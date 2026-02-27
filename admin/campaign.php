<?php
require_once __DIR__ . '/../includes/layout.php';

$campaigns = Database::fetchAll(
    "SELECT c.*, t.name AS tpl_name,
            u.name AS created_by_name
     FROM campaigns c
     LEFT JOIN email_templates t ON c.template_id = t.id
     LEFT JOIN users u ON c.created_by = u.id
     ORDER BY c.created_at DESC"
);
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
                    <th>Total</th>
                    <th>Sent</th>
                    <th>Failed</th>
                    <th>Status</th>
                    <th>Mode</th>
                    <th>Created By</th>
                    <th>Created</th>
                    <th>Completed</th>
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
                <td><?php echo $c['total_leads']; ?></td>
                <td style="color:#10b981"><?php echo $c['sent_count']; ?></td>
                <td style="color:#ef4444"><?php echo $c['failed_count']; ?></td>
                <td><?php echo pill($c['status']); ?></td>
                <td><span class="pill <?php echo $c['test_mode'] ? 'p-queued' : 'p-sent'; ?>"><?php echo $c['test_mode'] ? 'Test' : 'Live'; ?></span></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($c['created_by_name'] ?? '—'); ?></td>
                <td style="font-size:12px"><?php echo timeAgo($c['created_at']); ?></td>
                <td style="font-size:12px"><?php echo $c['completed_at'] ? timeAgo($c['completed_at']) : '—'; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($campaigns)): ?>
            <tr><td colspan="13" style="text-align:center;color:#8a9ab5;padding:32px">No campaigns yet. <a href="<?php echo APP_URL; ?>/admin/auto_campaign.php" style="color:#0d6efd">Create one →</a></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
