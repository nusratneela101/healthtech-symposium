<?php
$pageTitle = 'Cron Monitor';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireSuperAdmin();

$automationMode = getSetting('automation_mode', 'cron');

// Cron job definitions
$cronJobs = [
    [
        'name'     => '📧 Campaign Sender',
        'schedule' => 'Every 5 min',
        'command'  => 'php ' . realpath(__DIR__ . '/../api/run_campaign_cron.php'),
        'cron_exp' => '*/5 * * * *',
        'key'      => 'campaign_cron',
    ],
    [
        'name'     => '📥 Inbox Poller',
        'schedule' => 'Every 10 min',
        'command'  => 'php ' . realpath(__DIR__ . '/../api/poll_inbox.php'),
        'cron_exp' => '*/10 * * * *',
        'key'      => 'inbox_poller',
    ],
    [
        'name'     => '👥 Lead Collector',
        'schedule' => 'Daily 8 AM',
        'command'  => 'php ' . realpath(__DIR__ . '/../api/collect_leads.php'),
        'cron_exp' => '0 8 * * *',
        'key'      => 'lead_collector',
    ],
    [
        'name'     => '🔁 Follow-up Sender',
        'schedule' => 'Daily 10 AM',
        'command'  => 'php ' . realpath(__DIR__ . '/../api/run_followup_cron.php'),
        'cron_exp' => '0 10 * * *',
        'key'      => 'followup_sender',
    ],
];

// Load last-run data from cron_logs table (best-effort)
$lastRuns = [];
try {
    $rows = Database::fetchAll(
        "SELECT job_key, status, message, duration_ms, ran_at
         FROM cron_logs
         WHERE id IN (
             SELECT MAX(id) FROM cron_logs GROUP BY job_key
         )"
    );
    foreach ($rows as $r) {
        $lastRuns[$r['job_key']] = $r;
    }
} catch (Exception $e) { /* table may not exist yet */ }

// Recent logs
$recentLogs = [];
try {
    $recentLogs = Database::fetchAll(
        "SELECT job_key, status, message, duration_ms, ran_at
         FROM cron_logs
         ORDER BY id DESC
         LIMIT 20"
    );
} catch (Exception $e) {}

function cronStatusBadge(array $lastRuns, string $key): string {
    if (!isset($lastRuns[$key])) {
        return '<span style="background:#1e3355;color:#8a9ab5;padding:2px 8px;border-radius:10px;font-size:11px">🟡 Never</span>';
    }
    $r = $lastRuns[$key];
    if ($r['status'] === 'ok' || $r['status'] === 'success') {
        return '<span style="background:#052e16;color:#4ade80;padding:2px 8px;border-radius:10px;font-size:11px">🟢 OK</span>';
    }
    return '<span style="background:#2d0a0a;color:#f87171;padding:2px 8px;border-radius:10px;font-size:11px">🔴 Error</span>';
}

function cronTimeAgo(?string $ts): string {
    if (!$ts) return '—';
    $diff = time() - strtotime($ts);
    if ($diff < 60)  return $diff . 's ago';
    if ($diff < 3600) return round($diff / 60) . 'm ago';
    if ($diff < 86400) return round($diff / 3600) . 'h ago';
    return round($diff / 86400) . 'd ago';
}
?>

<h2 style="font-size:20px;margin-bottom:20px">⏱️ Cron Job Monitor</h2>

