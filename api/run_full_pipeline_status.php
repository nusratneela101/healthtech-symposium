<?php
/**
 * Full Pipeline Status API
 *
 * Returns a JSON snapshot of the pipeline's current state:
 *   - Lock status (is another pipeline instance running?)
 *   - Last collection: timestamp, lead counts
 *   - Active campaigns and their progress
 *   - Today's sending stats (sent vs limit)
 *   - Warm-up day and today's limit
 *   - Next recommended actions
 *
 * Auth: ?api_key=<N8N_API_KEY>
 */

ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/warmup.php';

ob_clean();
header('Content-Type: application/json');

// ── Authentication ────────────────────────────────────────────────────────────
$authenticated = false;

// Allow session-based SuperAdmin access
if (!empty($_SESSION['user_id'])) {
    try {
        $user = Database::fetchOne("SELECT role FROM users WHERE id=?", [$_SESSION['user_id']]);
        if ($user && $user['role'] === 'superadmin') {
            $authenticated = true;
        }
    } catch (Exception $e) { /* ignore */ }
}

// Allow API key access
if (!$authenticated) {
    $apiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!empty($apiKey) && defined('N8N_API_KEY') && hash_equals(N8N_API_KEY, $apiKey)) {
        $authenticated = true;
    }
}

if (!$authenticated) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// ── Lock status ───────────────────────────────────────────────────────────────
$lockStatus = 'unknown';
$lockHeld   = false;
try {
    $lockRow  = Database::fetchOne("SELECT IS_FREE_LOCK('healthtech_email_sender') AS free_lock");
    $isFree   = isset($lockRow['free_lock']) && (int)$lockRow['free_lock'] === 1;
    $lockHeld = !$isFree;
    $lockStatus = $isFree ? 'idle' : 'running';
} catch (Exception $e) { /* ignore */ }

// ── Last collection ───────────────────────────────────────────────────────────
$lastCollection = null;
try {
    $lastCollection = Database::fetchOne(
        "SELECT total_fetched, total_saved, total_duplicates, total_skipped,
                status, completed_at
         FROM lead_collections
         ORDER BY id DESC LIMIT 1"
    );
} catch (Exception $e) { /* ignore */ }

// ── Active campaigns ──────────────────────────────────────────────────────────
$activeCampaigns = [];
try {
    $rows = Database::fetchAll(
        "SELECT c.id, c.name, c.status, c.total_leads, c.created_at,
                COUNT(el.id) AS emails_sent
         FROM campaigns c
         LEFT JOIN email_logs el ON el.campaign_id = c.id AND el.status = 'sent'
         WHERE c.status IN ('running', 'draft', 'scheduled')
         GROUP BY c.id
         ORDER BY c.id DESC
         LIMIT 10"
    );
    foreach ($rows as $row) {
        $activeCampaigns[] = [
            'id'          => (int)$row['id'],
            'name'        => $row['name'],
            'status'      => $row['status'],
            'total_leads' => (int)$row['total_leads'],
            'emails_sent' => (int)$row['emails_sent'],
            'created_at'  => $row['created_at'],
        ];
    }
} catch (Exception $e) { /* ignore */ }

// ── Today's sending stats ─────────────────────────────────────────────────────
$todaySent   = 0;
$todayFailed = 0;
try {
    $row = Database::fetchOne(
        "SELECT
            SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END)   AS sent,
            SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed
         FROM email_logs
         WHERE DATE(COALESCE(sent_at, created_at)) = CURDATE()"
    );
    $todaySent   = (int)($row['sent']   ?? 0);
    $todayFailed = (int)($row['failed'] ?? 0);
} catch (Exception $e) { /* ignore */ }

// ── Warm-up info ──────────────────────────────────────────────────────────────
$warmupInfo = [];
try {
    $warmupProgress = WarmupManager::getProgress();
    $warmupInfo = [
        'enabled'      => $warmupProgress['enabled'],
        'current_day'  => $warmupProgress['current_day'],
        'total_days'   => $warmupProgress['days'],
        'daily_limit'  => $warmupProgress['daily_limit'],
        'completed'    => $warmupProgress['completed'],
        'start_date'   => $warmupProgress['start_date'],
    ];
} catch (Exception $e) { /* ignore */ }

