<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::check();

$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];

if (!empty($_GET['segment'])) {
    $segs = array_filter(array_map('trim', (array)$_GET['segment']));
    if (!empty($segs)) {
        $segPlaceholders = implode(',', array_fill(0, count($segs), '?'));
        $where .= " AND segment IN ($segPlaceholders)";
        foreach ($segs as $seg) {
            $params[] = $seg;
        }
    }
}
if (!empty($_GET['status'])) {
    $where .= ' AND status = ?';
    $params[] = $_GET['status'];
}
if (!empty($_GET['province'])) {
    $where .= ' AND province = ?';
    $params[] = $_GET['province'];
}
if (!empty($_GET['q'])) {
    $where .= ' AND (full_name LIKE ? OR email LIKE ? OR company LIKE ?)';
    $q = '%' . $_GET['q'] . '%';
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
}

// Delete action (superadmin only) — must run BEFORE layout outputs HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && Auth::isSuperAdmin()) {
    $did = (int)$_POST['delete_id'];
    Database::query("DELETE FROM leads WHERE id = ?", [$did]);
    flash('success', 'Lead deleted.');
    header('Location: ' . APP_URL . '/admin/leads.php');
    exit;
}

// CSV export (superadmin only) — must run BEFORE layout outputs HTML
if (isset($_GET['export_csv']) && Auth::isSuperAdmin()) {
    $exportLeads = Database::fetchAll(
        "SELECT * FROM leads WHERE $where ORDER BY created_at DESC",
        $params
    );
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="leads_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','First Name','Last Name','Full Name','Email','Company','Job Title','Role','Segment','Country','Province','City','Source','Status','LinkedIn','Created At']);
    foreach ($exportLeads as $l) {
        fputcsv($out, [
            $l['id'], $l['first_name'], $l['last_name'], $l['full_name'], $l['email'],
            $l['company'], $l['job_title'], $l['role'], $l['segment'],
            $l['country'], $l['province'], $l['city'], $l['source'],
            $l['status'], $l['linkedin_url'], $l['created_at']
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/layout.php';

$total = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE $where", $params)['c'] ?? 0);
$leads = Database::fetchAll(
    "SELECT id, full_name, email, company, job_title, role, segment, province, status, score, created_at FROM leads WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

$segmentRows  = Database::fetchAll("SELECT DISTINCT segment FROM leads WHERE segment IS NOT NULL AND segment != '' ORDER BY segment ASC");
$segments  = array_column($segmentRows, 'segment');
$statuses  = ['new','emailed','responded','converted','unsubscribed','bounced'];
$provinces = Database::fetchAll("SELECT DISTINCT province FROM leads WHERE province != '' ORDER BY province ASC");
$activeSegs = array_filter((array)($_GET['segment'] ?? []));
$pagination = paginate($total, $page, $perPage, APP_URL . '/admin/leads.php?' . http_build_query(array_filter([
    'status'   => $_GET['status']   ?? '',
    'province' => $_GET['province'] ?? '',
    'q'        => $_GET['q']        ?? '',
])) . (!empty($activeSegs) ? '&' . http_build_query(['segment' => $activeSegs]) : ''));
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:20px">👥 Lead Database <span style="font-size:14px;color:#8a9ab5;font-weight:400">(<?php echo number_format($total); ?> total)</span></h2>
    <?php if (Auth::isSuperAdmin()): ?>
    <div style="display:flex;gap:10px">
    <a href="<?php echo APP_URL; ?>/admin/import_leads.php" class="btn-launch" style="text-decoration:none;font-size:13px">+ Import Leads</a>
    <?php
    $exportParams = array_filter(['status' => $_GET['status'] ?? '', 'province' => $_GET['province'] ?? '', 'q' => $_GET['q'] ?? '']);
    $exportUrl = APP_URL . '/admin/leads.php?export_csv=1&' . http_build_query($exportParams);
    if (!empty($activeSegs)) $exportUrl .= '&' . http_build_query(['segment' => $activeSegs]);
    ?>
    <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn-sec" style="text-decoration:none;font-size:13px">⬇️ Export CSV</a>
    <button class="btn-sec" style="font-size:13px" onclick="autoFixSegments()" aria-label="Auto-fix lead segments">🔍 Auto-Fix Segments</button>
    </div>
    <?php endif; ?>
</div>

<div class="gc" style="margin-bottom:20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <input class="fi" name="q" placeholder="Search name, email, company…" value="<?php echo htmlspecialchars($_GET['q']??''); ?>" style="flex:1;min-width:200px">
        <select class="fi" name="segment[]" multiple aria-label="Filter by segment" aria-describedby="segment-hint" style="min-width:200px;height:auto;min-height:38px;max-height:120px">
            <?php
            $selectedSegs = isset($_GET['segment']) ? (array)$_GET['segment'] : [];
            foreach ($segments as $s):
            ?>
            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo in_array($s, $selectedSegs) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
        </select>
        <small id="segment-hint" style="color:#8a9ab5;font-size:11px;display:block;margin-top:2px">Hold Ctrl/Cmd to select multiple</small>
        <select class="fi" name="status" style="min-width:120px">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo ($_GET['status']??'')===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
        </select>
        <select class="fi" name="province" style="min-width:120px">
            <option value="">All Provinces</option>
            <?php foreach ($provinces as $p): ?>
            <option value="<?php echo $p['province']; ?>" <?php echo ($_GET['province']??'')===$p['province']?'selected':''; ?>><?php echo $p['province']; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-launch" style="white-space:nowrap">🔍 Filter</button>
        <a href="<?php echo APP_URL; ?>/admin/leads.php" class="btn-sec" style="text-decoration:none;white-space:nowrap">✕ Clear</a>
    </form>
</div>

<?php if (Auth::isSuperAdmin()): ?>
<!-- Bulk Actions Bar -->
<div id="bulk-bar" style="display:none;background:#0d1b2e;border:1px solid #1e3a5f;border-radius:10px;padding:12px 16px;margin-bottom:16px;align-items:center;gap:12px;flex-wrap:wrap">
    <span id="bulk-count" style="font-size:13px;color:#8a9ab5">0 selected</span>
    <select class="fi" id="bulk-action" style="min-width:180px">
        <option value="">— Bulk Action —</option>
        <option value="bulk_delete">🗑️ Delete Selected</option>
        <option value="bulk_status_update">🔄 Change Status</option>
        <option value="bulk_segment_update">📂 Change Segment</option>
        <option value="bulk_export">⬇️ Export Selected</option>
    </select>
    <select class="fi" id="bulk-value-status" style="min-width:140px;display:none">
        <?php foreach ($statuses as $s): ?><option value="<?php echo $s; ?>"><?php echo ucfirst($s); ?></option><?php endforeach; ?>
    </select>
    <select class="fi" id="bulk-value-segment" style="min-width:180px;display:none">
        <?php foreach ($segments as $s): ?><option value="<?php echo $s; ?>"><?php echo $s; ?></option><?php endforeach; ?>
    </select>
    <button class="btn-launch" onclick="applyBulkAction()">▶ Apply</button>
    <button class="btn-sec" onclick="clearSelection()">✕ Clear</button>
</div>
<?php endif; ?>

<div class="gc">
    <div class="tbl-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <?php if (Auth::isSuperAdmin()): ?>
                    <th style="width:36px"><input type="checkbox" id="select-all" onchange="toggleSelectAll(this)" style="cursor:pointer"></th>
                    <?php endif; ?>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Job Title</th>
                    <th>Segment</th>
                    <th>Province</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Added</th>
                    <?php if (Auth::isSuperAdmin()): ?><th>Action</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($leads as $l): ?>
            <tr id="lead-row-<?php echo $l['id']; ?>">
                <?php if (Auth::isSuperAdmin()): ?>
                <td><input type="checkbox" class="lead-cb" value="<?php echo $l['id']; ?>" onchange="updateBulkBar()" style="cursor:pointer"></td>
                <?php endif; ?>
                <td><?php echo $l['id']; ?></td>
                <td><?php echo htmlspecialchars($l['full_name']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($l['email']); ?></td>
                <td><?php echo htmlspecialchars($l['company']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($l['job_title']); ?></td>
                <td><?php echo pill($l['segment']); ?></td>
                <td><?php echo htmlspecialchars($l['province']); ?></td>
                <td><?php echo pill($l['status']); ?></td>
                <td style="font-size:12px">
                    <?php
                    $score = (int)($l['score'] ?? 0);
                    $scoreColor = $score >= 50 ? '#ef4444' : ($score >= 20 ? '#f59e0b' : '#8a9ab5');
                    echo '<span style="font-weight:600;color:'.$scoreColor.'">'.$score.'</span>';
                    ?>
                </td>
                <td style="font-size:12px"><?php echo timeAgo($l['created_at']); ?></td>
                <?php if (Auth::isSuperAdmin()): ?>
                <td>
                    <form method="POST" onsubmit="return confirm('Delete this lead?')">
                        <input type="hidden" name="delete_id" value="<?php echo $l['id']; ?>">
                        <button type="submit" style="background:#ef4444;border:none;color:#fff;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px">Delete</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($leads)): ?>
            <tr><td colspan="<?php echo Auth::isSuperAdmin() ? 12 : 10; ?>" style="text-align:center;color:#8a9ab5;padding:32px">No leads found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo $pagination; ?>
</div>

<!-- Confirmation Modal -->
<div id="bulk-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:12px;padding:24px;max-width:420px;width:90%">
        <h3 style="margin:0 0 12px;font-size:16px" id="modal-title">Confirm Action</h3>
        <p style="font-size:14px;color:#8a9ab5;margin:0 0 20px" id="modal-body">Are you sure?</p>
        <div style="display:flex;gap:10px">
            <button class="btn-launch" id="modal-confirm" onclick="confirmBulk()">✓ Confirm</button>
            <button class="btn-sec" onclick="closeBulkModal()">✕ Cancel</button>
        </div>
    </div>
</div>

<script>
var pendingBulkAction = null;
var pendingBulkIds    = [];
var pendingBulkValue  = '';

function getSelectedIds(){
    return Array.from(document.querySelectorAll('.lead-cb:checked')).map(function(cb){ return parseInt(cb.value); });
}

function toggleSelectAll(cb){
    document.querySelectorAll('.lead-cb').forEach(function(c){ c.checked = cb.checked; });
    updateBulkBar();
}

function clearSelection(){
    document.querySelectorAll('.lead-cb,#select-all').forEach(function(c){ c.checked = false; });
    updateBulkBar();
}

function updateBulkBar(){
    var ids = getSelectedIds();
    var bar = document.getElementById('bulk-bar');
    bar.style.display = ids.length > 0 ? 'flex' : 'none';
    document.getElementById('bulk-count').textContent = ids.length + ' selected';
    // Reset selects
    document.getElementById('bulk-value-status').style.display  = 'none';
    document.getElementById('bulk-value-segment').style.display = 'none';
}

document.getElementById('bulk-action').addEventListener('change', function(){
    document.getElementById('bulk-value-status').style.display  = this.value === 'bulk_status_update'  ? 'inline-block' : 'none';
    document.getElementById('bulk-value-segment').style.display = this.value === 'bulk_segment_update' ? 'inline-block' : 'none';
});

function applyBulkAction(){
    var ids    = getSelectedIds();
    var action = document.getElementById('bulk-action').value;
    if (!ids.length) { alert('No leads selected'); return; }
    if (!action)     { alert('Select a bulk action'); return; }

    if (action === 'bulk_export') {
        doBulkExport(ids);
        return;
    }

    var value = '';
    if (action === 'bulk_status_update')  value = document.getElementById('bulk-value-status').value;
    if (action === 'bulk_segment_update') value = document.getElementById('bulk-value-segment').value;

    pendingBulkAction = action;
    pendingBulkIds    = ids;
    pendingBulkValue  = value;

    var titles = { bulk_delete:'Delete Leads', bulk_status_update:'Change Status', bulk_segment_update:'Change Segment' };
    var bodies = {
        bulk_delete: 'Permanently delete ' + ids.length + ' lead(s)? This cannot be undone.',
        bulk_status_update: 'Update status of ' + ids.length + ' lead(s) to "' + value + '"?',
        bulk_segment_update: 'Update segment of ' + ids.length + ' lead(s) to "' + value + '"?',
    };
    document.getElementById('modal-title').textContent = titles[action] || 'Confirm';
    document.getElementById('modal-body').textContent  = bodies[action] || 'Are you sure?';
    document.getElementById('bulk-modal').style.display = 'flex';
}

function closeBulkModal(){
    document.getElementById('bulk-modal').style.display = 'none';
}

function confirmBulk(){
    closeBulkModal();
    var payload = { action: pendingBulkAction, ids: pendingBulkIds };
    if (pendingBulkValue) payload.value = pendingBulkValue;

    fetch('<?php echo APP_URL; ?>/api/bulk_leads.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            if (pendingBulkAction === 'bulk_delete') {
                pendingBulkIds.forEach(function(id){ var r = document.getElementById('lead-row-'+id); if(r) r.remove(); });
            }
            clearSelection();
            var msg = d.affected + ' lead(s) updated.';
            if (typeof showToast === 'function') showToast('✅ ' + msg, 'success');
            else alert(msg);
            if (pendingBulkAction === 'bulk_status_update' || pendingBulkAction === 'bulk_segment_update') {
                setTimeout(function(){ location.reload(); }, 1200);
            }
        } else {
            alert('Error: ' + (d.error || 'Unknown error'));
        }
    })
    .catch(function(e){ alert('Network error: ' + e.message); });
}

function doBulkExport(ids){
    fetch('<?php echo APP_URL; ?>/api/bulk_leads.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'bulk_export', ids: ids})
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (!d.success || !d.leads) { alert('Export failed'); return; }
        var cols = ['id','full_name','email','company','job_title','role','segment','country','province','city','source','status','linkedin_url','created_at'];
        var csv  = cols.join(',') + '\n';
        d.leads.forEach(function(l){
            csv += cols.map(function(c){ return '"' + String(l[c]||'').replace(/"/g,'""') + '"'; }).join(',') + '\n';
        });
        var blob = new Blob(['\ufeff'+csv], {type:'text/csv;charset=utf-8'});
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'leads_selected_' + Date.now() + '.csv';
        a.click();
    })
    .catch(function(e){ alert('Export error: ' + e.message); });
}

function autoFixSegments(){
    if (!confirm('Run auto-detection on all leads with segment "Other"?')) return;
    fetch('<?php echo APP_URL; ?>/api/fix_segments.php', { method: 'POST' })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            showToast('✅ Fixed ' + d.fixed + ' of ' + d.total + ' "Other" leads.', 'success');
            if (d.fixed > 0) setTimeout(function(){ location.reload(); }, 1500);
        } else {
            showToast('❌ Error: ' + (d.error || 'Unknown'), 'error');
        }
    })
    .catch(function(e){ showToast('❌ Network error: ' + e.message, 'error'); });
}

function showToast(msg, type){
    var wrap = document.getElementById('toast-wrap');
    if(!wrap) return;
    var el = document.createElement('div');
    el.className = 'toast toast-'+(type||'info');
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(function(){ el.remove(); }, 4000);
}
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
