<?php
/**
 * Campaign Batch Sender
 * Called by cPanel cron or directly to send a batch of emails for a campaign.
 * Accepts campaign_id via POST or GET.
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

// Accept API key for direct calls
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$apiKey = $input['api_key'] ?? ($_GET['api_key'] ?? '');

// If called with an API key, validate it; otherwise allow CLI/cron invocation
if ($apiKey !== '' && $apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$campaignId = (int)($input['campaign_id'] ?? ($_GET['campaign_id'] ?? 0));
$batchSize  = max(1, min(200, (int)($input['batch_size'] ?? ($_GET['batch_size'] ?? 50))));

if (!$campaignId) {
    echo json_encode(['error' => 'campaign_id required']);
    exit;
}

$campaign = Database::fetchOne("SELECT * FROM campaigns WHERE id=?", [$campaignId]);
if (!$campaign) {
    echo json_encode(['error' => 'Campaign not found']);
    exit;
}

$start = microtime(true);

// Rate limit
if ($apiKey !== '') {
    RateLimiter::enforce($apiKey ?: RateLimiter::getIdentifier(), 'api/send_campaign_batch', 60, 60);
}

// Get unsent leads for this campaign
$leads = Database::fetchAll(
    "SELECT l.* FROM leads l
     LEFT JOIN email_logs el ON el.lead_id = l.id AND el.campaign_id = ?
     WHERE el.id IS NULL AND l.status NOT IN ('unsubscribed','bounced')
     ORDER BY l.id ASC LIMIT ?",
    [$campaignId, $batchSize]
);

$sent   = 0;
$failed = 0;

foreach ($leads as $lead) {
    $result = Email::send([
        'to'          => $lead['email'],
        'to_name'     => $lead['full_name'],
        'subject'     => $campaign['subject'] ?? '',
        'html'        => $campaign['body_html'] ?? '',
        'campaign_id' => $campaignId,
        'lead_id'     => $lead['id'],
    ]);

    if ($result['success'] ?? false) {
        $sent++;
        Database::query(
            "UPDATE campaigns SET sent_count = sent_count + 1 WHERE id=?",
            [$campaignId]
        );
    } else {
        $failed++;
        Database::query(
            "UPDATE campaigns SET failed_count = failed_count + 1 WHERE id=?",
            [$campaignId]
        );
    }
}

// Mark completed if no more leads remain
$remaining = (int)(Database::fetchOne(
    "SELECT COUNT(*) AS c FROM leads l
     LEFT JOIN email_logs el ON el.lead_id = l.id AND el.campaign_id = ?
     WHERE el.id IS NULL AND l.status NOT IN ('unsubscribed','bounced')",
    [$campaignId]
)['c'] ?? 0);

if ($remaining === 0) {
    Database::query("UPDATE campaigns SET status='completed' WHERE id=?", [$campaignId]);
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
        ['campaign_batch', 'ok', "Campaign #{$campaignId} — Sent: {$sent}, Failed: {$failed}", $duration]
    );
} catch (Exception $e) {}

echo json_encode([
    'success'     => true,
    'campaign_id' => $campaignId,
    'sent'        => $sent,
    'failed'      => $failed,
    'remaining'   => $remaining,
    'duration_ms' => $duration,
]);
