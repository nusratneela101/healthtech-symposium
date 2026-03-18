<?php
/**
 * Campaign Status API
 * Returns current sent_count, failed_count, and status for a campaign.
 * Used by the auto_campaign.php frontend to poll live progress.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$apiKey = $_GET['api_key'] ?? '';
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$campaignId = (int)($_GET['campaign_id'] ?? 0);
if (!$campaignId) {
    echo json_encode(['success' => false, 'error' => 'campaign_id required']);
    exit;
}

$campaign = Database::fetchOne("SELECT id, status, sent_count, failed_count, total_leads, target_mode, target_count FROM campaigns WHERE id=?", [$campaignId]);
if (!$campaign) {
    echo json_encode(['success' => false, 'error' => 'Campaign not found']);
    exit;
}

echo json_encode([
    'success'      => true,
    'campaign_id'  => (int)$campaign['id'],
    'status'       => $campaign['status'],
    'sent_count'   => (int)$campaign['sent_count'],
    'failed_count' => (int)$campaign['failed_count'],
    'total_leads'  => (int)$campaign['total_leads'],
    'target_mode'  => $campaign['target_mode'] ?? 'all',
    'target_count' => (int)($campaign['target_count'] ?? 0),
]);
