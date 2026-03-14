<?php
ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

ob_clean();
header('Content-Type: application/json');

$apiKey = $_GET['api_key'] ?? (json_decode(file_get_contents('php://input'), true)['api_key'] ?? '');
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$leads = $input['leads'] ?? [];
$source = trim($input['source'] ?? 'Apollo');
$searchParams = trim($input['search_params'] ?? '');

// Create collection record
Database::query(
    "INSERT INTO lead_collections (source, total_fetched, status, search_params, started_at) VALUES(?,?,'running',?,NOW())",
    [$source, count($leads), $searchParams]
);
$collectionId = (int)Database::lastInsertId();

$saved = 0;
$skipped = 0;
$duplicates = 0;

foreach ($leads as $l) {
    $email = strtolower(trim($l['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $skipped++;
        continue;
    }

    // Check if lead already exists
    $existing = Database::fetchOne("SELECT id FROM leads WHERE email=?", [$email]);

    if ($existing) {
        $duplicates++;
        // Still log as duplicate in collection items
        Database::query(
            "INSERT INTO lead_collection_items (collection_id, lead_id, action) VALUES(?,?,'duplicate')",
            [$collectionId, (int)$existing['id']]
        );
        continue;
    }

    $fn        = trim($l['first_name'] ?? '');
    $ln        = trim($l['last_name'] ?? '');
    $segment   = trim($l['segment'] ?? 'Other');
    $validSegs = getSegments();
    if (!in_array($segment, $validSegs, true)) {
        $mapped = mapApolloSegment($segment);
        $segment = ($mapped !== 'Other') ? $mapped : detectSegment(trim($l['job_title'] ?? ''), trim($l['company'] ?? ''));
    }

    try {
        Database::query(
            "INSERT INTO leads (first_name,last_name,full_name,email,company,job_title,role,segment,country,province,city,source,linkedin_url)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $fn, $ln, trim($l['full_name'] ?? "$fn $ln"), $email,
                trim($l['company'] ?? ''), trim($l['job_title'] ?? ''),
                trim($l['role'] ?? ''), $segment,
                trim($l['country'] ?? 'Canada'), trim($l['province'] ?? ''),
                trim($l['city'] ?? ''), trim($l['source'] ?? $source),
                trim($l['linkedin_url'] ?? '')
            ]
        );
        $leadId = (int)Database::lastInsertId();
        $saved++;

        // Log in collection items
        Database::query(
            "INSERT INTO lead_collection_items (collection_id, lead_id, action) VALUES(?,?,'created')",
            [$collectionId, $leadId]
        );
    } catch (Exception $e) {
        $skipped++;
    }
}

// Update collection record
Database::query(
    "UPDATE lead_collections SET total_saved=?, total_skipped=?, total_duplicates=?, status='completed', completed_at=NOW() WHERE id=?",
    [$saved, $skipped, $duplicates, $collectionId]
);

echo json_encode([
    'success' => true,
    'collection_id' => $collectionId,
    'saved' => $saved,
    'skipped' => $skipped,
    'duplicates' => $duplicates,
    'total_fetched' => count($leads)
]);
