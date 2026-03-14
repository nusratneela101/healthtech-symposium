<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#0d6efd';
        $sort  = (int)($_POST['sort_order'] ?? 0);
        if ($name) {
            try {
                Database::query(
                    "INSERT INTO segments (name, description, color, sort_order) VALUES (?,?,?,?)",
                    [$name, $desc, $color, $sort]
                );
                flash('success', "Segment '$name' created.");
            } catch (Exception $e) {
                flash('error', 'Segment name already exists.');
            }
        } else {
            flash('error', 'Segment name is required.');
        }
    } elseif ($action === 'delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $seg = Database::fetchOne("SELECT name FROM segments WHERE id=?", [$id]);
        if ($seg) {
            $count = (int)(Database::fetchOne(
                "SELECT COUNT(*) AS c FROM leads WHERE segment=?",
                [$seg['name']]
            )['c'] ?? 0);
            if ($count > 0) {
                flash('error', "Cannot delete '{$seg['name']}' — {$count} lead(s) are assigned to it. Reassign them first.");
            } else {
                Database::query("DELETE FROM segments WHERE id=?", [$id]);
                flash('success', "Segment deleted.");
            }
        }
    } elseif ($action === 'edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#0d6efd';
        $sort  = (int)($_POST['sort_order'] ?? 0);
        if ($name && $id) {
            $old = Database::fetchOne("SELECT name FROM segments WHERE id=?", [$id]);
            $pdo = Database::getConnection();
            $pdo->beginTransaction();
            try {
                if ($old && $old['name'] !== $name) {
                    Database::query("UPDATE leads SET segment=? WHERE segment=?", [$name, $old['name']]);
                }
                Database::query(
                    "UPDATE segments SET name=?, description=?, color=?, sort_order=? WHERE id=?",
                    [$name, $desc, $color, $sort, $id]
                );
                $pdo->commit();
                flash('success', "Segment updated.");
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Segment name already exists.');
            }
        } else {
            flash('error', 'Segment name is required.');
        }
    }

    header('Location: ' . APP_URL . '/admin/segments.php');
    exit;
}

$pageTitle = 'Segment Manager';
require_once __DIR__ . '/../includes/layout.php';

