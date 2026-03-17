<?php
$pageTitle = 'Cron Monitor';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireSuperAdmin();

$automationMode = getSetting('automation_mode', 'cron');

$cronJobs = [
    'full_pipeline'   => ['label' => '🔄 Full Pipeline',    'schedule' => 'Every 30 min', 'icon' => '🔄'],
    'campaign_sender' => ['label' => '📧 Campaign Sender',  'schedule' => 'Every 5 min',  'icon' => '📧'],
    'inbox_poller'    => ['label' => '📥 Inbox Poller',     'schedule' => 'Every 10 min', 'icon' => '📥'],
    'lead_collector'  => ['label' => '👥 Lead Collector',   'schedule' => 'Daily 8 AM',   'icon' => '👥'],
    'followup_sender' => ['label' => '🔁 Follow-up Sender', 'schedule' => 'Daily 10 AM',  'icon' => '🔁'],
];

$cronStatus = [];
foreach ($cronJobs as $jobName => $meta) {
    $cronStatus[$jobName] = ['status' => 'never', 'last_run' => null, 'duration_ms' => null, 'message' => ''];
}

try {
    $rows = Database::fetchAll(
        "SELECT job_name, status, message, duration_ms, last_run FROM cron_log ORDER BY last_run DESC"
    );
    foreach ($rows as $row) {
        if (isset($cronStatus[$row['job_name']])) {
            $cronStatus[$row['job_name']] = $row;
        }
    }
} catch (Exception $e) {}

$recentLogs = [];
try {
    $recentLogs = Database::fetchAll(
        "SELECT job_name, status, message, duration_ms, last_run FROM cron_log ORDER BY last_run DESC LIMIT 20"
    );
} catch (Exception $e) {}

// Warm-up status for the pipeline summary card
require_once __DIR__ . '/../includes/warmup.php';
$warmupProgress = WarmupManager::getProgress();

// Quick stats for the pipeline card
$todaySent         = 0;
$todayLeads        = 0;
$activeCampaigns   = 0;
try {
    $row = Database::fetchOne(
        "SELECT COUNT(*) AS c FROM email_logs
         WHERE status='sent' AND DATE(COALESCE(sent_at, created_at)) = CURDATE()"
    );
    $todaySent = (int)($row['c'] ?? 0);

    $row2 = Database::fetchOne(
        "SELECT COUNT(*) AS c FROM leads WHERE DATE(created_at) = CURDATE() AND source='Apollo Pipeline'"
    );
    $todayLeads = (int)($row2['c'] ?? 0);

    $row3 = Database::fetchOne(
        "SELECT COUNT(*) AS c FROM campaigns WHERE status IN ('running','draft','scheduled')"
    );
    $activeCampaigns = (int)($row3['c'] ?? 0);
} catch (Exception $e) {}

// Effective daily limit (warm-up takes precedence)
$effectiveDailyLimit = null;
if ($warmupProgress['enabled'] && $warmupProgress['daily_limit'] !== null) {
    $effectiveDailyLimit = $warmupProgress['daily_limit'];
} else {
    $dl = (int)getSetting('email_daily_limit', '0');
    if ($dl > 0) $effectiveDailyLimit = $dl;
}

function formatTimeAgo(?string $dateStr): string {
    if (!$dateStr) return '—';
    $diff = time() - strtotime($dateStr);
    if ($diff < 60)    return $diff . 's ago';
    if ($diff < 3600)  return (int)($diff / 60) . ' min ago';
    if ($diff < 86400) return (int)($diff / 3600) . 'h ago';
    return (int)($diff / 86400) . 'd ago';
}

function statusDot(string $status): string {
    return match($status) {
        'ok'    => '🟢 OK',
        'error' => '🔴 Error',
        'late'  => '🟡 Late',
        default => '⚪ Never',
    };
}
?>

<h2 style="font-size:20px;margin-bottom:20px">⏱️ Cron Job Monitor</h2>

