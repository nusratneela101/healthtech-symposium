<?php
// Ensure output buffering is active before any output
ob_start();

// Bootstrap: set session name and start session BEFORE loading config
// (config.php also calls session_start, but only if PHP_SESSION_NONE;
//  we need to set the correct session name first so the browser cookie is read)
require_once __DIR__ . '/../includes/session_bootstrap.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

ob_clean();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $stats = [
        'total_leads'      => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads")['c'] ?? 0),
        'new_leads'        => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='new'")['c'] ?? 0),
        'emailed'          => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='emailed'")['c'] ?? 0),
        'responded'        => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='responded'")['c'] ?? 0),
        'converted'        => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='converted'")['c'] ?? 0),
        'unsubscribed'     => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='unsubscribed'")['c'] ?? 0),
        'total_campaigns'  => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM campaigns")['c'] ?? 0),
        'emails_sent'      => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='sent'")['c'] ?? 0),
        'unread_responses' => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses WHERE is_read=0")['c'] ?? 0),
        'delivered'        => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='delivered'")['c'] ?? 0),
        'bounced'          => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status IN ('bounced','failed')")['c'] ?? 0),
        // Fix: response_type column does not exist; using sentiment='positive' instead
        'hot_leads'        => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses WHERE sentiment='positive'")['c'] ?? 0),
        'followups_sent'   => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE follow_up_sequence=2")['c'] ?? 0),
        'week_sends'       => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE sent_at >= DATE(NOW() - INTERVAL WEEKDAY(NOW()) DAY)")['c'] ?? 0),
        'month_sends'      => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE sent_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")['c'] ?? 0),
        'opened'           => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE opened_at IS NOT NULL")['c'] ?? 0),
    ];
    $openRateStr = 'N/A';
    if ($stats['emails_sent'] > 0 && $stats['opened'] > 0) {
        $openRateStr = round(($stats['opened'] / $stats['emails_sent']) * 100, 1) . '%';
    }
    $stats['open_rate_str'] = $openRateStr;
    echo json_encode($stats);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}