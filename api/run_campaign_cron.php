<?php
/**
 * Campaign Cron Runner
 * Called by cPanel cron job every 5 minutes.
 * Sends pending campaign email batches.
 */
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

ob_clean();
header('Content-Type: application/json');

// Automation mode guard: skip if n8n is handling automation
if (getSetting('automation_mode', 'cron') !== 'cron') {
    echo json_encode(['skipped' => true, 'reason' => 'automation_mode is n8n']);
    exit;
}

$start = microtime(true);

// Load pending campaigns
$campaigns = [];
try {
    $campaigns = Database::fetchAll(
        "SELECT * FROM campaigns WHERE status IN ('running','scheduled') ORDER BY id ASC LIMIT 5"
    );
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$totalSent   = 0;
$totalFailed = 0;

foreach ($campaigns as $campaign) {
    // Get unsent leads for this campaign
    $leads = Database::fetchAll(
        "SELECT l.* FROM leads l
         LEFT JOIN email_logs el ON el.lead_id = l.id AND el.campaign_id = ?
         WHERE el.id IS NULL AND l.status NOT IN ('unsubscribed','bounced')
         ORDER BY l.id ASC LIMIT 50",
        [$campaign['id']]
    );

    if (empty($leads)) {
        // Mark campaign as completed
        Database::query("UPDATE campaigns SET status='completed' WHERE id=?", [$campaign['id']]);
        continue;
    }

    foreach ($leads as $lead) {
        $result = Email::send([
            'to'          => $lead['email'],
            'to_name'     => $lead['full_name'],
            'subject'     => $campaign['subject'] ?? '',
            'html'        => $campaign['body_html'] ?? '',
            'campaign_id' => $campaign['id'],
            'lead_id'     => $lead['id'],
        ]);

        if ($result['success'] ?? false) {
            $totalSent++;
            Database::query(
                "UPDATE campaigns SET sent_count = sent_count + 1 WHERE id=?",
                [$campaign['id']]
            );
        } else {
            $totalFailed++;
            Database::query(
                "UPDATE campaigns SET failed_count = failed_count + 1 WHERE id=?",
                [$campaign['id']]
            );
        }
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
        ['campaign_cron', 'ok', "Sent: {$totalSent}, Failed: {$totalFailed}", $duration]
    );
} catch (Exception $e) {}

echo json_encode([
    'success'      => true,
    'sent'         => $totalSent,
    'failed'       => $totalFailed,
    'duration_ms'  => $duration,
]);