<?php
$fpStatus  = $cronStatus['full_pipeline'];
$fpLastRun = $fpStatus['last_run'] ?? null;
$fpMsg     = $fpStatus['message'] ?? '';
$fpStat    = $fpStatus['status'] ?? 'never';
$fpColor   = match($fpStat) { 'ok' => '#10b981', 'error' => '#ef4444', 'skipped' => '#f59e0b', default => '#8a9ab5' };
$fpBg      = match($fpStat) { 'ok' => 'rgba(16,185,129,0.07)', 'error' => 'rgba(239,68,68,0.07)', default => 'rgba(30,58,95,0.4)' };
?>

<!-- ── Full Pipeline Summary Card ──────────────────────────────────────────── -->
<div class="gc" style="margin-bottom:24px;border:2px solid <?php echo $fpColor; ?>;background:<?php echo $fpBg; ?>">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <div style="font-size:18px;font-weight:700;color:#e2e8f0">🔄 Full Pipeline</div>
            <div style="font-size:12px;color:#8a9ab5;margin-top:2px">Lead collection → Campaign → Email sending</div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <button class="btn-sec" id="run-pipeline-btn"
                    onclick="runPipelineNow()"
                    style="padding:8px 18px;font-size:13px;font-weight:600;border-color:<?php echo $fpColor; ?>;color:<?php echo $fpColor; ?>">
                ▶ Run Now
            </button>
            <a href="<?php echo APP_URL; ?>/api/run_full_pipeline_status.php?api_key=<?php echo defined('N8N_API_KEY') ? N8N_API_KEY : ''; ?>"
               target="_blank"
               style="font-size:12px;color:#60a5fa;text-decoration:none;padding:8px 12px;border:1px solid #1e3a5f;border-radius:6px">
                📊 Status API
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-top:20px">
        <!-- Last Run -->
        <div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:12px 14px">
            <div style="font-size:11px;color:#8a9ab5;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Last Run</div>
            <div style="font-size:14px;color:#e2e8f0;font-weight:600"><?php echo formatTimeAgo($fpLastRun); ?></div>
            <div style="font-size:11px;color:#8a9ab5;margin-top:2px"><?php echo htmlspecialchars($fpLastRun ?: 'Never'); ?></div>
        </div>
        <!-- Status -->
        <div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:12px 14px">
            <div style="font-size:11px;color:#8a9ab5;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Status</div>
            <div style="font-size:14px;color:<?php echo $fpColor; ?>;font-weight:600"><?php echo statusDot($fpStat); ?></div>
            <div style="font-size:11px;color:#8a9ab5;margin-top:2px;word-break:break-word"><?php echo htmlspecialchars(substr($fpMsg, 0, 80)); ?></div>
        </div>
        <!-- Warm-up -->
        <div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:12px 14px">
            <div style="font-size:11px;color:#8a9ab5;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Warm-up</div>
            <?php if ($warmupProgress['enabled']): ?>
            <div style="font-size:14px;color:#f59e0b;font-weight:600">Day <?php echo $warmupProgress['current_day']; ?> / <?php echo $warmupProgress['days']; ?></div>
            <div style="font-size:11px;color:#8a9ab5;margin-top:2px">Today's limit: <?php echo $warmupProgress['daily_limit'] ?? '—'; ?></div>
            <?php else: ?>
            <div style="font-size:14px;color:#8a9ab5;font-weight:600">Disabled</div>
            <div style="font-size:11px;color:#8a9ab5;margin-top:2px">No warm-up limit</div>
            <?php endif; ?>
        </div>
        <!-- Leads Today -->
        <div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:12px 14px">
            <div style="font-size:11px;color:#8a9ab5;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Leads Today</div>
            <div style="font-size:20px;color:#60a5fa;font-weight:700"><?php echo number_format($todayLeads); ?></div>
            <div style="font-size:11px;color:#8a9ab5;margin-top:2px">collected via Apollo</div>
        </div>
        <!-- Emails Sent Today -->
        <div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:12px 14px">
            <div style="font-size:11px;color:#8a9ab5;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Sent Today</div>
            <div style="font-size:20px;color:#10b981;font-weight:700">
                <?php echo number_format($todaySent); ?>
                <?php if ($effectiveDailyLimit !== null): ?>
                <span style="font-size:13px;color:#8a9ab5"> / <?php echo number_format($effectiveDailyLimit); ?></span>
                <?php endif; ?>
            </div>
            <div style="font-size:11px;color:#8a9ab5;margin-top:2px">
                <?php if ($effectiveDailyLimit !== null): ?>
                    <?php echo max(0, $effectiveDailyLimit - $todaySent); ?> remaining
                <?php else: ?>
                    no limit set
                <?php endif; ?>
            </div>
        </div>
        <!-- Active Campaigns -->
        <div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:12px 14px">
            <div style="font-size:11px;color:#8a9ab5;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Active Campaigns</div>
            <div style="font-size:20px;color:<?php echo $activeCampaigns > 0 ? '#10b981' : '#8a9ab5'; ?>;font-weight:700"><?php echo $activeCampaigns; ?></div>
            <div style="font-size:11px;color:#8a9ab5;margin-top:2px">running / draft / scheduled</div>
        </div>
    </div>

    <div id="run-pipeline-result" style="margin-top:12px;font-size:13px"></div>