<!-- ─── Automation Mode Toggle ─────────────────────────────────────────── -->
<div class="gc" style="margin-bottom:24px">
    <div class="gc-title" style="margin-bottom:16px">🤖 Automation Mode</div>
    <div style="display:flex;gap:16px;flex-wrap:wrap">

        <!-- Cron Job card -->
        <div id="card-cron"
             onclick="switchMode('cron')"
             style="flex:1;min-width:200px;cursor:pointer;border-radius:12px;padding:20px 24px;
                    border:2px solid <?php echo $automationMode==='cron' ? '#10b981' : '#1e3355'; ?>;
                    background:<?php echo $automationMode==='cron' ? '#052e16' : '#0a1628'; ?>;
                    transition:all .2s">
            <div style="font-size:28px;margin-bottom:8px">🕐</div>
            <div style="font-size:15px;font-weight:700;color:<?php echo $automationMode==='cron' ? '#4ade80' : '#8a9ab5'; ?>">
                Cron Job
            </div>
            <div style="margin-top:8px">
                <?php if ($automationMode === 'cron'): ?>
                    <span style="background:#10b981;color:#fff;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700">✅ ACTIVE</span>
                <?php else: ?>
                    <span style="background:#1e3355;color:#8a9ab5;padding:3px 10px;border-radius:10px;font-size:11px">⏸ OFF</span>
                <?php endif; ?>
            </div>
            <div style="margin-top:10px;font-size:12px;color:#8a9ab5">
                PHP cron jobs run on schedule via cPanel
            </div>
        </div>

        <!-- n8n card -->
        <div id="card-n8n"
             onclick="switchMode('n8n')"
             style="flex:1;min-width:200px;cursor:pointer;border-radius:12px;padding:20px 24px;
                    border:2px solid <?php echo $automationMode==='n8n' ? '#7c3aed' : '#1e3355'; ?>;
                    background:<?php echo $automationMode==='n8n' ? '#1e1b4b' : '#0a1628'; ?>;
                    transition:all .2s">
            <div style="font-size:28px;margin-bottom:8px">🤖</div>
            <div style="font-size:15px;font-weight:700;color:<?php echo $automationMode==='n8n' ? '#a78bfa' : '#8a9ab5'; ?>">
                n8n
            </div>
            <div style="margin-top:8px">
                <?php if ($automationMode === 'n8n'): ?>
                    <span style="background:#7c3aed;color:#fff;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700">✅ ACTIVE</span>
                <?php else: ?>
                    <span style="background:#1e3355;color:#8a9ab5;padding:3px 10px;border-radius:10px;font-size:11px">⏸ OFF</span>
                <?php endif; ?>
            </div>
            <div style="margin-top:10px;font-size:12px;color:#8a9ab5">
                n8n workflows handle all automation tasks
            </div>
        </div>

    </div>

    <div id="mode-status" style="margin-top:14px;font-size:13px;color:#8a9ab5">
        <?php if ($automationMode === 'cron'): ?>
            🕐 <strong style="color:#4ade80">Cron Job Mode</strong> is currently active.
        <?php else: ?>
            🤖 <strong style="color:#a78bfa">n8n Mode</strong> is currently active.
        <?php endif; ?>
    </div>
</div>

<?php if ($automationMode === 'n8n'): ?>
<!-- Warning: cron jobs are informational only -->
<div style="background:#451a03;border:1px solid #92400e;border-radius:8px;padding:12px 18px;margin-bottom:20px;font-size:13px;display:flex;align-items:center;gap:10px">
    <span style="font-size:18px">⚠️</span>
    <span style="color:#fef3c7">
        <strong style="color:#fcd34d">n8n Mode is active.</strong>
        The cron job status below is <em>informational only</em> — cron jobs are currently disabled.
        To re-enable, switch to <strong>Cron Job Mode</strong> above.
    </span>
</div>
<?php endif; ?>

<!-- ─── Cron Job Status ───────────────────────────────────────────────── -->
<div class="gc" style="margin-bottom:24px">
    <div class="gc-title" style="margin-bottom:14px">📊 Cron Job Status</div>
    <div class="tbl-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>Job</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th>Last Run</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cronJobs as $job): ?>
            <?php $lr = $lastRuns[$job['key']] ?? null; ?>
            <tr>
                <td style="font-weight:600"><?php echo htmlspecialchars($job['name']); ?></td>
                <td style="font-size:12px;color:#8a9ab5"><?php echo htmlspecialchars($job['schedule']); ?></td>
                <td><?php echo cronStatusBadge($lastRuns, $job['key']); ?></td>
                <td style="font-size:12px;color:#8a9ab5"><?php echo cronTimeAgo($lr['ran_at'] ?? null); ?></td>
                <td style="font-size:12px;color:#8a9ab5">
                    <?php echo isset($lr['duration_ms']) ? $lr['duration_ms'] . 'ms' : '—'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ─── Recent Logs ───────────────────────────────────────────────────── -->
