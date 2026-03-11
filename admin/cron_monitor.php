<?php
$pageTitle = 'Cron Monitor';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireSuperAdmin();

$automationMode = getSetting('automation_mode', 'cron');

$cronJobs = [
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
        'campaign_sender' => ['label'=>'📧 Campaign Sender (every 5 min)','schedule'=>'*/5 * * * *','command'=>"curl -s \"{$baseUrl}/api/run_campaign_cron.php?api_key={$apiKey}\""]
        ,'inbox_poller'    => ['label'=>'📥 Inbox Poller (every 10 min)','schedule'=>'*/10 * * * *','command'=>"curl -s \"{$baseUrl}/api/poll_inbox.php?api_key={$apiKey}\""]
        ,'lead_collector'  => ['label'=>'👥 Lead Collector (daily 8 AM)','schedule'=>'0 8 * * *','command'=>"curl -s \"{$baseUrl}/api/collect_leads.php?api_key={$apiKey}\""]
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
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>