</div>

<div class="gc" style="margin-bottom:24px">
    <div class="gc-title">🤖 Automation Mode</div>
    <div class="gc-sub">Switch between Cron Job automation and n8n workflow automation. Only one can be active at a time.</div>
    <div style="display:flex;gap:16px;margin-top:20px;align-items:center;flex-wrap:wrap">
        <div id="mode-card-cron" onclick="setAutomationMode('cron')"
             style="flex:1;min-width:220px;cursor:pointer;border-radius:12px;padding:20px;border:2px solid <?php echo $automationMode==='cron' ? '#10b981' : '#1e3a5f'; ?>;background:<?php echo $automationMode==='cron' ? 'rgba(16,185,129,0.08)' : '#0d1b2e'; ?>;transition:all 0.2s">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-size:28px">🕐</span>
                <div>
                    <div style="font-size:15px;font-weight:700;color:#e2e8f0">Cron Job</div>
                    <div style="font-size:12px;color:#8a9ab5">PHP cPanel Cron</div>
                </div>
                <?php if($automationMode==='cron'): ?>
                <span style="margin-left:auto;background:#10b981;color:#fff;font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600">✅ ACTIVE</span>
                <?php else: ?>
                <span style="margin-left:auto;background:#1e3a5f;color:#8a9ab5;font-size:11px;padding:3px 10px;border-radius:20px">⏸ OFF</span>
                <?php endif; ?>
            </div>
        </div>
        <div style="font-size:24px;color:#8a9ab5;flex-shrink:0">⇄</div>
        <div id="mode-card-n8n" onclick="setAutomationMode('n8n')"
             style="flex:1;min-width:220px;cursor:pointer;border-radius:12px;padding:20px;border:2px solid <?php echo $automationMode==='n8n' ? '#6366f1' : '#1e3a5f'; ?>;background:<?php echo $automationMode==='n8n' ? 'rgba(99,102,241,0.08)' : '#0d1b2e'; ?>;transition:all 0.2s">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-size:28px">🤖</span>
                <div>
                    <div style="font-size:15px;font-weight:700;color:#e2e8f0">n8n Workflow</div>
                    <div style="font-size:12px;color:#8a9ab5">Cloud Automation</div>
                </div>
                <?php if($automationMode==='n8n'): ?>
                <span style="margin-left:auto;background:#6366f1;color:#fff;font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600">✅ ACTIVE</span>
                <?php else: ?>
                <span style="margin-left:auto;background:#1e3a5f;color:#8a9ab5;font-size:11px;padding:3px 10px;border-radius:20px">⏸ OFF</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div id="mode-save-result" style="margin-top:8px;font-size:13px"></div>
</div>

