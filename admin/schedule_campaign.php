<?php
require_once __DIR__ . '/../includes/layout.php';

// Only super admin can schedule
if (!Auth::isSuperAdmin()) {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

// Handle schedule save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_campaign'])) {
    $campaignId = (int)$_POST['campaign_id'];
    $scheduledAt = trim($_POST['scheduled_at'] ?? '');

    if ($campaignId && $scheduledAt) {
        // Server-side validation: ensure scheduled time is not in the past
        if (strtotime($scheduledAt) <= time()) {
            setFlash('error', 'Scheduled time must be in the future.');
            header('Location: ' . APP_URL . '/admin/schedule_campaign.php');
            exit;
        }
        Database::query(
            "UPDATE campaigns SET scheduled_at=?, scheduled_by=?, status='scheduled' WHERE id=? AND status IN ('draft','scheduled')",
            [$scheduledAt, Auth::user()['id'], $campaignId]
        );
        setFlash('success', 'Campaign scheduled for ' . $scheduledAt);
    }
    header('Location: ' . APP_URL . '/admin/schedule_campaign.php');
    exit;
}

// Handle cancel schedule
if (isset($_GET['cancel']) && (int)$_GET['cancel'] > 0) {
    Database::query(
        "UPDATE campaigns SET scheduled_at=NULL, scheduled_by=NULL, status='draft' WHERE id=? AND status='scheduled'",
        [(int)$_GET['cancel']]
    );
    setFlash('success', 'Schedule cancelled.');
    header('Location: ' . APP_URL . '/admin/schedule_campaign.php');
    exit;
}

// Get draft campaigns for scheduling
$draftCampaigns = [];
try {
    $draftCampaigns = Database::fetchAll(
        "SELECT c.*, t.name AS tpl_name FROM campaigns c
         LEFT JOIN email_templates t ON c.template_id=t.id
         WHERE c.status IN ('draft') ORDER BY c.created_at DESC"
    );
} catch (Exception $e) {}

// Get all scheduled campaigns
$scheduledCampaigns = [];
try {
    $scheduledCampaigns = Database::fetchAll(
        "SELECT c.*, t.name AS tpl_name, u.name AS scheduled_by_name
         FROM campaigns c
         LEFT JOIN email_templates t ON c.template_id=t.id
         LEFT JOIN users u ON c.scheduled_by=u.id
         WHERE c.scheduled_at IS NOT NULL AND c.status IN ('scheduled','running','completed')
         ORDER BY c.scheduled_at DESC"
    );
} catch (Exception $e) {}
?>

<h2 style="font-size:20px;margin-bottom:20px">📅 Schedule Campaign</h2>

<div class="grid-2">
    <div class="gc">
        <div class="gc-title">⏰ Schedule a Campaign</div>
        <div class="gc-sub">Pick a campaign and set send date/time. n8n will automatically trigger at that time.</div>
        <?php if (empty($draftCampaigns)): ?>
        <div style="margin-top:16px;padding:16px;background:#0a1628;border:1px solid #1e3355;border-radius:8px;font-size:13px;color:#8a9ab5;text-align:center">
            No campaigns available to schedule.<br>
            <a href="<?php echo APP_URL; ?>/admin/auto_campaign.php" style="color:#0d6efd;margin-top:8px;display:inline-block">Create a campaign first →</a>
        </div>
        <?php else: ?>
        <form method="POST" style="margin-top:16px">
            <input type="hidden" name="schedule_campaign" value="1">
            <div style="margin-bottom:14px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Select Campaign</label>
                <select class="fi" name="campaign_id" style="width:100%" required>
                    <option value="">-- Choose a draft campaign --</option>
                    <?php foreach ($draftCampaigns as $c): ?>
                    <option value="<?php echo $c['id']; ?>">
                        #<?php echo $c['id']; ?> — <?php echo htmlspecialchars($c['name']); ?>
                        (<?php echo $c['total_leads']; ?> leads, Template: <?php echo htmlspecialchars($c['tpl_name'] ?? 'N/A'); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:20px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Send Date &amp; Time</label>
                <input class="fi" type="datetime-local" name="scheduled_at" style="width:100%" required
                       min="<?php echo date('Y-m-d\TH:i'); ?>">
                <div style="font-size:11px;color:#8a9ab5;margin-top:4px">
                    🕐 Server timezone: <?php echo date_default_timezone_get(); ?> — Current: <?php echo date('Y-m-d H:i'); ?>
                </div>
            </div>
            <button type="submit" class="btn-launch">📅 Schedule Campaign</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="gc">
        <div class="gc-title">📋 How It Works</div>
        <div class="gc-sub">n8n automation flow</div>
        <div style="font-size:13px;color:#8a9ab5;line-height:2;margin-top:12px">
            <div>1️⃣ Create a campaign in <a href="<?php echo APP_URL; ?>/admin/auto_campaign.php" style="color:#0d6efd">Auto Campaign</a></div>
            <div>2️⃣ Come here and set the send date/time</div>
            <div>3️⃣ n8n checks every 5 minutes for scheduled campaigns</div>
            <div>4️⃣ When it's time, n8n calls <code>send_one_email.php</code> in a loop</div>
            <div>5️⃣ Emails are sent, logged, and campaign marked complete</div>
            <div style="margin-top:12px;padding:12px;background:#0a1628;border-radius:8px;border:1px solid #1e3355">
                <strong style="color:#10b981">✅ Super Admin Controls:</strong><br>
                • You decide WHEN emails go out<br>
                • You decide WHICH campaign runs<br>
                • n8n handles the sending automatically<br>
                • Leads are collected daily at 8 AM from Apollo
            </div>
        </div>
    </div>
</div>

<div class="gc" style="margin-top:20px">
    <div class="gc-title">📅 Scheduled &amp; Completed Campaigns</div>
    <div class="tbl-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Campaign</th>
                    <th>Template</th>
                    <th>Total Leads</th>
                    <th>Scheduled For</th>
                    <th>Scheduled By</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th>Failed</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($scheduledCampaigns as $c): ?>
            <tr>
                <td><?php echo $c['id']; ?></td>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($c['tpl_name'] ?? '—'); ?></td>
                <td><?php echo $c['total_leads']; ?></td>
                <td style="font-size:12px;color:#0d6efd;font-weight:600">
                    <?php echo date('M d, Y — h:i A', strtotime($c['scheduled_at'])); ?>
                </td>
                <td style="font-size:12px"><?php echo htmlspecialchars($c['scheduled_by_name'] ?? '—'); ?></td>
                <td><?php echo pill($c['status']); ?></td>
                <td style="color:#10b981"><?php echo $c['sent_count']; ?></td>
                <td style="color:#ef4444"><?php echo $c['failed_count']; ?></td>
                <td>
                    <?php if ($c['status'] === 'scheduled'): ?>
                    <a href="?cancel=<?php echo $c['id']; ?>"
                       onclick="return confirm('Cancel this schedule?')"
                       style="color:#ef4444;font-size:12px;text-decoration:none">✕ Cancel</a>
                    <?php else: ?>
                    <span style="font-size:12px;color:#8a9ab5">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($scheduledCampaigns)): ?>
            <tr><td colspan="10" style="text-align:center;color:#8a9ab5;padding:32px">No scheduled campaigns yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
