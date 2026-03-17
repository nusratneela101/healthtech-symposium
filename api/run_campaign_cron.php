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

$startTime    = microtime(true);
$maxRunSeconds = 240; // 4 minutes max — prevents overlap with next 5-min cron run
$batchSize    = max(1, (int)getSetting('cron_batch_size', '50'));
$delaySeconds = max(0, (int)getSetting('cron_send_delay_seconds', '5'));

// ── Acquire a MySQL advisory lock to prevent overlapping cron runs ────────────
// GET_LOCK with timeout=0 means "fail immediately if already locked".
// All sending scripts use the same lock name so they cannot run concurrently.
try {
    $lockRow      = Database::fetchOne("SELECT GET_LOCK('healthtech_email_sender', 0) AS got_lock");
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
            ['campaign_sender', 'skipped', 'Skipped: another instance is running (locked)', 0]
        );
    } catch (Exception $e) { /* ignore */ }
    echo json_encode([
        'success' => false,
        'skipped' => true,
        'reason'  => 'Another sender is already running',
    ]);
    exit;
}

// Find all currently running campaigns
$runningCampaigns = Database::fetchAll("SELECT * FROM campaigns WHERE status='running'");

$totalSent       = 0;
$totalFailed     = 0;
$completedCount  = 0;
$limitHit        = false;
$limitReason     = '';
$campaignResults = [];

foreach ($runningCampaigns as $campaign) {
    $campaignId  = (int)$campaign['id'];
    $followUpSeq = 1;

    $tpl = Database::fetchOne("SELECT * FROM email_templates WHERE id=?", [$campaign['template_id']]);
    if (!$tpl) {
        $campaignResults[] = ['campaign_id' => $campaignId, 'error' => 'Template not found'];
        continue;
    }

    $sentThisCampaign   = 0;
    $failedThisCampaign = 0;
    $campaignComplete   = false;

    for ($i = 0; $i < $batchSize; $i++) {
        // Cap execution time to prevent overlap with next cron run
        if ((microtime(true) - $startTime) > $maxRunSeconds) {
            $limitHit    = true;
            $limitReason = 'Max run time reached (' . $maxRunSeconds . 's)';
            break 2;
        }

        // Respect warm-up and daily/weekly/monthly limits
        $limitCheck = checkSendingLimits($followUpSeq);
        if (!$limitCheck['allowed']) {
            $limitHit    = true;
            $limitReason = $limitCheck['reason'];
            break 2; // stop processing all campaigns
        }

        // Check fixed target limit
        if (($campaign['target_mode'] ?? 'all') === 'fixed' && (int)($campaign['target_count'] ?? 0) > 0) {
            if (((int)$campaign['sent_count'] + $sentThisCampaign) >= (int)$campaign['target_count']) {
                Database::query(
                    "UPDATE campaigns SET status='completed', completed_at=NOW() WHERE id=?",
                    [$campaignId]
                );
                $completedCount++;
                $campaignComplete = true;
                break;
            }
        }

        // Build lead query respecting campaign filters
        $where  = "l.status NOT IN ('unsubscribed','bounced','emailed') AND l.email NOT LIKE '%@noemail.placeholder'";
        $params = [];
        if (!empty($campaign['filter_segment'])) {
            $where   .= ' AND LOWER(TRIM(l.segment))=LOWER(TRIM(?))';
            $params[] = $campaign['filter_segment'];
        }
        if (!empty($campaign['filter_role'])) {
            $where   .= ' AND l.role LIKE ?';
            $params[] = '%' . $campaign['filter_role'] . '%';
        }
        if (!empty($campaign['filter_province'])) {
            $where   .= ' AND LOWER(TRIM(l.province))=LOWER(TRIM(?))';
            $params[] = $campaign['filter_province'];
        }

        $lead = Database::fetchOne(
            "SELECT l.* FROM leads l
             LEFT JOIN email_logs el
                    ON el.lead_id = l.id AND el.campaign_id = ? AND el.follow_up_sequence = ?
             WHERE $where AND el.id IS NULL LIMIT 1",
            array_merge([$campaignId, $followUpSeq], $params)
        );

        if (!$lead) {
            // No more eligible leads — mark campaign complete
            Database::query(
                "UPDATE campaigns SET status='completed', completed_at=NOW() WHERE id=?",
                [$campaignId]
            );
            $completedCount++;
            $campaignComplete = true;
            break;
        }

        $result = sendCampaignEmail($campaign, $tpl, $lead, $followUpSeq);

        if ($result['status'] === 'sent') {
            $sentThisCampaign++;
            $totalSent++;
        } else {
            $failedThisCampaign++;
            $totalFailed++;
        }

        if ($delaySeconds > 0) {
            sleep($delaySeconds);
        }
    }

    $campaignResults[] = [
        'campaign_id' => $campaignId,
        'sent'        => $sentThisCampaign,
        'failed'      => $failedThisCampaign,
        'complete'    => $campaignComplete,
    ];
}

$durationMs = (int)round((microtime(true) - $startTime) * 1000);
$message    = sprintf(
    'Sent: %d, Failed: %d, Completed campaigns: %d',
    $totalSent, $totalFailed, $completedCount
);
if ($limitHit) {
    $message .= ' | Limit: ' . $limitReason;
}

// ── Release the advisory lock ─────────────────────────────────────────────────
try {
    Database::fetchOne("SELECT RELEASE_LOCK('healthtech_email_sender')");
} catch (Exception $e) { /* ignore */ }

try {
    Database::query(
        "INSERT INTO cron_log (job_name, status, message, duration_ms)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message),
                                 duration_ms=VALUES(duration_ms), last_run=NOW()",
        ['campaign_sender', 'ok', $message, $durationMs]
    );
} catch (Exception $e) { /* ignore — table may not exist yet */ }

echo json_encode([
    'success'             => true,
    'sent'                => $totalSent,
    'failed'              => $totalFailed,
    'completed_campaigns' => $completedCount,
    'limit_hit'           => $limitHit,
    'limit_reason'        => $limitReason ?: null,
    'results'             => $campaignResults,
]);