<div class="gc" style="margin-bottom:24px">
    <div class="gc-title">📊 Cron Job Status</div>
    <div class="gc-sub">Live status of all scheduled cron jobs</div>
    <?php if (($cronStatus['full_pipeline']['status'] ?? 'never') === 'never'): ?>
    <div style="margin-top:16px;background:rgba(245,158,11,0.12);border:1px solid #f59e0b;border-radius:8px;padding:14px 16px;display:flex;align-items:flex-start;gap:10px">
        <span style="font-size:18px;flex-shrink:0">⚠️</span>
        <div>
            <div style="font-weight:600;color:#f59e0b;margin-bottom:4px">Full Pipeline cron has never run</div>
            <div style="font-size:13px;color:#d1a827">The <strong>Full Pipeline</strong> cron job has not been set up in cPanel yet. Without it, new leads won't be collected automatically and daily warm-up sending may not fire. Copy the <em>Full Pipeline</em> command from the <strong>cPanel Cron Commands</strong> section further down this page and add it to cPanel Cron Jobs (every 30 minutes).</div>
        </div>
    </div>
    <?php endif; ?>
    <div class="tbl-wrap" style="margin-top:16px;overflow-x:auto">
        <table class="dt" style="width:100%">
            <thead><tr><th>Job</th><th>Schedule</th><th>Status</th><th>Last Run</th><th>Duration</th></tr></thead>
            <tbody>
                <?php foreach ($cronJobs as $jobName => $meta):
                    $s = $cronStatus[$jobName];
                    $status = $s['status'] ?? 'never';
                    $lastRun = $s['last_run'] ?? null;
                    $duration = isset($s['duration_ms']) && $s['duration_ms'] !== null ? $s['duration_ms'] . 'ms' : '—';
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($meta['label']); ?></td>
                    <td style="color:#8a9ab5"><?php echo htmlspecialchars($meta['schedule']); ?></td>
                    <td><?php echo statusDot($status); ?></td>
                    <td style="color:#8a9ab5"><?php echo formatTimeAgo($lastRun); ?></td>
                    <td style="color:#8a9ab5"><?php echo htmlspecialchars($duration); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="gc" style="margin-bottom:24px">
    <div class="gc-title">📋 Recent Logs</div>
    <div class="gc-sub">Last 20 cron job runs</div>
    <?php if (empty($recentLogs)): ?>
    <div style="margin-top:16px;color:#8a9ab5;font-size:13px">No logs yet. Cron jobs will appear here once they run.</div>
    <?php else: ?>
    <div class="tbl-wrap" style="margin-top:16px;overflow-x:auto">
        <table class="dt" style="width:100%">
            <thead><tr><th>Job</th><th>Status</th><th>Message</th><th>Duration</th><th>Time</th></tr></thead>
            <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['job_name'] ?? ''); ?></td>
                    <td><?php echo statusDot($log['status'] ?? 'never'); ?></td>
                    <td style="color:#8a9ab5;font-size:12px"><?php echo htmlspecialchars($log['message'] ?? ''); ?></td>
                    <td style="color:#8a9ab5"><?php echo isset($log['duration_ms']) ? htmlspecialchars($log['duration_ms']) . 'ms' : '—'; ?></td>
                    <td style="color:#8a9ab5;font-size:12px"><?php echo htmlspecialchars($log['last_run'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="gc">
    <div class="gc-title">📋 cPanel Cron Commands</div>
    <div class="gc-sub">Copy these commands into your cPanel Cron Jobs manager</div>
    <?php
    $baseUrl = APP_URL;
    $apiKey  = defined('N8N_API_KEY') ? N8N_API_KEY : '';
    $cronCommands = [
        'full_pipeline'   => ['label'=>'🔄 Full Pipeline (every 30 min)','schedule'=>'*/30 * * * *','command'=>"curl -s \"{$baseUrl}/api/run_full_pipeline_cron.php?api_key={$apiKey}\""]
        ,'campaign_sender' => ['label'=>'📧 Campaign Sender (every 5 min)','schedule'=>'*/5 * * * *','command'=>"curl -s \"{$baseUrl}/api/run_campaign_cron.php?api_key={$apiKey}\""]
        ,'inbox_poller'    => ['label'=>'📥 Inbox Poller (every 10 min)','schedule'=>'*/10 * * * *','command'=>"curl -s \"{$baseUrl}/api/poll_inbox.php?api_key={$apiKey}\""]
        ,'lead_collector'  => ['label'=>'👥 Lead Collector (daily 8 AM)','schedule'=>'0 8 * * *','command'=>"curl -s \"{$baseUrl}/api/run_lead_collector_cron.php?api_key={$apiKey}\""]
        ,'followup_sender' => ['label'=>'🔁 Follow-up Sender (daily 10 AM)','schedule'=>'0 10 * * *','command'=>"curl -s \"{$baseUrl}/api/run_followup_cron.php?api_key={$apiKey}\""]
    ];
    ?>
    <div style="display:grid;gap:16px;margin-top:16px">
        <?php foreach ($cronCommands as $id => $cmd): ?>
        <div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:16px">
            <div style="font-size:13px;font-weight:600;color:#e2e8f0;margin-bottom:8px"><?php echo htmlspecialchars($cmd['label']); ?></div>
            <div style="font-size:12px;color:#8a9ab5;margin-bottom:6px">Schedule: <code style="color:#60a5fa"><?php echo htmlspecialchars($cmd['schedule']); ?></code></div>
            <div style="display:flex;align-items:center;gap:8px">
                <code id="cmd-<?php echo $id; ?>" style="flex:1;background:#060f1a;border:1px solid #1e3a5f;border-radius:6px;padding:8px 12px;font-size:12px;color:#60a5fa;word-break:break-all"><?php echo htmlspecialchars($cmd['command']); ?></code>
                <button class="btn-sec" style="flex-shrink:0;padding:6px 12px;font-size:12px" onclick="copyCmd('cmd-<?php echo $id; ?>')">📋 Copy</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
var currentAutomationMode = <?php echo json_encode(getSetting('automation_mode','cron')); ?>;

function setAutomationMode(mode) {
    if (mode === currentAutomationMode) return;
    var label = mode === 'cron' ? '🕐 Cron Job' : '🤖 n8n';
    if (!confirm('Switch to ' + label + ' mode?\n\nThe other automation will be marked as inactive.')) return;
    fetch('<?php echo APP_URL; ?>/api/save_settings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({group: 'automation', settings: {automation_mode: mode}})
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) { currentAutomationMode = mode; window.location.reload(); }
        else { alert('❌ Failed to save: ' + (d.error || 'Unknown error')); }
    })
    .catch(function(e){ alert('❌ Network error: ' + e.message); });
}

