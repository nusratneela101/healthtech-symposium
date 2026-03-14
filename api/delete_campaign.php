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
    http_response_code(400);
    echo json_encode(['error' => 'campaign_id required']);
    exit;
}

try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();
    // Delete email logs for this campaign first (foreign key safety)
    Database::query("DELETE FROM email_logs WHERE campaign_id = ?", [$campaignId]);
    // Delete the campaign
    Database::query("DELETE FROM campaigns WHERE id = ?", [$campaignId]);
    $pdo->commit();
    echo json_encode(['success' => true, 'deleted_campaign_id' => $campaignId]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
}