<div class="gc" style="margin-bottom:24px">
    <div class="gc-title" style="margin-bottom:14px">📋 Recent Logs (last 20 runs)</div>
    <?php if (empty($recentLogs)): ?>
    <div style="color:#8a9ab5;font-size:13px;padding:16px;text-align:center">
        No cron logs yet. Logs will appear here once cron jobs run.
    </div>
    <?php else: ?>
    <div class="tbl-wrap">
        <table class="dt">
            <thead>
                <tr><th>Job</th><th>Status</th><th>Message</th><th>Duration</th><th>Time</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recentLogs as $log): ?>
            <tr>
                <td style="font-size:12px;font-weight:600"><?php echo htmlspecialchars($log['job_key']); ?></td>
                <td>
                    <?php if (in_array($log['status'], ['ok','success'])): ?>
                        <span style="background:#052e16;color:#4ade80;padding:2px 8px;border-radius:10px;font-size:11px">✅ <?php echo htmlspecialchars($log['status']); ?></span>
                    <?php else: ?>
                        <span style="background:#2d0a0a;color:#f87171;padding:2px 8px;border-radius:10px;font-size:11px">❌ <?php echo htmlspecialchars($log['status']); ?></span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#8a9ab5;max-width:300px;word-break:break-word"><?php echo htmlspecialchars($log['message'] ?? '—'); ?></td>
                <td style="font-size:12px;color:#8a9ab5"><?php echo isset($log['duration_ms']) ? $log['duration_ms'] . 'ms' : '—'; ?></td>
                <td style="font-size:12px;color:#8a9ab5"><?php echo htmlspecialchars($log['ran_at'] ?? '—'); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ─── cPanel Cron Commands ──────────────────────────────────────────── -->
<div class="gc">
    <div class="gc-title" style="margin-bottom:4px">📋 cPanel Cron Commands</div>
    <div style="font-size:12px;color:#8a9ab5;margin-bottom:16px">Copy these into cPanel → Cron Jobs</div>

    <?php foreach ($cronJobs as $job): ?>
    <div style="margin-bottom:16px">
        <div style="font-size:13px;font-weight:600;color:#e2e8f0;margin-bottom:6px"><?php echo htmlspecialchars($job['name']); ?> — <span style="color:#8a9ab5;font-weight:400"><?php echo htmlspecialchars($job['schedule']); ?></span></div>
        <div style="background:#020c1b;border:1px solid #1e3355;border-radius:6px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px">
            <code style="font-size:12px;color:#4ade80;word-break:break-all;flex:1"><?php echo htmlspecialchars($job['cron_exp'] . '  ' . $job['command']); ?></code>
            <button onclick="copyCmd(this, <?php echo json_encode($job['cron_exp'] . '  ' . $job['command'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>)"
                    style="flex-shrink:0;background:#0d6efd;border:none;color:#fff;padding:5px 12px;border-radius:6px;cursor:pointer;font-size:12px">
                📋 Copy
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
function switchMode(mode) {
    var current = <?php echo json_encode($automationMode); ?>;
    if (mode === current) return;

    var label = mode === 'n8n' ? 'n8n Mode' : 'Cron Job Mode';
    if (!confirm('Switch to ' + label + '?\n\nThe other automation system will be deactivated.')) return;

    var statusEl = document.getElementById('mode-status');
    statusEl.innerHTML = '<span style="color:#8a9ab5">⏳ Saving…</span>';

    fetch('<?php echo APP_URL; ?>/api/save_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ group: 'automation', settings: { automation_mode: mode } })
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            window.location.reload();
        } else {
            statusEl.innerHTML = '<span style="color:#ef4444">❌ ' + (d.error || 'Save failed') + '</span>';
        }
    })
    .catch(function(e) {
        statusEl.innerHTML = '<span style="color:#ef4444">❌ ' + e.message + '</span>';
    });
}

function copyCmd(btn, text) {
    navigator.clipboard.writeText(text).then(function() {
        var orig = btn.textContent;
        btn.textContent = '✅ Copied!';
        btn.style.background = '#10b981';
        setTimeout(function() {
            btn.textContent = orig;
            btn.style.background = '#0d6efd';
        }, 2000);
    }).catch(function() {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        btn.textContent = '✅ Copied!';
        setTimeout(function() { btn.textContent = '📋 Copy'; }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