function copyCmd(elemId) {
    var el = document.getElementById(elemId);
    if (!el) return;
    navigator.clipboard.writeText(el.textContent || el.innerText).then(function(){
        showToast('📋 Copied to clipboard', 'success');
    }).catch(function(){
        showToast('Copy failed — please copy manually', 'error');
    });
}

function runPipelineNow() {
    var btn = document.getElementById('run-pipeline-btn');
    var res = document.getElementById('run-pipeline-result');
    if (!btn) return;
    btn.disabled = true;
    btn.textContent = '⏳ Running…';
    res.textContent = '';
    res.style.color = '#8a9ab5';

    var apiKey = <?php echo json_encode(defined('N8N_API_KEY') ? N8N_API_KEY : ''); ?>;
    fetch('<?php echo APP_URL; ?>/api/run_full_pipeline_cron.php?api_key=' + encodeURIComponent(apiKey))
        .then(function(r){ return r.json(); })
        .then(function(d){
            btn.disabled = false;
            btn.textContent = '▶ Run Now';
            if (d.success) {
                var s1 = d.pipeline && d.pipeline.step1_collection;
                var s3 = d.pipeline && d.pipeline.step3_sending;
                var msg = '✅ Done';
                if (s1) msg += ' | Leads saved: ' + (s1.saved || 0);
                if (s3) msg += ' | Emails sent: ' + (s3.sent || 0);
                msg += ' (' + (d.duration || 0) + 'ms)';
                res.textContent = msg;
                res.style.color = '#10b981';
                setTimeout(function(){ window.location.reload(); }, 3000);
            } else if (d.skipped) {
                res.textContent = '⏸ Skipped — ' + (d.reason || 'another instance is running');
                res.style.color = '#f59e0b';
            } else {
                res.textContent = '❌ Error: ' + (d.error || 'Unknown error');
                res.style.color = '#ef4444';
            }
        })
        .catch(function(e){
            btn.disabled = false;
            btn.textContent = '▶ Run Now';
            res.textContent = '❌ Network error: ' + e.message;
            res.style.color = '#ef4444';
        });
}
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
