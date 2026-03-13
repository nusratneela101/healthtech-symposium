<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
Auth::check();

try {
    $stats = [
        'total_leads'      => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads")['c'] ?? 0),
        'new_leads'        => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='new'")['c'] ?? 0),
        'responded'        => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='responded'")['c'] ?? 0),
        'converted'        => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='converted'")['c'] ?? 0),
        'unsubscribed'     => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='unsubscribed'")['c'] ?? 0),
        'total_campaigns'  => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM campaigns")['c'] ?? 0),
        'emails_sent'      => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='sent'")['c'] ?? 0),
        'unread_responses' => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses WHERE is_read=0")['c'] ?? 0),
        'delivered'        => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='delivered'")['c'] ?? 0),
        'bounced'          => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status IN ('bounced','failed')")['c'] ?? 0),
        'hot_leads'        => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses WHERE response_type='interested'")['c'] ?? 0),
        'followups_sent'   => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE follow_up_sequence=2")['c'] ?? 0),
        'week_sends'       => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='sent' AND sent_at >= DATE(NOW() - INTERVAL WEEKDAY(NOW()) DAY)")['c'] ?? 0),
        'month_sends'      => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='sent' AND sent_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")['c'] ?? 0),
        'opened'           => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE opened_at IS NOT NULL")['c'] ?? 0),
    ];
    $openRate = 'N/A';
    if ($stats['emails_sent'] > 0 && $stats['opened'] > 0) {
        $openRate = round(($stats['opened'] / $stats['emails_sent']) * 100, 1) . '%';
    }
    $stats['open_rate'] = $openRate;
    echo json_encode(['success' => true, 'stats' => $stats]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
