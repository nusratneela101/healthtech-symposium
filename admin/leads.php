<?php
require_once __DIR__ . '/../includes/layout.php';

$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];

if (!empty($_GET['segment'])) {
    $where .= ' AND segment = ?';
    $params[] = $_GET['segment'];
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

// Delete action (superadmin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && Auth::isSuperAdmin()) {
    $did = (int)$_POST['delete_id'];
    Database::query("DELETE FROM leads WHERE id = ?", [$did]);
    flash('success', 'Lead deleted.');
    header('Location: ' . APP_URL . '/admin/leads.php');
    exit;
}

$total = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE $where", $params)['c'] ?? 0);
$leads = Database::fetchAll(
    "SELECT * FROM leads WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

$segments  = ['Financial Institutions','Technology & Solution Providers','Venture Capital / Investors','FinTech Startups','Other'];
$statuses  = ['new','emailed','responded','converted','unsubscribed','bounced'];
$provinces = Database::fetchAll("SELECT DISTINCT province FROM leads WHERE province != '' ORDER BY province ASC");
$pagination = paginate($total, $page, $perPage, APP_URL . '/admin/leads.php?' . http_build_query(array_filter(['segment'=>$_GET['segment']??'','status'=>$_GET['status']??'','province'=>$_GET['province']??'','q'=>$_GET['q']??''])));
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:20px">👥 Lead Database <span style="font-size:14px;color:#8a9ab5;font-weight:400">(<?php echo number_format($total); ?> total)</span></h2>
    <a href="<?php echo APP_URL; ?>/admin/import_leads.php" class="btn-launch" style="text-decoration:none;font-size:13px">+ Import Leads</a>
</div>

<div class="gc" style="margin-bottom:20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <input class="fi" name="q" placeholder="Search name, email, company…" value="<?php echo htmlspecialchars($_GET['q']??''); ?>" style="flex:1;min-width:200px">
        <select class="fi" name="segment" style="min-width:160px">
            <option value="">All Segments</option>
            <?php foreach ($segments as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo ($_GET['segment']??'')===$s?'selected':''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
        </select>
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

<div class="gc">
    <div class="tbl-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Job Title</th>
                    <th>Segment</th>
                    <th>Province</th>
                    <th>Status</th>
                    <th>Added</th>
                    <?php if (Auth::isSuperAdmin()): ?><th>Action</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($leads as $l): ?>
            <tr>
                <td><?php echo $l['id']; ?></td>
                <td><?php echo htmlspecialchars($l['full_name']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($l['email']); ?></td>
                <td><?php echo htmlspecialchars($l['company']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($l['job_title']); ?></td>
                <td><?php echo pill($l['segment']); ?></td>
                <td><?php echo htmlspecialchars($l['province']); ?></td>
                <td><?php echo pill($l['status']); ?></td>
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
            <tr><td colspan="<?php echo Auth::isSuperAdmin() ? 10 : 9; ?>" style="text-align:center;color:#8a9ab5;padding:32px">No leads found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo $pagination; ?>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
