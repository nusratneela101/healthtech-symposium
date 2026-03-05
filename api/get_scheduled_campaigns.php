<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$apiKey = $_GET['api_key'] ?? '';
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Find campaigns where scheduled_at has passed and status is 'scheduled'
$campaigns = Database::fetchAll(
    "SELECT c.id, c.name, c.campaign_key, c.template_id, c.total_leads, c.scheduled_at,
            c.filter_segment, c.filter_role, c.filter_province
     FROM campaigns c
     WHERE c.status = 'scheduled'
       AND c.scheduled_at <= NOW()
     ORDER BY c.scheduled_at ASC
     LIMIT 5"
);

echo json_encode([
    'campaigns' => $campaigns,
    'count' => count($campaigns),
    'checked_at' => date('Y-m-d H:i:s')
]);
