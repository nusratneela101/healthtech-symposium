<?php
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

// Check for positive-sentiment responses in last 15 minutes
// (response_type column does not exist; using sentiment='positive' instead)
$hotLeads = Database::fetchAll(
    "SELECT r.*, l.company, l.full_name as lead_name, l.email as lead_email, l.role as lead_role
     FROM responses r
     LEFT JOIN leads l ON l.email = r.email
     WHERE r.sentiment = 'positive'
     AND r.received_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
     AND r.hot_alert_sent = 0
     ORDER BY r.received_at DESC"
);

if (empty($hotLeads)) {
    echo json_encode(['hot_leads' => 0, 'count' => 0, 'leads' => []]);
    exit;
}

// Mark as alerted
$ids = array_column($hotLeads, 'id');
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    Database::query("UPDATE responses SET hot_alert_sent=1 WHERE id IN ($placeholders)", $ids);
}

// Format for email
$details = [];
foreach ($hotLeads as $hl) {
    $details[] = [
        'name'    => $hl['lead_name']  ?: ($hl['email'] ?? ''),
        'email'   => $hl['lead_email'] ?: ($hl['email'] ?? ''),
        'company' => $hl['company']    ?? '',
        'role'    => $hl['lead_role']  ?? '',
        'subject' => $hl['subject']    ?? '',
        'snippet' => substr($hl['body'] ?? '', 0, 200)
    ];
}

echo json_encode([
    'hot_leads' => count($hotLeads),
    'count'     => count($hotLeads),
    'leads'     => $details
]);