// ── Daily limit ───────────────────────────────────────────────────────────────
$dailyLimit   = (int)getSetting('email_daily_limit', '0');
$warmupLimit  = $warmupInfo['daily_limit'] ?? null;
$effectiveLimit = null;
if ($warmupLimit !== null && $warmupInfo['enabled']) {
    $effectiveLimit = $warmupLimit;
} elseif ($dailyLimit > 0) {
    $effectiveLimit = $dailyLimit;
}

// ── Last pipeline log ─────────────────────────────────────────────────────────
$lastPipelineLog = null;
try {
    $lastPipelineLog = Database::fetchOne(
        "SELECT status, message, duration_ms, last_run
         FROM cron_log WHERE job_name='full_pipeline' LIMIT 1"
    );
} catch (Exception $e) { /* ignore */ }

// ── Lead counts ───────────────────────────────────────────────────────────────
$leadsCollectedToday = 0;
$leadsNewTotal       = 0;
try {
    $row = Database::fetchOne(
        "SELECT COUNT(*) AS c FROM leads WHERE DATE(created_at) = CURDATE() AND source='Apollo Pipeline'"
    );
    $leadsCollectedToday = (int)($row['c'] ?? 0);

    $row2 = Database::fetchOne(
        "SELECT COUNT(*) AS c FROM leads WHERE status='new' AND email NOT LIKE '%@noemail.placeholder' AND email != ''"
    );
    $leadsNewTotal = (int)($row2['c'] ?? 0);
} catch (Exception $e) { /* ignore */ }

// ── Page tracking ─────────────────────────────────────────────────────────────
$lastApolloPage = (int)getSetting('last_apollo_page_fetched', '0');

// ── Next recommended actions ──────────────────────────────────────────────────
$actions = [];
$automationMode = getSetting('automation_mode', 'cron');

if ($automationMode !== 'cron') {
    $actions[] = ['priority' => 'high', 'message' => 'automation_mode is not set to "cron" — pipeline will not run'];
}
if (!getSetting('apollo_api_key', '')) {
    $actions[] = ['priority' => 'high', 'message' => 'apollo_api_key is not configured — no leads will be collected'];
}
if ($leadsNewTotal === 0 && empty($activeCampaigns)) {
    $actions[] = ['priority' => 'medium', 'message' => 'No new leads and no active campaigns — check Apollo API settings'];
}
if (!empty($activeCampaigns) && $todaySent === 0 && $effectiveLimit !== null && $effectiveLimit > 0) {
    $actions[] = ['priority' => 'low', 'message' => 'Campaign active but no emails sent today yet — wait for next cron run'];
}
if ($effectiveLimit !== null && $todaySent >= $effectiveLimit) {
    $actions[] = ['priority' => 'info', 'message' => "Daily limit reached ($todaySent / $effectiveLimit) — sending will resume tomorrow"];
}
if (empty($actions)) {
    $actions[] = ['priority' => 'info', 'message' => 'Pipeline is operating normally'];
}

// ── Assemble response ─────────────────────────────────────────────────────────
echo json_encode([
    'success'     => true,
    'generated_at' => date('Y-m-d H:i:s'),
    'pipeline' => [
        'lock_status'       => $lockStatus,
        'lock_held'         => $lockHeld,
        'last_run'          => $lastPipelineLog ? $lastPipelineLog['last_run']   : null,
        'last_status'       => $lastPipelineLog ? $lastPipelineLog['status']     : null,
        'last_message'      => $lastPipelineLog ? $lastPipelineLog['message']    : null,
        'last_duration_ms'  => $lastPipelineLog ? (int)$lastPipelineLog['duration_ms'] : null,
    ],
    'collection' => [
        'last_collection'     => $lastCollection,
        'leads_today'         => $leadsCollectedToday,
        'leads_new_total'     => $leadsNewTotal,
        'last_apollo_page'    => $lastApolloPage,
        'pagination_mode'     => getSetting('apollo_pagination_mode', 'configured'),
    ],
    'campaigns' => $activeCampaigns,
    'sending' => [
        'sent_today'      => $todaySent,
        'failed_today'    => $todayFailed,
        'daily_limit'     => $effectiveLimit,
        'remaining_today' => $effectiveLimit !== null ? max(0, $effectiveLimit - $todaySent) : null,
    ],
    'warmup' => $warmupInfo,
    'next_actions' => $actions,
]);
