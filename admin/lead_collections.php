<?php
require_once __DIR__ . '/../includes/layout.php';
Auth::requireSuperAdmin();

// Handle CSV export of a single collection's leads
if (isset($_GET['export_collection'])) {
    $collId = (int)$_GET['export_collection'];
    $coll = Database::fetchOne("SELECT * FROM lead_collections WHERE id=?", [$collId]);
    if ($coll) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="collection_' . $collId . '_leads_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Lead ID','First Name','Last Name','Email','Company','Job Title','Role','Segment','Province','City','Source','Status','Action','Collected At']);
        $items = Database::fetchAll(
            "SELECT l.*, lci.action, lci.created_at AS collected_at
             FROM lead_collection_items lci
             JOIN leads l ON l.id = lci.lead_id
             WHERE lci.collection_id=? ORDER BY lci.id ASC", [$collId]
        );
        foreach ($items as $item) {
            fputcsv($out, [
                $item['id'], $item['first_name'], $item['last_name'], $item['email'],
                $item['company'], $item['job_title'], $item['role'], $item['segment'],
                $item['province'], $item['city'], $item['source'], $item['status'],
                $item['action'], $item['collected_at']
            ]);
        }
        fclose($out);
        exit;
    }
}

// Handle export ALL collection history
if (isset($_GET['export_history'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="lead_collection_history_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Collection ID','Source','Total Fetched','Total Saved','Total Skipped','Total Duplicates','Status','Started At','Completed At']);
    $allCollections = Database::fetchAll("SELECT * FROM lead_collections ORDER BY created_at DESC");
    foreach ($allCollections as $c) {
        fputcsv($out, [
            $c['id'], $c['source'], $c['total_fetched'], $c['total_saved'],
            $c['total_skipped'], $c['total_duplicates'], $c['status'],
            $c['started_at'], $c['completed_at']
        ]);
    }
    fclose($out);
    exit;
}

// Handle manual trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_collection'])) {
    $ch = curl_init(APP_URL . '/api/collect_leads.php?api_key=' . urlencode(N8N_API_KEY) . '&manual=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['leads' => [], 'source' => 'Manual']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    if ($result && ($result['success'] ?? false)) {
        flash('success', "Manual collection complete! Saved: {$result['saved']}, Skipped: {$result['skipped']}");
    } else {
        flash('error', 'Collection failed: ' . ($result['error'] ?? 'Unknown error'));
    }
    header('Location: ' . APP_URL . '/admin/lead_collections.php');
    exit;
}

// Stats
$totalCollections = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM lead_collections")['c'] ?? 0);
$totalCollected   = (int)(Database::fetchOne("SELECT COALESCE(SUM(total_saved),0) AS c FROM lead_collections")['c'] ?? 0);
$todayCollections = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM lead_collections WHERE DATE(created_at)=CURDATE()")['c'] ?? 0);
$todaySaved       = (int)(Database::fetchOne("SELECT COALESCE(SUM(total_saved),0) AS c FROM lead_collections WHERE DATE(created_at)=CURDATE()")['c'] ?? 0);
$weekCollections  = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM lead_collections WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'] ?? 0);
$weekSaved        = (int)(Database::fetchOne("SELECT COALESCE(SUM(total_saved),0) AS c FROM lead_collections WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'] ?? 0);

// Pagination
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$collections = Database::fetchAll(
    "SELECT * FROM lead_collections ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$perPage, $offset]
);
$pagination = paginate($totalCollections, $page, $perPage, APP_URL . '/admin/lead_collections.php');

// Detailed view of a single collection
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$viewCollection = null;
$viewItems = [];
if ($viewId > 0) {
    $viewCollection = Database::fetchOne("SELECT * FROM lead_collections WHERE id=?", [$viewId]);
    if ($viewCollection) {
        $viewItems = Database::fetchAll(
            "SELECT l.id, l.full_name, l.email, l.company, l.job_title, l.segment, l.province, lci.action, lci.created_at AS collected_at
             FROM lead_collection_items lci
             JOIN leads l ON l.id = lci.lead_id
             WHERE lci.collection_id=? ORDER BY lci.id ASC", [$viewId]
        );
    }
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:20px">📥 Lead Collections <span style="font-size:14px;color:#8a9ab5;font-weight:400">(<?php echo number_format($totalCollections); ?> runs)</span></h2>
    <div style="display:flex;gap:10px">
        <a href="<?php echo APP_URL; ?>/admin/lead_collections.php?export_history=1" class="btn-sec" style="text-decoration:none;font-size:13px">⬇️ Export History CSV</a>
        <form method="POST" style="margin:0">
            <button type="submit" name="trigger_collection" value="1" class="btn-launch" style="font-size:13px" onclick="return confirm('Trigger a manual collection run now?')">🔄 Trigger Manual Collection</button>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px">
    <div class="gc" style="text-align:center;padding:20px">
        <div style="font-size:28px;font-weight:700;color:#60a5fa"><?php echo number_format($totalCollections); ?></div>
        <div style="font-size:12px;color:#8a9ab5;margin-top:4px">Total Collections</div>
    </div>
    <div class="gc" style="text-align:center;padding:20px">
        <div style="font-size:28px;font-weight:700;color:#34d399"><?php echo number_format($totalCollected); ?></div>
        <div style="font-size:12px;color:#8a9ab5;margin-top:4px">Total Leads Saved</div>
    </div>
    <div class="gc" style="text-align:center;padding:20px">
        <div style="font-size:28px;font-weight:700;color:#f59e0b"><?php echo number_format($todayCollections); ?></div>
        <div style="font-size:12px;color:#8a9ab5;margin-top:4px">Today's Runs</div>
        <div style="font-size:11px;color:#60a5fa"><?php echo number_format($todaySaved); ?> saved</div>
    </div>
    <div class="gc" style="text-align:center;padding:20px">
        <div style="font-size:28px;font-weight:700;color:#a78bfa"><?php echo number_format($weekCollections); ?></div>
        <div style="font-size:12px;color:#8a9ab5;margin-top:4px">This Week's Runs</div>
        <div style="font-size:11px;color:#60a5fa"><?php echo number_format($weekSaved); ?> saved</div>
    </div>
</div>

<?php if ($viewCollection): ?>
<!-- Detailed view -->
<div class="gc" style="margin-bottom:24px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div>
            <div class="gc-title">🔍 Collection #<?php echo $viewCollection['id']; ?> — <?php echo htmlspecialchars($viewCollection['source']); ?></div>
            <div class="gc-sub">Started: <?php echo htmlspecialchars($viewCollection['started_at']); ?> | Status: <?php echo pill($viewCollection['status']); ?></div>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <a href="<?php echo APP_URL; ?>/admin/lead_collections.php?export_collection=<?php echo $viewCollection['id']; ?>" class="btn-sec" style="text-decoration:none;font-size:13px">⬇️ Export CSV</a>
            <a href="<?php echo APP_URL; ?>/admin/lead_collections.php" class="btn-sec" style="text-decoration:none;font-size:13px">✕ Close</a>
        </div>
    </div>
    <div style="display:flex;gap:20px;margin-bottom:16px;font-size:13px">
        <span>Fetched: <strong><?php echo (int)$viewCollection['total_fetched']; ?></strong></span>
        <span>Saved: <strong style="color:#34d399"><?php echo (int)$viewCollection['total_saved']; ?></strong></span>
        <span>Skipped: <strong style="color:#f59e0b"><?php echo (int)$viewCollection['total_skipped']; ?></strong></span>
        <span>Duplicates: <strong style="color:#a78bfa"><?php echo (int)$viewCollection['total_duplicates']; ?></strong></span>
    </div>
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
                    <th>Action</th>
                    <th>Collected At</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($viewItems as $item): ?>
            <tr>
                <td><?php echo (int)$item['id']; ?></td>
                <td><?php echo htmlspecialchars($item['full_name']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($item['email']); ?></td>
                <td><?php echo htmlspecialchars($item['company']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($item['job_title']); ?></td>
                <td><?php echo pill($item['segment']); ?></td>
                <td><?php echo htmlspecialchars($item['province']); ?></td>
                <td><?php echo pill($item['action']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($item['collected_at']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($viewItems)): ?>
            <tr><td colspan="9" style="text-align:center;color:#8a9ab5;padding:32px">No leads in this collection.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Collection History Table -->
<div class="gc">
    <div class="gc-title">📋 Collection History</div>
    <div class="tbl-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Source</th>
                    <th>Fetched</th>
                    <th>Saved</th>
                    <th>Skipped</th>
                    <th>Duplicates</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Completed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($collections as $c): ?>
            <tr>
                <td><?php echo (int)$c['id']; ?></td>
                <td><?php echo htmlspecialchars($c['source']); ?></td>
                <td><?php echo (int)$c['total_fetched']; ?></td>
                <td style="color:#34d399;font-weight:600"><?php echo (int)$c['total_saved']; ?></td>
                <td style="color:#f59e0b"><?php echo (int)$c['total_skipped']; ?></td>
                <td style="color:#a78bfa"><?php echo (int)$c['total_duplicates']; ?></td>
                <td><?php echo pill($c['status']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($c['started_at'] ?? ''); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($c['completed_at'] ?? '—'); ?></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <a href="<?php echo APP_URL; ?>/admin/lead_collections.php?view=<?php echo (int)$c['id']; ?>" class="btn-sec" style="text-decoration:none;font-size:12px;padding:4px 10px">👁 View</a>
                        <a href="<?php echo APP_URL; ?>/admin/lead_collections.php?export_collection=<?php echo (int)$c['id']; ?>" class="btn-sec" style="text-decoration:none;font-size:12px;padding:4px 10px">⬇️ CSV</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($collections)): ?>
            <tr><td colspan="10" style="text-align:center;color:#8a9ab5;padding:32px">No collection runs yet. Use the manual trigger or configure n8n to start collecting.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo $pagination; ?>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
