<?php
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

ob_clean();
header('Content-Type: application/json');

$startTime = microtime(true);

// Auth check
$apiKey = $_GET['api_key'] ?? ''; 
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Read Apollo config from database
$apolloApiKey = getSetting('apollo_api_key', defined('APOLLO_API_KEY') ? APOLLO_API_KEY : '');
$location     = getSetting('apollo_search_location', 'Canada');
$industry     = getSetting('apollo_search_industry', 'Health Technology');
$titlesRaw    = getSetting('apollo_search_titles', '');
$perPage      = min(25, max(1, (int)getSetting('apollo_per_page', '25')));
$maxPages     = max(1, (int)getSetting('apollo_max_pages', '5'));

titles = array_values(array_filter(array_map('trim', explode("\n", $titlesRaw))));

if (empty($apolloApiKey)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Apollo API key is not configured']);
    exit;
}

// Map Apollo person/org industry to lead segment
function mapSegment(string $ind): string {
    $i = strtolower($ind);
    if (strpos($i, 'health it') !== false || strpos($i, 'digital health') !== false) return 'Health IT & Digital Health';
    if (strpos($i, 'pharma') !== false || strpos($i, 'biotech') !== false) return 'Pharmaceutical & Biotech';
    if (strpos($i, 'medical device') !== false) return 'Medical Devices';
    if (strpos($i, 'venture') !== false || strpos($i, 'invest') !== false) return 'Venture Capital / Investors';
    if (strpos($i, 'hospital') !== false || strpos($i, 'healthcare provider') !== false) return 'Healthcare Providers';
    return 'Health IT & Digital Health';
}

// Create collection record
Database::query(
    "INSERT INTO lead_collections (source, total_fetched, status, search_params, started_at) VALUES(?,?,'running',?,NOW())",
    ['Apollo Cron', 0, json_encode(['location' => $location, 'industry' => $industry])]
);
$collectionId = (int)Database::lastInsertId();

$saved        = 0;
$skipped      = 0;
$duplicates   = 0;
$totalFetched = 0;
$apiError     = null;

$debugInfo = [
    'apollo_key_prefix'       => substr($apolloApiKey, 0, 6),
    'titles_used'             => !empty($titles) ? $titles : ['CEO'],
    'location_used'           => $location,
    'per_page_used'           => $perPage,
    'apollo_http_code'        => null,
    'apollo_people_count'     => null,
    'apollo_response_preview' => null,
    'api_error'               => null,
];

// Loop through pages and fetch leads from Apollo
for ($page = 1; $page <= $maxPages; $page++) {
    // Apollo requires the api_key in the X-Api-Key header
    $requestBody = json_encode([
        'q_organization_industry_tag_ids' => [],
        'person_titles'                   => !empty($titles) ? $titles : ['CEO'],
        'person_locations'                => [$location],
        'page'                            => $page,
        'per_page'                        => $perPage,
    ]);

    $ch = curl_init('https://api.apollo.io/v1/mixed_people/search');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $requestBody,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Cache-Control: no-cache',
            'X-Api-Key: ' . $apolloApiKey,
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($page === 1) {
        $debugInfo['apollo_http_code']        = $httpCode;
        $debugInfo['apollo_response_preview'] = substr((string)$response, 0, 500);
    }

    if ($response === false || $httpCode !== 200) {
        $apiError = $response === false
            ? 'cURL error on page ' . $page
            : 'Apollo API returned HTTP ' . $httpCode . ' on page ' . $page . ' | Response: ' . substr((string)$response, 0, 300);
        $debugInfo['api_error'] = $apiError;
        break;
    }

    $data   = json_decode($response, true);
    $people = $data['people'] ?? [];

    if ($page === 1) {
        $debugInfo['apollo_people_count'] = count($people);
    }

    if (empty($people)) {
        break;
    }

    $totalFetched += count($people);

    foreach ($people as $person) {
        $email       = strtolower(trim($person['email'] ?? ''));
        $fullName    = trim($person['name'] ?? trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')));
        $linkedinUrl = trim($person['linkedin_url'] ?? '');

        if (empty($fullName) && empty($linkedinUrl)) {
            $skipped++;
            continue;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'noemail_' . uniqid() . '@noemail.placeholder';
        }

        if (strpos($email, '@noemail.placeholder') === false) {
            $existing = Database::fetchOne("SELECT id FROM leads WHERE email=?", [$email]);
            if ($existing) {
                $duplicates++;
                Database::query(
                    "INSERT INTO lead_collection_items (collection_id, lead_id, action) VALUES(?,?,'duplicate')",
                    [$collectionId, (int)$existing['id']]
                );
                continue;
            }
        }

        $fn          = trim($person['first_name'] ?? '');
        $ln          = trim($person['last_name'] ?? '');
        $orgName     = trim($person['organization']['name'] ?? '');
        $orgIndustry = trim($person['organization']['industry'] ?? '');
        $segment     = mapSegment($orgIndustry ?: $industry);

        try {
            Database::query(
                "INSERT INTO leads (first_name,last_name,full_name,email,company,job_title,role,segment,country,province,city,source,linkedin_url)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $fn, $ln, $fullName ?: "$fn $ln", $email,
                    $orgName, trim($person['title'] ?? ''),
                    trim($person['title'] ?? ''), $segment,
                    trim($person['country'] ?? 'Canada'), trim($person['state'] ?? ''),
                    trim($person['city'] ?? ''), 'Apollo Cron',
                    $linkedinUrl,
                ]
            );
            $leadId = (int)Database::lastInsertId();
            $saved++;

            Database::query(
                "INSERT INTO lead_collection_items (collection_id, lead_id, action) VALUES(?,?,'created')",
                [$collectionId, $leadId]
            );
        } catch (Exception $e) {
            $skipped++;
        }
    }
}

Database::query(
    "UPDATE lead_collections SET total_fetched=?, total_saved=?, total_skipped=?, total_duplicates=?, status='completed', completed_at=NOW() WHERE id=?",
    [$totalFetched, $saved, $skipped, $duplicates, $collectionId]
);

$durationMs = (int)round((microtime(true) - $startTime) * 1000);
$logStatus  = $apiError ? 'error' : 'ok';
$message    = "Fetched: {$totalFetched}, Saved: {$saved}, Duplicates: {$duplicates}, Skipped: {$skipped}";
if ($apiError) {
    $message .= ' | Error: ' . $apiError;
}
try {
    Database::query(
        "INSERT INTO cron_log (job_name, status, message, duration_ms)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message),
                                 duration_ms=VALUES(duration_ms), last_run=NOW()",
        ['lead_collector', $logStatus, $message, $durationMs]
    );
} catch (Exception $e) { /* ignore */ }

echo json_encode([
    'success'       => true,
    'collection_id' => $collectionId,
    'saved'         => $saved,
    'skipped'       => $skipped,
    'duplicates'    => $duplicates,
    'total_fetched' => $totalFetched,
    'debug'         => $debugInfo,
]);