$segments = Database::fetchAll("
    SELECT s.*, COUNT(l.id) AS lead_count
    FROM segments s
    LEFT JOIN leads l ON l.segment = s.name
    GROUP BY s.id
    ORDER BY s.sort_order ASC, s.name ASC
");

$editId = (int)($_GET['edit'] ?? 0);
$editSeg = null;
if ($editId) {
    foreach ($segments as $seg) {
        if ((int)$seg['id'] === $editId) {
            $editSeg = $seg;
            break;
        }
    }
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:20px">📂 Segment Manager</h2>
    <button class="btn-launch" onclick="toggleAddForm()" id="add-btn">+ Add Segment</button>
</div>

<!-- Add / Edit Form -->
<div class="gc" id="segment-form" style="margin-bottom:20px;<?php echo ($editSeg || isset($_GET['show_add'])) ? '' : 'display:none'; ?>">
    <div class="gc-title" id="form-title"><?php echo $editSeg ? '✏️ Edit Segment' : '➕ Add New Segment'; ?></div>
    <form method="POST" style="margin-top:16px">
        <input type="hidden" name="action" id="form-action" value="<?php echo $editSeg ? 'edit' : 'create'; ?>">
        <?php if ($editSeg): ?>
        <input type="hidden" name="id" id="form-id" value="<?php echo (int)$editSeg['id']; ?>">
        <?php else: ?>
        <input type="hidden" name="id" id="form-id" value="">
        <?php endif; ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div>
                <label style="font-size:12px;color:#8a9ab5;display:block;margin-bottom:4px">Segment Name *</label>
                <input class="fi" name="name" id="form-name" placeholder="e.g. Healthcare Providers" required style="width:100%"
                    value="<?php echo $editSeg ? htmlspecialchars($editSeg['name']) : ''; ?>">
            </div>
            <div>
                <label style="font-size:12px;color:#8a9ab5;display:block;margin-bottom:4px">Sort Order</label>
                <input class="fi" name="sort_order" id="form-sort" type="number" placeholder="0" style="width:100%"
                    value="<?php echo $editSeg ? (int)$editSeg['sort_order'] : '0'; ?>">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr auto;gap:12px;margin-bottom:12px;align-items:end">
            <div>
                <label style="font-size:12px;color:#8a9ab5;display:block;margin-bottom:4px">Description</label>
                <input class="fi" name="description" id="form-desc" placeholder="Optional description" style="width:100%"
                    value="<?php echo $editSeg ? htmlspecialchars($editSeg['description']) : ''; ?>">
            </div>
            <div>
                <label style="font-size:12px;color:#8a9ab5;display:block;margin-bottom:4px">Color</label>
                <input type="color" name="color" id="form-color" value="<?php echo $editSeg ? htmlspecialchars($editSeg['color']) : '#0d6efd'; ?>"
                    style="width:48px;height:38px;border:1px solid #1e3a5f;border-radius:6px;background:#0d1b2e;cursor:pointer;padding:2px">
            </div>
        </div>
        <div style="display:flex;gap:10px">
            <button type="submit" class="btn-launch" id="form-submit-btn"><?php echo $editSeg ? '💾 Save Changes' : '➕ Create Segment'; ?></button>
            <button type="button" class="btn-sec" onclick="cancelForm()">Cancel</button>
        </div>
    </form>
</div>

<!-- Segments Table -->
<div class="gc">
    <div class="gc-title">All Segments <span style="font-size:13px;color:#8a9ab5;font-weight:400">(<?php echo count($segments); ?>)</span></div>
    <div class="tbl-wrap" style="margin-top:16px">
        <table class="dt">
            <thead>
                <tr>
                    <th>Color</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Leads</th>
                    <th>Sort</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($segments)): ?>
            <tr><td colspan="7" style="text-align:center;color:#8a9ab5;padding:24px">No segments found. Add your first segment above.</td></tr>
            <?php else: ?>
            <?php foreach ($segments as $seg): ?>
            <tr>
                <td>
                    <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:<?php echo htmlspecialchars($seg['color']); ?>;vertical-align:middle"></span>
                </td>
                <td style="font-weight:600"><?php echo htmlspecialchars($seg['name']); ?></td>
                <td style="color:#8a9ab5;font-size:12px"><?php echo htmlspecialchars($seg['description']); ?></td>
                <td>
                    <?php if ((int)$seg['lead_count'] > 0): ?>
                    <a href="<?php echo APP_URL; ?>/admin/leads.php?segment[]=<?php echo urlencode($seg['name']); ?>"
                       style="color:#0d6efd;text-decoration:none;font-weight:600">
                        <?php echo number_format((int)$seg['lead_count']); ?>
                    </a>
                    <?php else: ?>
                    <span style="color:#8a9ab5">0</span>
                    <?php endif; ?>
                </td>
                <td style="color:#8a9ab5"><?php echo (int)$seg['sort_order']; ?></td>
                <td style="color:#8a9ab5;font-size:12px"><?php echo date('M j, Y', strtotime($seg['created_at'])); ?></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn-sec" style="font-size:12px;padding:4px 10px"
                            onclick="editSegment(<?php echo (int)$seg['id']; ?>,<?php echo json_encode($seg['name'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>,<?php echo json_encode($seg['description'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>,<?php echo json_encode($seg['color'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>,<?php echo (int)$seg['sort_order']; ?>)">
                            ✏️ Edit
                        </button>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm(<?php echo json_encode("Delete segment '{$seg['name']}'? This cannot be undone.", JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>)">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$seg['id']; ?>">
                            <button type="submit" class="btn-sec" style="font-size:12px;padding:4px 10px;background:#ef444420;color:#ef4444;border-color:#ef444440">
                                🗑️ Delete
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleAddForm() {
    const form = document.getElementById('segment-form');
    const btn  = document.getElementById('add-btn');
    if (form.style.display === 'none') {
        form.style.display = '';
        resetForm();
        btn.textContent = '✕ Cancel';
    } else {
        form.style.display = 'none';
        btn.textContent = '+ Add Segment';
    }
}

function cancelForm() {
    document.getElementById('segment-form').style.display = 'none';
    document.getElementById('add-btn').textContent = '+ Add Segment';
    resetForm();
}

function resetForm() {
    document.getElementById('form-action').value  = 'create';
    document.getElementById('form-id').value      = '';
    document.getElementById('form-name').value    = '';
    document.getElementById('form-desc').value    = '';
    document.getElementById('form-color').value   = '#0d6efd';
    document.getElementById('form-sort').value    = '0';
    document.getElementById('form-title').textContent  = '➕ Add New Segment';
    document.getElementById('form-submit-btn').textContent = '➕ Create Segment';
}

function editSegment(id, name, desc, color, sort) {
    document.getElementById('form-action').value  = 'edit';
    document.getElementById('form-id').value      = id;
    document.getElementById('form-name').value    = name;
    document.getElementById('form-desc').value    = desc;
    document.getElementById('form-color').value   = color;
    document.getElementById('form-sort').value    = sort;
    document.getElementById('form-title').textContent  = '✏️ Edit Segment';
    document.getElementById('form-submit-btn').textContent = '💾 Save Changes';
    document.getElementById('segment-form').style.display = '';
    document.getElementById('add-btn').textContent = '✕ Cancel';
    document.getElementById('segment-form').scrollIntoView({behavior: 'smooth', block: 'start'});
}
</script>
