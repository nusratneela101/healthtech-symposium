<?php
/**
 * Follow-up Cron Runner
 * Called by cPanel cron job daily at 10 AM.
 * Sends follow-up emails to leads who did not respond.
 */
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';

ob_clean();
header('Content-Type: application/json');

// Automation mode guard: skip if n8n is handling automation
if (getSetting('automation_mode', 'cron') !== 'cron') {
    echo json_encode(['skipped' => true, 'reason' => 'automation_mode is n8n']);
    exit;
}

$start = microtime(true);

$totalSent   = 0;
$totalFailed = 0;

try {
    // Find leads that received a campaign email but have not responded
    // and have not yet received a follow-up in the last 3 days
    $followupLeads = Database::fetchAll(
        "SELECT el.lead_id, el.campaign_id, l.email, l.full_name, c.subject, c.body_html
         FROM email_logs el
         JOIN leads l ON l.id = el.lead_id
         JOIN campaigns c ON c.id = el.campaign_id
         LEFT JOIN responses r ON r.lead_id = el.lead_id AND r.campaign_id = el.campaign_id
         LEFT JOIN email_logs fu ON fu.lead_id = el.lead_id
                                AND fu.campaign_id = el.campaign_id
                                AND fu.follow_up_sequence >= 1
                                AND fu.sent_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
         WHERE el.status = 'sent'
           AND el.follow_up_sequence = 0
           AND r.id IS NULL
           AND fu.id IS NULL
           AND l.status NOT IN ('unsubscribed','bounced')
           AND el.sent_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
         ORDER BY el.sent_at ASC
         LIMIT 50"
    );
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

foreach ($followupLeads as $lead) {
    $subject = 'Following up: ' . ($lead['subject'] ?? '');
    $result  = Email::send([
        'to'                  => $lead['email'],
        'to_name'             => $lead['full_name'],
        'subject'             => $subject,
        'html'                => $lead['body_html'] ?? '',
        'campaign_id'         => $lead['campaign_id'],
        'lead_id'             => $lead['lead_id'],
        'follow_up_sequence'  => 1,
    ]);

    if ($result['success'] ?? false) {
        $totalSent++;
    } else {
        $totalFailed++;
    }
}

$duration = (int)round((microtime(true) - $start) * 1000);

// Log run
try {
    Database::query(
        "CREATE TABLE IF NOT EXISTS `cron_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `job_key` varchar(100) NOT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'ok',
            `message` text DEFAULT NULL,
            `duration_ms` int(11) DEFAULT NULL,
            `ran_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `job_key` (`job_key`),
            KEY `ran_at` (`ran_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    Database::query(
        "INSERT INTO cron_logs (job_key, status, message, duration_ms) VALUES (?, ?, ?, ?)",
        ['followup_sender', 'ok', "Sent: {$totalSent}, Failed: {$totalFailed}", $duration]
    );
} catch (Exception $e) {}

echo json_encode([
    'success'      => true,
    'sent'         => $totalSent,
    'failed'       => $totalFailed,
    'duration_ms'  => $duration,
]);
