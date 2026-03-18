<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::check();

// Create campaign — handle BEFORE loading layout (which outputs HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    $tplId      = (int)$_POST['template_id'];
    $name       = trim($_POST['campaign_name'] ?? 'Campaign ' . date('Y-m-d H:i'));
    $seg        = trim($_POST['filter_segment'] ?? '');
    $role       = trim($_POST['filter_role']    ?? '');
    $prov       = trim($_POST['filter_province'] ?? '');
    $key        = 'camp_' . time() . '_' . rand(1000,9999);
    $testMode   = isset($_POST['test_mode']) ? 1 : 0;
    $targetMode = in_array($_POST['target_mode'] ?? '', ['all', 'fixed']) ? $_POST['target_mode'] : 'all';
    $targetCount = ($targetMode === 'fixed') ? max(1, (int)($_POST['target_count'] ?? 0)) : 0;

    try {
        $where  = "status NOT IN ('unsubscribed','bounced','emailed')";
        $params = [];
        if ($seg)  { $where .= ' AND LOWER(TRIM(segment))=LOWER(TRIM(?))';  $params[] = $seg; }
        if ($role) { $where .= ' AND role LIKE ?'; $params[] = "%$role%"; }
        if ($prov) { $where .= ' AND LOWER(TRIM(province))=LOWER(TRIM(?))'; $params[] = $prov; }
        $total = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE $where", $params)['c'] ?? 0);

        Database::query(
            "INSERT INTO campaigns (campaign_key,name,template_id,filter_segment,filter_role,filter_province,total_leads,status,test_mode,created_by,target_mode,target_count)
             VALUES(?,?,?,?,?,?,?,'running',?,?,?,?)",
            [$key, $name, $tplId, $seg, $role, $prov, $total, $testMode, Auth::user()['id'], $targetMode, $targetCount]
        );
        $campId = Database::lastInsertId();
        echo json_encode(['success' => true, 'campaign_id' => $campId, 'campaign_key' => $key, 'total' => $total]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

require_once __DIR__ . '/../includes/layout.php';

$sendDelayMs = max(500, (int)(getSetting('send_delay', '5')) * 1000);

$templates = [];
$segments  = [];
$provinces = [];
try {
    $templates   = Database::fetchAll("SELECT id, name, subject FROM email_templates ORDER BY is_default DESC, id DESC");
    $segments    = array_merge([''], getSegments());
    $provinces   = Database::fetchAll("SELECT DISTINCT province FROM leads WHERE province != '' ORDER BY province");
} catch (Exception $e) {}

$recentCampaigns = [];
try {
    $recentCampaigns = Database::fetchAll(
        "SELECT c.*, t.name AS tpl_name FROM campaigns c LEFT JOIN email_templates t ON c.template_id=t.id
         ORDER BY c.created_at DESC LIMIT 10"
    );
} catch (Exception $e) {}

// Detect any currently running campaign to auto-show progress on page load
$runningCampaign = null;
try {
    $runningCampaign = Database::fetchOne(
        "SELECT c.*, t.name AS tpl_name FROM campaigns c 
         LEFT JOIN email_templates t ON c.template_id=t.id
         WHERE c.status='running' ORDER BY c.started_at DESC LIMIT 1"
    );
} catch (Exception $e) {}
?>

<h2 style="font-size:20px;margin-bottom:20px">🚀 Auto Campaign</h2>

<div style="margin-bottom:16px">
    <button type="button" onclick="resetEmailedLeads()" style="background:#f59e0b;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
        🔄 Reset Emailed Leads
    </button>
    <span style="font-size:12px;color:#8a9ab5;margin-left:10px">Reset all <em>emailed</em> leads back to <em>new</em> so they can be targeted in the next campaign.</span>
</div>

<div class="grid-2">
    <div class="gc">
        <div class="gc-title">⚙️ Campaign Setup</div>
        <div class="gc-sub">Configure and launch an email campaign</div>
        <form id="campaignForm">
            <div style="margin-bottom:14px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Campaign Name</label>
                <input class="fi" name="campaign_name" placeholder="e.g. Toronto Fintech Outreach" style="width:100%" value="Fintech Symposium — <?php echo date('M Y'); ?>">
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Email Template</label>
                <select class="fi" name="template_id" style="width:100%">
                    <?php foreach ($templates as $t): ?>
                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Filter by Segment</label>
                <select class="fi" name="filter_segment" style="width:100%">
                    <option value="">All Segments</option>
                    <?php foreach (array_filter($segments) as $s): ?>
                    <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Filter by Province</label>
                <select class="fi" name="filter_province" style="width:100%">
                    <option value="">All Provinces</option>
                    <?php foreach ($provinces as $p): ?>
                    <option value="<?php echo htmlspecialchars($p['province']); ?>"><?php echo htmlspecialchars($p['province']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Filter by Role (keyword)</label>
                <input class="fi" name="filter_role" placeholder="e.g. CTO, VP, Director" style="width:100%">
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:8px">🎯 Target Mode</label>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="radio" name="target_mode" value="all" checked onchange="toggleTargetCount(this.value)" style="width:14px;height:14px">
                        <span style="font-size:13px;color:#e2e8f0">📧 All Leads <span style="color:#8a9ab5">(send to all matching leads)</span></span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="radio" name="target_mode" value="fixed" onchange="toggleTargetCount(this.value)" style="width:14px;height:14px">
                        <span style="font-size:13px;color:#e2e8f0">🎯 Fixed Count: <input type="number" id="targetCountInput" name="target_count" min="1" value="500" aria-label="Fixed target count" style="width:80px;padding:3px 8px;background:#0a1628;border:1px solid #1e3355;border-radius:4px;color:#e2e8f0;font-size:13px;margin:0 4px"> leads</span>
                    </label>
                </div>
            </div>
            <div style="margin-bottom:20px;display:flex;align-items:center;gap:8px">
                <input type="checkbox" id="test_mode" name="test_mode" style="width:16px;height:16px">
                <label for="test_mode" style="font-size:13px;color:#8a9ab5">Test Mode (log only, don't send real emails)</label>
            </div>
            <button type="button" class="btn-launch" id="launchBtn" onclick="launchCampaign()">🚀 Launch Campaign</button>
            <button type="button" class="btn-stop" id="stopBtn" onclick="stopCampaign()" style="display:none; background:#ef4444; color:#fff; border:none; padding:10px 24px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; margin-top:8px; width:100%">🛑 Stop Campaign</button>
        </form>
    </div>

    <div class="gc">
        <div class="gc-title">📊 Campaign Progress</div>
        <div class="gc-sub">Live sending progress</div>
        <div id="progressArea" style="display:none">
            <div style="margin-bottom:8px;font-size:13px;color:#8a9ab5">Sending: <span id="sentCount">0</span> / <span id="totalCount">0</span></div>
            <div style="background:#1e3355;border-radius:8px;height:16px;overflow:hidden;margin-bottom:16px">
                <div id="progressBar" style="height:100%;background:linear-gradient(90deg,#0d6efd,#8b5cf6);border-radius:8px;transition:width .3s;width:0%"></div>
            </div>
            <div id="statusMsg" style="font-size:13px;color:#10b981;margin-bottom:12px"></div>
        </div>
        <div id="noProgressMsg" style="padding:24px 0;text-align:center;color:#8a9ab5;font-size:13px">
            No campaign running. Launch a campaign to see live progress.
            <br><small style="color:#8a9ab5">Send delay: <?php echo $sendDelayMs / 1000; ?>s per email</small>
        </div>
        <div id="logTerminal" style="background:#0a0f1a;border:1px solid #1e3355;border-radius:8px;padding:16px;height:280px;overflow-y:auto;font-family:monospace;font-size:12px;color:#4ade80"><span style="color:#8a9ab5">▶ Waiting for campaign launch…</span></div>
    </div>
</div>

<div class="gc" style="margin-top:20px">
    <div class="gc-title">📋 Recent Campaigns</div>
    <div class="tbl-wrap">
        <table class="dt">
            <thead><tr><th>ID</th><th>Name</th><th>Template</th><th>Target</th><th>Progress</th><th>Failed</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($recentCampaigns as $c): ?>
            <tr>
                <td><?php echo $c['id']; ?></td>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($c['tpl_name'] ?? '—'); ?></td>
                <td style="font-size:12px">
                    <?php
                    $mode = $c['target_mode'] ?? 'all';
                    $cnt  = (int)($c['target_count'] ?? 0);
                    if ($mode === 'fixed' && $cnt > 0) {
                        echo '<span style="color:#f59e0b">🎯 Fixed: ' . $cnt . '</span>';
                    } else {
                        echo '<span style="color:#8a9ab5">📧 All</span>';
                    }
                    ?>
                </td>
                <td style="font-size:12px">
                    <?php
                    $sent  = (int)$c['sent_count'];
                    $total = (int)$c['total_leads'];
                    $mode  = $c['target_mode'] ?? 'all';
                    $cnt   = (int)($c['target_count'] ?? 0);
                    if ($mode === 'fixed' && $cnt > 0) {
                        echo "Sent $sent / $cnt (Fixed)";
                    } else {
                        echo "Sent $sent / $total (All)";
                    }
                    ?>
                </td>
                <td><?php echo $c['failed_count']; ?></td>
                <td><?php echo pill($c['status']); ?></td>
                <td style="font-size:12px"><?php echo timeAgo($c['created_at']); ?></td>
                <td style="display:flex;gap:4px;flex-wrap:wrap">
                    <?php if (in_array($c['status'], ['draft', 'paused', 'scheduled'])): ?>
                    <button onclick="resumeCampaign(<?php echo $c['id']; ?>)"
                            style="background:none;border:1px solid #10b981;color:#10b981;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer"
                            title="Start/Resume sending">
                        ▶️ Run
                    </button>
                    <?php endif; ?>

                    <?php if ($c['status'] === 'running'): ?>
                    <button onclick="pauseCampaign(<?php echo $c['id']; ?>)"
                            style="background:none;border:1px solid #f59e0b;color:#f59e0b;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer"
                            title="Pause campaign">
                        ⏸️ Pause
                    </button>
                    <?php endif; ?>

                    <?php if (in_array($c['status'], ['running', 'paused', 'draft'])): ?>
                    <button onclick="cancelCampaign(<?php echo $c['id']; ?>)"
                            style="background:none;border:1px solid #ef4444;color:#ef4444;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer"
                            title="Cancel/Stop campaign permanently">
                        🛑 Stop
                    </button>
                    <?php endif; ?>

                    <?php if ($c['status'] !== 'running'): ?>
                    <button onclick="deleteCampaign(<?php echo $c['id']; ?>)"
                            style="background:none;border:1px solid #ef4444;color:#ef4444;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer"
                            title="Delete campaign and logs">
                        🗑 Delete
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const SEND_DELAY_MS = <?php echo $sendDelayMs; ?>;
const RETRY_DELAY_MS = 5000; // delay before retrying after a transient error
let running = false;
let campaignId = null;
let campaignKey = null;
let sentCount = 0;
let totalCount = 0;

// Poll campaign status from server every few seconds for cron-driven campaigns
async function pollCampaignStatus() {
    if (!running || !campaignId) return;
    try {
        const res = await fetch('<?php echo APP_URL; ?>/api/campaign_status.php?campaign_id=' + campaignId + '&api_key=<?php echo N8N_API_KEY; ?>');
        const json = await res.json();
        if (json.success) {
            sentCount = json.sent_count;
            const failedCount = json.failed_count;
            document.getElementById('sentCount').textContent = sentCount;
            const pct = totalCount > 0 ? Math.round(sentCount / totalCount * 100) : 0;
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('statusMsg').textContent = `Sending… ${pct}%`;

            if (json.status === 'completed' || json.status === 'paused') {
                running = false;
                document.getElementById('stopBtn').style.display = 'none';
                document.getElementById('launchBtn').disabled = false;
                const emoji = json.status === 'completed' ? '✅' : '⏸️';
                log(`${emoji} Campaign ${json.status}! Sent: ${sentCount}, Failed: ${failedCount}`);
                document.getElementById('statusMsg').textContent = `${emoji} Campaign ${json.status}!`;
                return;
            }
        }
    } catch(e) { /* ignore polling errors */ }
    setTimeout(pollCampaignStatus, 5000);
}

// ── Auto-detect running campaign on page load ────────────────────
<?php if ($runningCampaign): ?>
(function autoResume() {
    const rc = <?php echo json_encode([
        'id'           => (int)$runningCampaign['id'],
        'campaign_key' => $runningCampaign['campaign_key'],
        'sent_count'   => (int)$runningCampaign['sent_count'],
        'failed_count' => (int)$runningCampaign['failed_count'],
        'total_leads'  => (int)$runningCampaign['total_leads'],
        'target_mode'  => $runningCampaign['target_mode'] ?? 'all',
        'target_count' => (int)($runningCampaign['target_count'] ?? 0),
        'name'         => $runningCampaign['name'],
    ]); ?>;

    campaignId  = rc.id;
    campaignKey = rc.campaign_key;
    sentCount   = rc.sent_count;
    totalCount  = (rc.target_mode === 'fixed' && rc.target_count > 0) ? rc.target_count : rc.total_leads;

    // Show the progress UI
    document.getElementById('progressArea').style.display = 'block';
    document.getElementById('noProgressMsg').style.display = 'none';
    document.getElementById('sentCount').textContent = sentCount;
    document.getElementById('totalCount').textContent = totalCount;
    const pct = totalCount > 0 ? Math.round(sentCount / totalCount * 100) : 0;
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('statusMsg').textContent = `Sending… ${pct}% (resumed from server)`;
    document.getElementById('stopBtn').style.display = 'block';
    document.getElementById('launchBtn').disabled = true;

    // Show log entry (build via DOM to avoid XSS from campaign name)
    const t = document.getElementById('logTerminal');
    const logDiv = document.createElement('div');
    logDiv.style.marginBottom = '2px';
    logDiv.textContent = `${new Date().toLocaleTimeString()} — 🔄 Campaign #${rc.id} "${rc.name}" is already running. Sent ${rc.sent_count}, Failed ${rc.failed_count}. Auto-resuming live tracking…`;
    t.innerHTML = '';
    t.appendChild(logDiv);

    running = true;
    pollCampaignStatus();
})();
<?php endif; ?>

function toggleTargetCount(mode) {
    document.getElementById('targetCountInput').disabled = (mode !== 'fixed');
}

async function resetEmailedLeads() {
    if (!confirm('This will reset all "emailed" leads back to "new" so they can be targeted again. Continue?')) return;
    const res = await fetch('<?php echo APP_URL; ?>/api/reset_emailed_leads.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({api_key: '<?php echo N8N_API_KEY; ?>'})
    });
    const json = await res.json();
    if (json.success) {
        alert(`✅ Reset complete! ${json.reset_count} lead(s) reset to "new".`);
    } else {
        alert('Error: ' + (json.error || 'Could not reset leads'));
    }
}

function log(msg, cls='') {
    const t = document.getElementById('logTerminal');
    t.innerHTML += `<div style="margin-bottom:2px">${new Date().toLocaleTimeString()} — ${msg}</div>`;
    t.scrollTop = t.scrollHeight;
}

async function stopCampaign() {
    running = false;
    log('⛔ Campaign stopped by user.');
    document.getElementById('stopBtn').style.display = 'none';
    document.getElementById('launchBtn').disabled = false;
    if (campaignId) {
        await fetch('<?php echo APP_URL; ?>/api/update_campaign.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                campaign_id: campaignId,
                status: 'paused',
                api_key: '<?php echo N8N_API_KEY; ?>'
            })
        });
    }
}

