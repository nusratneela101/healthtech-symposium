<?php
ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/check_sending_limits.php';
require_once __DIR__ . '/../includes/campaign_sender.php';

ob_clean();
header('Content-Type: application/json');

$automationMode = getSetting('automation_mode', 'cron');
if ($automationMode !== 'cron') {
    echo json_encode(['success' => false, 'error' => 'Cron mode is disabled. automation_mode = ' . $automationMode]);
    exit;
}

$apiKey = $_GET['api_key'] ?? (json_decode(file_get_contents('php://input'), true)['api_key'] ?? '');
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$startTime     = microtime(true);
$maxRunSeconds = 240; // 4 minutes max

// ── Acquire advisory lock to prevent overlapping cron runs ───────────────────
try {
    $lockRow      = Database::fetchOne("SELECT GET_LOCK('healthtech_followup_sender', 0) AS got_lock");
    $lockAcquired = isset($lockRow['got_lock']) && (int)$lockRow['got_lock'] === 1;
} catch (Exception $e) {
    $lockAcquired = true; // if advisory lock unavailable, proceed anyway
}

if (!$lockAcquired) {
    try {
        Database::query(
            "INSERT INTO cron_log (job_name, status, message, duration_ms)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message),
                                     duration_ms=VALUES(duration_ms), last_run=NOW()",
            ['followup_sender', 'skipped', 'Skipped: another instance is running (locked)', 0]
        );
    } catch (Exception $e) { /* ignore */ }
    echo json_encode([
        'success' => false,
        'skipped' => true,
        'reason'  => 'Another sender is already running',
    ]);
    exit;
}

$followUpSeq  = 2;
$daysDelay    = max(1, (int)getSetting('followup_days_delay', '3'));
$batchSize    = max(1, (int)getSetting('cron_batch_size', '50'));
$delaySeconds = max(0, (int)getSetting('send_delay', '5'));

// Find eligible leads: received sequence 1 more than $daysDelay days ago,
// have not replied, have not been sent sequence 2 yet, and are not unsubscribed/bounced.
$eligibleLeads = Database::fetchAll(
    "SELECT l.*, el.campaign_id, el.sent_at AS first_sent_at
     FROM leads l
     INNER JOIN email_logs el ON el.lead_id = l.id AND el.follow_up_sequence = 1
     WHERE l.status NOT IN ('unsubscribed','bounced')
       AND el.sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)
       AND NOT EXISTS (SELECT 1 FROM responses r WHERE r.from_email = l.email)
       AND NOT EXISTS (
           SELECT 1 FROM email_logs el2
            WHERE el2.lead_id = l.id
              AND el2.campaign_id = el.campaign_id
              AND el2.follow_up_sequence = 2
       )
     GROUP BY l.id, el.campaign_id
     LIMIT ?",
    [$daysDelay, $batchSize]
);

$totalSent   = 0;
$totalFailed = 0;
$limitHit    = false;
$limitReason = '';

foreach ($eligibleLeads as $lead) {
    // Cap execution time
    if ((microtime(true) - $startTime) > $maxRunSeconds) {
        $limitHit    = true;
        $limitReason = 'Max run time reached (' . $maxRunSeconds . 's)';
        break;
    }

    // Respect warm-up and daily/weekly/monthly limits
    $limitCheck = checkSendingLimits($followUpSeq);
    if (!$limitCheck['allowed']) {
        $limitHit    = true;
        $limitReason = $limitCheck['reason'];
        break;
    }

    $campaignId = (int)$lead['campaign_id'];
    $campaign   = Database::fetchOne("SELECT * FROM campaigns WHERE id=?", [$campaignId]);
    if (!$campaign) {
        $totalFailed++;
        continue;
    }

    $tpl = Database::fetchOne("SELECT * FROM email_templates WHERE id=?", [$campaign['template_id']]);
    if (!$tpl) {
        $totalFailed++;
        continue;
    }

    $result = sendCampaignEmail($campaign, $tpl, $lead, $followUpSeq);

    if ($result['status'] === 'sent') {
        $totalSent++;
    } else {
        $totalFailed++;
    }

    if ($delaySeconds > 0) {
        sleep($delaySeconds);
    }
}

$durationMs = (int)round((microtime(true) - $startTime) * 1000);
$message    = sprintf('Follow-up sent: %d, Failed: %d', $totalSent, $totalFailed);
if ($limitHit) {
    $message .= ' | Limit: ' . $limitReason;
}

// ── Release the advisory lock ─────────────────────────────────────────────────
try {
    Database::fetchOne("SELECT RELEASE_LOCK('healthtech_followup_sender')");
} catch (Exception $e) { /* ignore */ }

try {
    Database::query(
        "INSERT INTO cron_log (job_name, status, message, duration_ms)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message),
                                 duration_ms=VALUES(duration_ms), last_run=NOW()",
        ['followup_sender', 'ok', $message, $durationMs]
    );
} catch (Exception $e) { /* ignore */ }

echo json_encode([
    'success'      => true,
    'sent'         => $totalSent,
    'failed'       => $totalFailed,
    'limit_hit'    => $limitHit,
    'limit_reason' => $limitReason ?: null,
]);
