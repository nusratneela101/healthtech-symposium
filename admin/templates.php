<?php
require_once __DIR__ . '/../includes/layout.php';
Auth::requireSuperAdmin();

// Handle template actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id      = (int)($_POST['tpl_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['html_body'] ?? '');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        if ($isDefault) {
            Database::query("UPDATE email_templates SET is_default=0");
        }
        if ($id) {
            Database::query(
                "UPDATE email_templates SET name=?,subject=?,html_body=?,is_default=?,updated_at=NOW() WHERE id=?",
                [$name, $subject, $body, $isDefault, $id]
            );
            flash('success', 'Template updated.');
        } else {
            Database::query(
                "INSERT INTO email_templates (name,subject,html_body,is_default,created_by) VALUES(?,?,?,?,?)",
                [$name, $subject, $body, $isDefault, Auth::user()['id']]
            );
            flash('success', 'Template created.');
        }
        header('Location: ' . APP_URL . '/admin/templates.php');
        exit;
    }
    if ($action === 'delete') {
        $id = (int)$_POST['tpl_id'];
        Database::query("DELETE FROM email_templates WHERE id=?", [$id]);
        flash('success', 'Template deleted.');
        header('Location: ' . APP_URL . '/admin/templates.php');
        exit;
    }
}

$templates = Database::fetchAll("SELECT * FROM email_templates ORDER BY is_default DESC, id DESC");
$editing   = null;
if (isset($_GET['edit'])) {
    $editing = Database::fetchOne("SELECT * FROM email_templates WHERE id=?", [(int)$_GET['edit']]);
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:20px">✉️ Email Templates</h2>
    <a href="?new=1" class="btn-launch" style="text-decoration:none;font-size:13px">+ New Template</a>
</div>

<div class="grid-2">
    <div class="gc">
        <div class="gc-title"><?php echo $editing ? 'Edit Template' : (isset($_GET['new']) ? 'New Template' : 'Templates'); ?></div>
        <?php if ($editing || isset($_GET['new'])): ?>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="tpl_id" value="<?php echo $editing['id'] ?? 0; ?>">
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Template Name</label>
                <input class="fi" name="name" value="<?php echo htmlspecialchars($editing['name'] ?? ''); ?>" required style="width:100%">
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Email Subject</label>
                <input class="fi" name="subject" value="<?php echo htmlspecialchars($editing['subject'] ?? ''); ?>" required style="width:100%">
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">HTML Body</label>
                <textarea class="fi rt" name="html_body" style="width:100%;min-height:300px;font-family:monospace;font-size:12px;resize:vertical"><?php echo htmlspecialchars($editing['html_body'] ?? ''); ?></textarea>
            </div>
            <div style="margin-bottom:16px;display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="is_default" id="is_default" <?php echo ($editing['is_default'] ?? 0) ? 'checked' : ''; ?>>
                <label for="is_default" style="font-size:13px;color:#8a9ab5">Set as default template</label>
            </div>
            <button type="submit" class="btn-launch">💾 Save Template</button>
            <a href="<?php echo APP_URL; ?>/admin/templates.php" class="btn-sec" style="text-decoration:none;margin-left:8px">Cancel</a>
        </form>
        <?php else: ?>
        <div class="tbl-wrap">
            <table class="dt">
                <thead><tr><th>#</th><th>Name</th><th>Subject</th><th>Default</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($templates as $t): ?>
                <tr>
                    <td><?php echo $t['id']; ?></td>
                    <td><?php echo htmlspecialchars($t['name']); ?></td>
                    <td style="font-size:12px"><?php echo htmlspecialchars(substr($t['subject'], 0, 50)); ?>…</td>
                    <td><?php echo $t['is_default'] ? '<span class="pill p-sent">Default</span>' : ''; ?></td>
                    <td style="display:flex;gap:6px">
                        <a href="?edit=<?php echo $t['id']; ?>" style="background:#0d6efd;color:#fff;padding:4px 10px;border-radius:6px;text-decoration:none;font-size:12px">Edit</a>
                        <a href="?preview=<?php echo $t['id']; ?>" target="_blank" style="background:#8b5cf6;color:#fff;padding:4px 10px;border-radius:6px;text-decoration:none;font-size:12px">Preview</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete template?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="tpl_id" value="<?php echo $t['id']; ?>">
                            <button type="submit" style="background:#ef4444;color:#fff;padding:4px 10px;border-radius:6px;border:none;cursor:pointer;font-size:12px">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="gc">
        <div class="gc-title">🔖 Template Variables</div>
        <div class="gc-sub">Use these placeholders in your template</div>
        <div style="background:#0d1f38;border-radius:8px;padding:16px;font-size:13px;line-height:2">
            <code style="color:#10b981">{{first_name}}</code> — First name<br>
            <code style="color:#10b981">{{last_name}}</code> — Last name<br>
            <code style="color:#10b981">{{full_name}}</code> — Full name<br>
            <code style="color:#10b981">{{role}}</code> — Job role/title<br>
            <code style="color:#10b981">{{company}}</code> — Company name<br>
            <code style="color:#10b981">{{city}}</code> — City<br>
            <code style="color:#10b981">{{province}}</code> — Province<br>
            <code style="color:#10b981">{{email}}</code> — Email address<br>
            <code style="color:#10b981">{{unsubscribe_link}}</code> — Unsubscribe URL
        </div>
        <?php if (isset($_GET['preview'])): ?>
        <div style="margin-top:16px">
            <div class="gc-title" style="margin-bottom:8px">Preview</div>
            <?php $tpl = Database::fetchOne("SELECT html_body FROM email_templates WHERE id=?", [(int)$_GET['preview']]); ?>
            <?php if ($tpl): ?>
            <iframe srcdoc="<?php echo htmlspecialchars($tpl['html_body']); ?>" style="width:100%;height:400px;border:1px solid #1e3355;border-radius:8px;background:#fff"></iframe>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
