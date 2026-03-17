<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

$campaigns = [];
try {
    $campaigns = Database::fetchAll(
        "SELECT c.*, t.name AS tpl_name,
                u.name AS created_by_name
         FROM campaigns c
         LEFT JOIN email_templates t ON c.template_id = t.id
         LEFT JOIN users u ON c.created_by = u.id
         ORDER BY c.created_at DESC"
    );
} catch (Exception $e) {}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:20px">📧 Campaigns</h2>
    <a href="<?php echo APP_URL; ?>/admin/auto_campaign.php" class="btn-launch" style="text-decoration:none;font-size:13px">+ New Campaign</a>
</div>

<div class="gc">
    <div class="tbl-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Template</th>
                    <th>Segment</th>
                    <th>Province</th>
                    <th>Target</th>
                    <th>Total</th>
                    <th>Sent</th>
                    <th>Failed</th>
                    <th>Status</th>
                    <th>Mode</th>
                    <th>Created By</th>
                    <th>Created</th>
                    <th>Completed</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($campaigns as $c): ?>
            <tr>
                <td><?php echo $c['id']; ?></td>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($c['tpl_name'] ?? '—'); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($c['filter_segment'] ?: 'All'); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($c['filter_province'] ?: 'All'); ?></td>
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
                <td><?php echo $c['total_leads']; ?></td>
                <td style="color:#10b981"><?php echo $c['sent_count']; ?></td>
                <td style="color:#ef4444"><?php echo $c['failed_count']; ?></td>
                <td><?php echo pill($c['status']); ?></td>
                <td><span class="pill <?php echo $c['test_mode'] ? 'p-queued' : 'p-sent'; ?>"><?php echo $c['test_mode'] ? 'Test' : 'Live'; ?></span></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($c['created_by_name'] ?? '—'); ?></td>
                <td style="font-size:12px"><?php echo timeAgo($c['created_at']); ?></td>
                <td style="font-size:12px"><?php echo $c['completed_at'] ? timeAgo($c['completed_at']) : '—'; ?></td>
                <td>
                    <button onclick="deleteCampaign(<?php echo $c['id']; ?>)"
                            style="background:none;border:1px solid #ef4444;color:#ef4444;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer">
                        🗑 Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($campaigns)): ?>
            <tr><td colspan="15" style="text-align:center;color:#8a9ab5;padding:32px">No campaigns yet. <a href="<?php echo APP_URL; ?>/admin/auto_campaign.php" style="color:#0d6efd">Create one →</a></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
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
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