async function deleteCampaign(id) {
    if (!confirm('Delete this campaign and all its email logs?')) return;
    const res = await fetch('<?php echo APP_URL; ?>/api/delete_campaign.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({campaign_id: id, api_key: '<?php echo N8N_API_KEY; ?>'})
    });
    const json = await res.json();
    if (json.success) {
        location.reload();
    } else {
        alert('Error: ' + (json.error || 'Could not delete'));
    }
}

async function resumeCampaign(id) {
    if (!confirm('Start/Resume this campaign? The cron job will begin sending emails.')) return;
    const res = await fetch('<?php echo APP_URL; ?>/api/update_campaign.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            campaign_id: id,
            status: 'running',
            api_key: '<?php echo N8N_API_KEY; ?>'
        })
    });
    const json = await res.json();
    if (json.success) {
        location.reload();
    } else {
        alert('Error: ' + (json.error || 'Could not resume campaign'));
    }
}

async function pauseCampaign(id) {
    if (!confirm('Pause this campaign? Sending will stop until you resume it.')) return;
    const res = await fetch('<?php echo APP_URL; ?>/api/update_campaign.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            campaign_id: id,
            status: 'paused',
            api_key: '<?php echo N8N_API_KEY; ?>'
        })
    });
    const json = await res.json();
    if (json.success) {
        location.reload();
    } else {
        alert('Error: ' + (json.error || 'Could not pause campaign'));
    }
}

