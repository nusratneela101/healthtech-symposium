<?php
ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

ob_clean();
header('Content-Type: application/json');

Auth::requireSuperAdmin();

// Get Apollo settings
$apolloApiKey   = getSetting('apollo_api_key', APOLLO_API_KEY);
$searchTitles   = getSetting('apollo_search_titles', 'Product Manager,Business Development Manager,Chief Operating Officer,Founder,Co-Founder,Managing Partner,General Partner');
$searchLocation = getSetting('apollo_search_location', 'Canada');
$searchIndustry = getSetting('apollo_search_industry', '');
$perPage        = max(1, min(100, (int)getSetting('apollo_per_page', '25')));
$maxPages       = max(1, min(10,  (int)getSetting('apollo_max_pages', '4')));

if (empty($apolloApiKey)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Apollo API key is not configured. Go to Settings → Apollo tab and enter your Apollo API key.',
    ]);
    exit;
}

// Build Apollo People Search payload
$titles = array_filter(array_map('trim', explode(',', $searchTitles)));

$allLeads   = [];
$page       = 1;
$totalPages = $maxPages;

while ($page <= $totalPages) {
    $payload = [
        'api_key'              => $apolloApiKey,
        'person_titles'        => array_values($titles),
        'person_locations'     => [$searchLocation],
        'page'                 => $page,
        'per_page'             => $perPage,
        'contact_email_status' => ['verified', 'likely to engage', 'guessed'],
    ];

    if (!empty($searchIndustry)) {
        $payload['q_organization_keyword_tags'] = array_values(array_filter(array_map('trim', explode(',', $searchIndustry))));
    }

    $ch = curl_init('https://api.apollo.io/v1/mixed_people/search');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Cache-Control: no-cache'],
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        // Log error detail but continue to return whatever was collected so far
        error_log("apollo_direct_collect: page {$page} failed (HTTP {$httpCode})" . ($curlError ? " — {$curlError}" : ''));
        break;
    }

    $data = json_decode($response, true);
    if (!isset($data['people']) || empty($data['people'])) {
        break;
    }

    foreach ($data['people'] as $person) {
        // Pick best available email
        $email = $person['email'] ?? '';
        if (empty($email) && !empty($person['contact']['email'])) {
            $email = $person['contact']['email'];
        }
        if (empty($email)) continue;

        $org      = $person['organization'] ?? ($person['employment_history'][0] ?? []);
        $industry = strtolower($org['industry'] ?? '');

        $allLeads[] = [
            'first_name'   => $person['first_name'] ?? '',
            'last_name'    => $person['last_name']  ?? '',
            'email'        => $email,
            'company'      => $org['name'] ?? ($person['organization_name'] ?? ''),
            'job_title'    => $person['title'] ?? '',
            'role'         => $person['seniority'] ?? '',
            'segment'      => $industry,
            'province'     => $person['state'] ?? '',
            'city'         => $person['city']  ?? '',
            'country'      => $person['country'] ?? 'Canada',
            'linkedin_url' => $person['linkedin_url'] ?? '',
            'source'       => 'Apollo Direct',
            'phone'        => $person['phone_numbers'][0]['sanitized_number'] ?? '',
        ];
    }

    // Check if there are more pages
    $totalFromApi    = (int)($data['pagination']['total_entries'] ?? 0);
    $calculatedPages = (int)ceil($totalFromApi / $perPage);
    $totalPages      = min($maxPages, $calculatedPages);

    $page++;
}

if (empty($allLeads)) {
    // Still create a collection record so the user can see the run happened
    Database::query(
        "INSERT INTO lead_collections (source, total_fetched, total_saved, total_skipped, total_duplicates, status, search_params, started_at, completed_at) VALUES('Apollo Direct',0,0,0,0,'completed',?,NOW(),NOW())",
        [json_encode(['titles' => array_values($titles), 'location' => $searchLocation])]
    );
    $collectionId = (int)Database::lastInsertId();
    echo json_encode([
        'success'       => true,
        'collection_id' => $collectionId,
        'fetched'       => 0,
        'saved'         => 0,
        'skipped'       => 0,
        'duplicates'    => 0,
        'message'       => 'Apollo returned 0 results. Check your search filters in Settings → Apollo.',
    ]);
    exit;
}

// Create collection record
Database::query(
    "INSERT INTO lead_collections (source, total_fetched, status, search_params, started_at) VALUES('Apollo Direct',?,'running',?,NOW())",
    [count($allLeads), json_encode(['titles' => array_values($titles), 'location' => $searchLocation])]
);
$collectionId = (int)Database::lastInsertId();

$created    = 0;
$skipped    = 0;
$duplicates = 0;
$validSegs  = getSegments();

foreach ($allLeads as $l) {
    $email = strtolower(trim($l['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped++; continue; }

    $existing = Database::fetchOne("SELECT id FROM leads WHERE email = ?", [$email]);
    if ($existing) {
        $duplicates++;
        Database::query(
            "INSERT INTO lead_collection_items (collection_id, lead_id, action) VALUES(?,?,'duplicate')",
            [$collectionId, (int)$existing['id']]
        );
        continue;
    }

    $fn      = trim($l['first_name']);
    $ln      = trim($l['last_name']);
    $segment = trim($l['segment']);
    if (!in_array($segment, $validSegs, true)) {
        $mapped  = mapApolloSegment($segment);
        $segment = ($mapped !== 'Other')
            ? $mapped
            : detectSegment(trim($l['job_title']), trim($l['company']));
    }

    try {
        Database::query(
            "INSERT IGNORE INTO leads
             (first_name,last_name,full_name,email,company,job_title,role,segment,country,province,city,phone,source,linkedin_url)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $fn, $ln, trim("$fn $ln"), $email,
                trim($l['company']), trim($l['job_title']), trim($l['role']), $segment,
                trim($l['country'] ?: 'Canada'), trim($l['province']), trim($l['city']),
                trim($l['phone']), 'Apollo Direct', trim($l['linkedin_url']),
            ]
        );
        $leadId = (int)Database::lastInsertId();
        if ($leadId > 0) {
            $created++;
            Database::query(
                "INSERT INTO lead_collection_items (collection_id, lead_id, action) VALUES(?,?,'created')",
                [$collectionId, $leadId]
            );
        } else {
            $duplicates++;
        }
    } catch (Exception $e) {
        $skipped++;
    }
}

Database::query(
    "UPDATE lead_collections SET total_saved=?, total_skipped=?, total_duplicates=?, status='completed', completed_at=NOW() WHERE id=?",
    [$created, $skipped, $duplicates, $collectionId]
);

echo json_encode([
    'success'       => true,
    'collection_id' => $collectionId,
    'fetched'       => count($allLeads),
    'saved'         => $created,
    'skipped'       => $skipped,
    'duplicates'    => $duplicates,
]);
