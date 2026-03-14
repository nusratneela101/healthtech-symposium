<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}
$input = $input ?? [];

$apiKey = $input['api_key'] ?? ($_GET['api_key'] ?? '');

if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Rate limit: 120 requests per minute per API caller
RateLimiter::enforce($apiKey ?: RateLimiter::getIdentifier(), 'api/save_lead', 120, 60);

$leads   = $input['leads'] ?? [];
$source  = trim($input['source'] ?? 'Apollo');
$searchParams = trim($input['search_params'] ?? '');

if (!is_array($leads)) {
    http_response_code(400);
    echo json_encode(['error' => 'The "leads" field must be an array.']);
    exit;
}

// Cap the number of leads per request
if (count($leads) > 1000) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Too many leads in a single request. Maximum is 1000.',
        'count' => count($leads),
    ]);
    exit;
}

// Create collection record
Database::query(
    "INSERT INTO lead_collections (source, total_fetched, status, search_params, started_at) VALUES(?,?,'running',?,NOW())",
    [$source, count($leads), $searchParams]
);
$collectionId = (int)Database::lastInsertId();

$created    = 0;
$skipped    = 0;
$duplicates = 0;
$errorList  = [];

foreach ($leads as $index => $l) {
    $email = strtolower(trim($l['email'] ?? ''));

    if ($email === '') {
        $skipped++;
        $errorList[] = ['index' => $index, 'email' => '', 'reason' => 'Email field is missing or empty.'];
        continue;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $skipped++;
        $errorList[] = ['index' => $index, 'email' => $email, 'reason' => 'Invalid email address format.'];
        continue;
    }

    // Check if lead already exists
    $existing = Database::fetchOne("SELECT id FROM leads WHERE email = ?", [$email]);
    if ($existing) {
        $duplicates++;
        Database::query(
            "INSERT INTO lead_collection_items (collection_id, lead_id, action) VALUES(?,?,'duplicate')",
            [$collectionId, (int)$existing['id']]
        );
        continue;
    }

    $fn      = trim($l['first_name'] ?? '');
    $ln      = trim($l['last_name']  ?? '');
    $phone   = trim($l['phone']      ?? '');
    $segment = trim($l['segment']    ?? 'Other');

    $validSegs = [
        'Healthcare Providers',
        'Health IT & Digital Health',
        'Pharmaceutical & Biotech',
        'Medical Devices',
        'Venture Capital / Investors',
        'Fintech Startups',
        'Other',
    ];

    if (!in_array($segment, $validSegs, true)) {
        $segment = 'Other';
    }

    if ($segment === 'Other') {
        $jobTitle = trim($l['role'] ?? ($l['job_title'] ?? ''));
        $company  = trim($l['company'] ?? '');
        $segment  = detectSegment($jobTitle, $company);
    }

    try {
        Database::query(
            "INSERT IGNORE INTO leads
             (first_name,last_name,full_name,email,company,job_title,role,segment,country,province,city,phone,source,linkedin_url)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $fn, $ln, trim($l['full_name'] ?? "$fn $ln"), $email,
                trim($l['company']      ?? ''), trim($l['job_title']    ?? ''),
                trim($l['role']         ?? ''), $segment,
                trim($l['country']      ?? 'Canada'), trim($l['province'] ?? ''),
                trim($l['city']         ?? ''), $phone,
                trim($l['source']       ?? 'API'),
                trim($l['linkedin_url'] ?? ''),
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
            $skipped++;
            $errorList[] = ['index' => $index, 'email' => $email, 'reason' => 'Lead was silently skipped (possible duplicate).'];
        }
    } catch (Exception $e) {
        $skipped++;
        $errorList[] = ['index' => $index, 'email' => $email, 'reason' => 'Database error: ' . $e->getMessage()];
        error_log('save_lead.php error for ' . $email . ': ' . $e->getMessage());
    }
}

// Update collection record
Database::query(
    "UPDATE lead_collections SET total_saved=?, total_skipped=?, total_duplicates=?, status='completed', completed_at=NOW() WHERE id=?",
    [$created, $skipped, $duplicates, $collectionId]
);

echo json_encode([
    'success'        => true,
    'created'        => $created,
    'skipped'        => $skipped,
    'duplicates'     => $duplicates,
    'total'          => count($leads),
    'collection_id'  => $collectionId,
    'total_errors'   => count($errorList),
    'errors'         => array_slice($errorList, 0, 10),
]);

