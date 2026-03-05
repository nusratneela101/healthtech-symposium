<?php
require_once __DIR__ . '/../includes/layout.php';

$pageTitle = 'Follow-up Settings';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_followup'])) {
    if (!csrf_verify()) {
        flash('error', 'Invalid request. Please try again.');
        header('Location: ' . APP_URL . '/admin/followup_settings.php');
        exit;
    }
    $campId          = (int)$_POST['campaign_id'];
    $followupEnabled = isset($_POST['followup_enabled']) ? 1 : 0;
    $followupDays    = max(1, (int)($_POST['followup_days'] ?? 7));
    $followupTplId   = (int)($_POST['followup_template_id'] ?? 0) ?: null;
    $maxFollowups    = max(1, min(5, (int)($_POST['max_followups'] ?? 2)));

    Database::query(
        "UPDATE campaigns SET followup_enabled=?, followup_days=?, followup_template_id=?, max_followups=? WHERE id=?",
        [$followupEnabled, $followupDays, $followupTplId, $maxFollowups, $campId]
    );
    audit_log('followup_updated', 'campaigns', $campId);
    flash('success', 'Follow-up settings saved.');
    header('Location: ' . APP_URL . '/admin/followup_settings.php?edited=' . $campId);
    exit;
}

$templates = Database::fetchAll("SELECT id, name FROM email_templates ORDER BY is_default DESC, id DESC");
$campaigns = Database::fetchAll(
    "SELECT c.*, t.name AS tpl_name,
            (SELECT COUNT(*) FROM email_logs el WHERE el.campaign_id = c.id AND el.follow_up_sequence >= 2) AS followup_sent_count,
            (SELECT COUNT(*) FROM responses r WHERE r.campaign_id = c.id) AS response_count
     FROM campaigns c
     LEFT JOIN email_templates t ON c.template_id = t.id
     ORDER BY c.created_at DESC"
);

$editId = (int)($_GET['edit'] ?? $_GET['edited'] ?? 0);
$editCamp = $editId ? Database::fetchOne("SELECT * FROM campaigns WHERE id=?", [$editId]) : null;
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:20px">🔁 Follow-up Settings</h2>
</div>

<?php if ($editCamp): ?>
<div class="gc" style="margin-bottom:24px">
    <div class="gc-title">✏️ Edit Follow-up — <?php echo htmlspecialchars($editCamp['name']); ?></div>
    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="save_followup" value="1">
        <input type="hidden" name="campaign_id" value="<?php echo $editCamp['id']; ?>">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:16px">
            <div style="display:flex;align-items:center;gap:8px;grid-column:1/-1">
                <input type="checkbox" id="followup_enabled" name="followup_enabled" style="width:16px;height:16px"
                    <?php echo $editCamp['followup_enabled'] ? 'checked' : ''; ?>>
                <label for="followup_enabled" style="font-size:13px;color:#e2e8f0;font-weight:600">Enable Follow-up Sequence</label>
            </div>
            <div>
                <label style="font-size:12px;color:#8a9ab5;display:block;margin-bottom:4px">Follow-up after (days)</label>
                <input class="fi" type="number" name="followup_days" value="<?php echo (int)($editCamp['followup_days'] ?? 7); ?>" min="1" max="90" style="width:100%">
            </div>
            <div>
                <label style="font-size:12px;color:#8a9ab5;display:block;margin-bottom:4px">Max follow-ups</label>
                <input class="fi" type="number" name="max_followups" value="<?php echo (int)($editCamp['max_followups'] ?? 2); ?>" min="1" max="5" style="width:100%">
            </div>
            <div>
                <label style="font-size:12px;color:#8a9ab5;display:block;margin-bottom:4px">Follow-up Template</label>
                <select class="fi" name="followup_template_id" style="width:100%">
                    <option value="">— Same as main template —</option>
                    <?php foreach ($templates as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo ($editCamp['followup_template_id'] == $t['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="display:flex;gap:8px">
            <button type="submit" class="btn-launch">💾 Save Settings</button>
            <a href="<?php echo APP_URL; ?>/admin/followup_settings.php" class="btn-sec" style="text-decoration:none">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="gc">
    <div class="gc-title">📋 Campaign Follow-up Status</div>
    <div class="gc-sub">Manage follow-up sequences for each campaign</div>
    <div class="tbl-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Campaign</th>
                    <th>Status</th>
                    <th>Follow-up</th>
                    <th>After (days)</th>
                    <th>Max</th>
                    <th>Follow-ups Sent</th>
                    <th>Responses</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($campaigns as $c): ?>
            <tr>
                <td><?php echo $c['id']; ?></td>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td><?php echo pill($c['status']); ?></td>
                <td>
                    <?php if ($c['followup_enabled']): ?>
                        <span class="pill p-responded">✅ Enabled</span>
                    <?php else: ?>
                        <span class="pill p-queued">⬜ Disabled</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px"><?php echo $c['followup_enabled'] ? (int)$c['followup_days'] . 'd' : '—'; ?></td>
                <td style="font-size:13px"><?php echo $c['followup_enabled'] ? (int)$c['max_followups'] : '—'; ?></td>
                <td style="color:#8b5cf6;font-weight:600"><?php echo (int)$c['followup_sent_count']; ?></td>
                <td style="color:#10b981;font-weight:600"><?php echo (int)$c['response_count']; ?></td>
                <td>
                    <a href="?edit=<?php echo $c['id']; ?>" class="btn-sec" style="text-decoration:none;font-size:12px;padding:4px 10px">✏️ Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($campaigns)): ?>
            <tr><td colspan="9" style="text-align:center;color:#8a9ab5;padding:32px">No campaigns yet. <a href="<?php echo APP_URL; ?>/admin/auto_campaign.php" style="color:#0d6efd">Create one →</a></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
