<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$apiKey = $input['api_key'] ?? ($_GET['api_key'] ?? '');

if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$campaignId = (int)($input['campaign_id'] ?? 0);
if (!$campaignId) {
    echo json_encode(['error' => 'campaign_id required']);
    exit;
}

$fields  = [];
$allowed = ['name','status','sent_count','failed_count','total_leads','started_at','completed_at'];
foreach ($allowed as $f) {
    if (isset($input[$f])) {
        $fields[$f] = $input[$f];
    }
}

if (empty($fields)) {
    echo json_encode(['error' => 'No fields to update']);
    exit;
}

$sets   = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
$values = array_values($fields);
$values[] = $campaignId;

Database::query("UPDATE campaigns SET $sets WHERE id=?", $values);
echo json_encode(['success' => true]);
