<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
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

// Validate status if provided
if (isset($fields['status'])) {
    $validStatuses = ['draft', 'running', 'paused', 'completed', 'scheduled'];
    if (!in_array($fields['status'], $validStatuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status. Must be one of: draft, running, paused, completed, scheduled']);
        exit;
    }
}

$sets   = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
$values = array_values($fields);

// Auto-set timestamps based on status transition
$extraSql = '';
if (($fields['status'] ?? '') === 'running') {
    $extraSql = ', started_at = COALESCE(started_at, NOW())';
} elseif (($fields['status'] ?? '') === 'completed') {
    $extraSql = ', completed_at = NOW()';
}

$values[] = $campaignId;

Database::query("UPDATE campaigns SET $sets{$extraSql} WHERE id=?", $values);
echo json_encode(['success' => true, 'campaign_id' => $campaignId, 'status' => $fields['status'] ?? null]);
