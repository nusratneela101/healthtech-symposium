<?php
ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

ob_clean();
header('Content-Type: application/json');

$apiKey = $_GET['api_key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$campaignId = (int)($_GET['campaign_id'] ?? 0) ?: null;
$limit      = min(500, max(1, (int)($_GET['limit'] ?? 100)));
$daysSince  = max(1, (int)($_GET['days_since'] ?? 7));

// Find leads where:
// - follow_up_sequence=1 log exists (first email sent)
// - Sent more than N days ago
// - Lead has NOT replied (not in responses table)
// - Lead has NOT unsubscribed
// - No follow_up_sequence=2 log exists for this lead+campaign
$campaignFilter = $campaignId ? 'AND el.campaign_id = ?' : '';
$params = [];
if ($campaignId) {
    $params[] = $campaignId;
    $params[] = $campaignId;
}
$params[] = $daysSince;

$sql = "SELECT l.id, l.first_name, l.last_name, l.full_name, l.email,
               l.company, l.job_title, l.role, l.segment, l.city, l.province,
               el.campaign_id, el.sent_at AS first_sent_at
        FROM leads l
        INNER JOIN email_logs el
               ON el.lead_id = l.id
              AND el.follow_up_sequence = 1
              $campaignFilter
        WHERE l.status NOT IN ('unsubscribed','bounced')
          AND el.sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND NOT EXISTS (
              SELECT 1 FROM responses r WHERE r.from_email = l.email
          )
          AND NOT EXISTS (
              SELECT 1 FROM email_logs el2
               WHERE el2.lead_id = l.id
                 AND el2.campaign_id = el.campaign_id
                 AND el2.follow_up_sequence = 2
          )
        GROUP BY l.id, el.campaign_id
        LIMIT $limit";

$leads = Database::fetchAll($sql, $params);

echo json_encode([
    'count'  => count($leads),
    'leads'  => $leads,
]);