async function cancelCampaign(id) {
    if (!confirm('⚠️ STOP this campaign permanently? It will be marked as completed and cannot be resumed.')) return;
    const res = await fetch('<?php echo APP_URL; ?>/api/update_campaign.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            campaign_id: id,
            status: 'completed',
            api_key: '<?php echo N8N_API_KEY; ?>'
        })
    });
    const json = await res.json();
    if (json.success) {
        location.reload();
    } else {
        alert('Error: ' + (json.error || 'Could not stop campaign'));
    }
}

async function launchCampaign() {
    if (running) return;
    const form = document.getElementById('campaignForm');
    const data = new FormData(form);
    data.append('create_campaign', '1');
    document.getElementById('launchBtn').disabled = true;
    document.getElementById('logTerminal').innerHTML = '';
    log('Creating campaign...');

    const res = await fetch(window.location.href, {method:'POST', body: data});
    const json = await res.json();
    if (!json.success) { log('Error creating campaign'); document.getElementById('launchBtn').disabled = false; return; }

    campaignId  = json.campaign_id;
    campaignKey = json.campaign_key;
    totalCount  = json.total;
    sentCount   = 0;
    document.getElementById('progressArea').style.display = 'block';
    document.getElementById('noProgressMsg').style.display = 'none';
    document.getElementById('totalCount').textContent = totalCount;
    log(`Campaign #${campaignId} created. ${totalCount} leads targeted.`);

    if (totalCount === 0) { log('No eligible leads found.'); document.getElementById('launchBtn').disabled = false; return; }

    running = true;
    document.getElementById('stopBtn').style.display = 'block';
    await sendNext();
}

