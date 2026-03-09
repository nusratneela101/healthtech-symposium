<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireSuperAdmin();

// Handle template actions BEFORE loading layout (which outputs HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id           = (int)($_POST['tpl_id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $subject      = trim($_POST['subject'] ?? '');
        $body         = trim($_POST['html_body'] ?? '');
        $signatureHtml = trim($_POST['signature_html'] ?? '');
        $isDefault    = isset($_POST['is_default']) ? 1 : 0;

        // Handle attachment upload
        $attachmentPath = null;
        if (!empty($_FILES['attachment']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/attachments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext      = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $allowed  = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
            $allowedMime = ['application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/png', 'image/jpeg'];
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mime     = $finfo->file($_FILES['attachment']['tmp_name']);
            if (in_array($ext, $allowed, true) && in_array($mime, $allowedMime, true)
                && $_FILES['attachment']['size'] <= 5 * 1024 * 1024) {
                $filename       = bin2hex(random_bytes(16)) . '.' . $ext;
                $destPath       = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destPath)) {
                    $attachmentPath = 'uploads/attachments/' . $filename;
                }
            }
        }

        // Handle header image upload
        $headerImageUrl = null;
        if (!empty($_FILES['header_image']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/template_images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext     = strtolower(pathinfo($_FILES['header_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            $allowedMime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
            $finfo   = new finfo(FILEINFO_MIME_TYPE);
            $mime    = $finfo->file($_FILES['header_image']['tmp_name']);
            if (in_array($ext, $allowed, true) && in_array($mime, $allowedMime, true)
                && $_FILES['header_image']['size'] <= 5 * 1024 * 1024) {
                $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                $destPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['header_image']['tmp_name'], $destPath)) {
                    $headerImageUrl = APP_URL . '/uploads/template_images/' . $filename;
                }
            }
        }

        if ($isDefault) {
            Database::query("UPDATE email_templates SET is_default=0");
        }
        if ($id) {
            // Preserve existing upload paths if no new file was uploaded
            $existing = Database::fetchOne("SELECT attachment_path, header_image_url FROM email_templates WHERE id=?", [$id]);
            if ($attachmentPath === null && $existing) {
                $attachmentPath = $existing['attachment_path'];
            }
            if ($headerImageUrl === null && $existing) {
                $headerImageUrl = $existing['header_image_url'];
            }
            Database::query(
                "UPDATE email_templates SET name=?,subject=?,html_body=?,signature_html=?,attachment_path=?,header_image_url=?,is_default=?,updated_at=NOW() WHERE id=?",
                [$name, $subject, $body, $signatureHtml, $attachmentPath, $headerImageUrl, $isDefault, $id]
            );
            flash('success', 'Template updated.');
        } else {
            Database::query(
                "INSERT INTO email_templates (name,subject,html_body,signature_html,attachment_path,header_image_url,is_default,created_by) VALUES(?,?,?,?,?,?,?,?)",
                [$name, $subject, $body, $signatureHtml, $attachmentPath, $headerImageUrl, $isDefault, Auth::user()['id']]
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

require_once __DIR__ . '/../includes/layout.php';

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
        <form method="POST" enctype="multipart/form-data">
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
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">✍️ Email Signature <span style="color:#8a9ab5;font-size:11px">(optional — appended to bottom of email)</span></label>
                <textarea class="fi rt" name="signature_html" style="width:100%;min-height:120px;font-family:monospace;font-size:12px;resize:vertical"><?php echo htmlspecialchars($editing['signature_html'] ?? ''); ?></textarea>
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">📎 Attachment <span style="color:#8a9ab5;font-size:11px">(optional — PDF, DOC, image — max 5MB)</span></label>
                <input type="file" name="attachment" class="fi" style="width:100%" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg">
                <?php if (!empty($editing['attachment_path'])): ?>
                <div style="font-size:12px;color:#10b981;margin-top:4px">📎 Current: <?php echo htmlspecialchars(basename($editing['attachment_path'])); ?></div>
                <?php endif; ?>
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">🖼️ Header Image <span style="color:#8a9ab5;font-size:11px">(optional — shown at top of email)</span></label>
                <input type="file" name="header_image" class="fi" style="width:100%" accept=".png,.jpg,.jpeg,.gif,.webp">
                <?php if (!empty($editing['header_image_url'])): ?>
                <div style="margin-top:6px"><img src="<?php echo htmlspecialchars($editing['header_image_url']); ?>" style="max-height:80px;border-radius:6px"></div>
                <?php endif; ?>
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
            <code style="color:#10b981">{{unsubscribe_link}}</code> — Unsubscribe URL<br>
            <code style="color:#10b981">{{signature}}</code> — Your email signature (auto-appended)
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
