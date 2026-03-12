<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::check();

// Create campaign — handle BEFORE loading layout (which outputs HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    $tplId   = (int)$_POST['template_id'];
    $name    = trim($_POST['campaign_name'] ?? 'Campaign ' . date('Y-m-d H:i'));
    $seg     = trim($_POST['filter_segment'] ?? '');
    $role    = trim($_POST['filter_role']    ?? '');
    $prov    = trim($_POST['filter_province'] ?? '');
    $key     = 'camp_' . time() . '_' . rand(1000,9999);
    $testMode = isset($_POST['test_mode']) ? 1 : 0;

    try {
        $where  = '1=1';
        $params = [];
        if ($seg)  { $where .= ' AND segment=?';  $params[] = $seg; }
        if ($role) { $where .= ' AND role LIKE ?'; $params[] = "%$role%"; }
        if ($prov) { $where .= ' AND province=?'; $params[] = $prov; }
        $where .= " AND status NOT IN ('unsubscribed','bounced','emailed')";
        $total = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE $where", $params)['c'] ?? 0);

        Database::query(
            "INSERT INTO campaigns (campaign_key,name,template_id,filter_segment,filter_role,filter_province,total_leads,status,test_mode,created_by)
             VALUES(?,?,?,?,?,?,?,'draft',?,?)",
            [$key, $name, $tplId, $seg, $role, $prov, $total, $testMode, Auth::user()['id']]
        );
        $campId = Database::lastInsertId();
        echo json_encode(['success' => true, 'campaign_id' => $campId, 'campaign_key' => $key, 'total' => $total]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

require_once __DIR__ . '/../includes/layout.php';

$templates = [];
$segments  = [];
$provinces = [];
try {
    $templates   = Database::fetchAll("SELECT id, name, subject FROM email_templates ORDER BY is_default DESC, id DESC");
    $segmentRows = Database::fetchAll("SELECT DISTINCT segment FROM leads WHERE segment IS NOT NULL AND segment != '' ORDER BY segment ASC");
    $segments    = array_merge([''], array_column($segmentRows, 'segment'));
    $provinces   = Database::fetchAll("SELECT DISTINCT province FROM leads WHERE province != '' ORDER BY province");
} catch (Exception $e) {}

$recentCampaigns = [];
try {
    $recentCampaigns = Database::fetchAll(
        "SELECT c.*, t.name AS tpl_name FROM campaigns c LEFT JOIN email_templates t ON c.template_id=t.id
         ORDER BY c.created_at DESC LIMIT 10"
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
        <div id="noProgressMsg" style="padding:24px 0;text-align:center;color:#8a9ab5;font-size:13px">No campaign running. Launch a campaign to see live progress.</div>
        <div id="logTerminal" style="background:#0a0f1a;border:1px solid #1e3355;border-radius:8px;padding:16px;height:280px;overflow-y:auto;font-family:monospace;font-size:12px;color:#4ade80"><span style="color:#8a9ab5">▶ Waiting for campaign launch…</span></div>
    </div>
</div>

<div class="gc" style="margin-top:20px">
    <div class="gc-title">📋 Recent Campaigns</div>
    <div class="tbl-wrap">
        <table class="dt">
            <thead><tr><th>ID</th><th>Name</th><th>Template</th><th>Total</th><th>Sent</th><th>Failed</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($recentCampaigns as $c): ?>
            <tr>
                <td><?php echo $c['id']; ?></td>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($c['tpl_name'] ?? '—'); ?></td>
                <td><?php echo $c['total_leads']; ?></td>
                <td><?php echo $c['sent_count']; ?></td>
                <td><?php echo $c['failed_count']; ?></td>
                <td><?php echo pill($c['status']); ?></td>
                <td style="font-size:12px"><?php echo timeAgo($c['created_at']); ?></td>
                <td>
                    <button onclick="deleteCampaign(<?php echo $c['id']; ?>)"
                            style="background:none;border:1px solid #ef4444;color:#ef4444;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer">
                        🗑 Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let running = false;
let campaignId = null;
let campaignKey = null;
let sentCount = 0;
let totalCount = 0;

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
        const json = await res.json();
        if (json.done) {
            running = false;
            log(`✅ Campaign complete! Sent: ${json.sent}, Failed: ${json.failed}`);
            document.getElementById('statusMsg').textContent = '✅ All emails sent!';
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
        setTimeout(sendNext, 800);
    } catch(e) {
        log('Network error: ' + e.message);
        setTimeout(sendNext, 2000);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