async function sendNext() {
    if (!running) return;
    try {
        const res = await fetch('<?php echo APP_URL; ?>/api/send_one_email.php', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({campaign_id: campaignId, api_key: '<?php echo N8N_API_KEY; ?>'})
        });

        // Parse as text first so a PHP fatal error (non-JSON) is handled gracefully
        const text = await res.text();
        let json;
        try {
            json = JSON.parse(text);
        } catch(e) {
            log(`⚠️ Server returned unexpected response: ${text.substring(0, 200)}`);
            setTimeout(sendNext, RETRY_DELAY_MS);
            return;
        }

        // Hard error from the API (e.g. automation_mode disabled, unauthorized)
        if (json.success === false && json.error) {
            running = false;
            log(`⛔ Error: ${json.error}`);
            document.getElementById('statusMsg').textContent = `⛔ ${json.error}`;
            document.getElementById('stopBtn').style.display = 'none';
            document.getElementById('launchBtn').disabled = false;
            return;
        }

        // Lock conflict — another sender is running, retry after 10 s
        if (json.retry) {
            log(`⏳ ${json.reason || 'Another sender is active'}, retrying in 10s…`);
            setTimeout(sendNext, 10000);
            return;
        }

        // Daily / hourly / warm-up limit reached — pause, do NOT show "All emails sent!"
        if (json.limit_hit) {
            running = false;
            const reason = json.reason || 'Sending limit reached';
            log(`⚠️ ${reason}. Campaign paused — will resume on next cron run.`);
            document.getElementById('statusMsg').textContent = `⚠️ ${reason}`;
            document.getElementById('stopBtn').style.display = 'none';
            document.getElementById('launchBtn').disabled = false;
            return;
        }

        // True campaign completion — no more leads
        if (json.done) {
            running = false;
            log(`✅ Campaign complete! Sent: ${json.sent}, Failed: ${json.failed}`);
            document.getElementById('statusMsg').textContent = '✅ All emails sent!';
            document.getElementById('stopBtn').style.display = 'none';
            document.getElementById('launchBtn').disabled = false;
            return;
        }

        // Server says campaign is paused or cancelled — stop the browser loop immediately
        if (json.paused || json.cancelled) {
            running = false;
            const reason = json.reason || (json.paused ? 'Campaign is paused' : 'Campaign is cancelled');
            log(`⏸️ ${reason}`);
            document.getElementById('statusMsg').textContent = `⏸️ ${reason}`;
            document.getElementById('stopBtn').style.display = 'none';
            document.getElementById('launchBtn').disabled = false;
            return;
        }

        if (json.sent_to) {
            sentCount++;
            document.getElementById('sentCount').textContent = sentCount;
            const pct = totalCount > 0 ? Math.round(sentCount/totalCount*100) : 0;
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('statusMsg').textContent = `Sending... ${pct}%`;
            log(`✉️ Sent to ${json.sent_to}`);
        }
        if (json.error) log(`⚠️ ${json.error}`);
        setTimeout(sendNext, SEND_DELAY_MS);
    } catch(e) {
        log(`⚠️ Network error: ${e.message}. Retrying…`);
        setTimeout(sendNext, RETRY_DELAY_MS);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
