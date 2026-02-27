<?php
require_once __DIR__ . '/../includes/layout.php';
Auth::requireSuperAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role  = $_POST['role'] ?? 'admin';
        $pass  = trim($_POST['password'] ?? '');
        if ($name && $email && $pass) {
            try {
                Database::query(
                    "INSERT INTO users (name,email,password,role) VALUES(?,?,?,?)",
                    [$name, $email, password_hash($pass, PASSWORD_BCRYPT), $role]
                );
                flash('success', 'User created.');
            } catch (Exception $e) {
                flash('error', 'Error: ' . $e->getMessage());
            }
        } else {
            flash('error', 'All fields required.');
        }
        header('Location: ' . APP_URL . '/admin/users.php');
        exit;
    }

    if ($action === 'toggle') {
        $uid = (int)$_POST['user_id'];
        Database::query("UPDATE users SET is_active = 1 - is_active WHERE id=?", [$uid]);
        flash('success', 'User status updated.');
        header('Location: ' . APP_URL . '/admin/users.php');
        exit;
    }

    if ($action === 'change_pass') {
        $uid  = (int)$_POST['user_id'];
        $pass = trim($_POST['new_password'] ?? '');
        if (strlen($pass) >= 6) {
            Database::query("UPDATE users SET password=? WHERE id=?", [password_hash($pass, PASSWORD_BCRYPT), $uid]);
            flash('success', 'Password updated.');
        } else {
            flash('error', 'Password must be at least 6 characters.');
        }
        header('Location: ' . APP_URL . '/admin/users.php');
        exit;
    }
}

$users = Database::fetchAll("SELECT * FROM users ORDER BY created_at DESC");
?>

<h2 style="font-size:20px;margin-bottom:20px">👤 User Management</h2>

<div class="grid-2">
    <div class="gc">
        <div class="gc-title">➕ Add New User</div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div style="margin-bottom:12px">
                <input class="fi" name="name" placeholder="Full Name" style="width:100%" required>
            </div>
            <div style="margin-bottom:12px">
                <input class="fi" name="email" type="email" placeholder="Email Address" style="width:100%" required>
            </div>
            <div style="margin-bottom:12px">
                <input class="fi" name="password" type="password" placeholder="Password (min 6 chars)" style="width:100%" required>
            </div>
            <div style="margin-bottom:16px">
                <select class="fi" name="role" style="width:100%">
                    <option value="admin">Admin</option>
                    <option value="superadmin">Super Admin</option>
                </select>
            </div>
            <button type="submit" class="btn-launch">➕ Create User</button>
        </form>
    </div>

    <div class="gc">
        <div class="gc-title">🔑 Change Password</div>
        <form method="POST">
            <input type="hidden" name="action" value="change_pass">
            <div style="margin-bottom:12px">
                <select class="fi" name="user_id" style="width:100%">
                    <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?> (<?php echo $u['email']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:16px">
                <input class="fi" name="new_password" type="password" placeholder="New Password" style="width:100%" required>
            </div>
            <button type="submit" class="btn-launch">🔑 Change Password</button>
        </form>
    </div>
</div>

<div class="gc" style="margin-top:20px">
    <div class="gc-title">All Users (<?php echo count($users); ?>)</div>
    <div class="tbl-wrap">
        <table class="dt">
            <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Created</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo $u['id']; ?></td>
                <td><?php echo htmlspecialchars($u['name']); ?></td>
                <td style="font-size:12px"><?php echo htmlspecialchars($u['email']); ?></td>
                <td><?php echo $u['role'] === 'superadmin' ? '<span class="badge-sa" style="font-size:11px">SUPER</span>' : '<span class="pill p-sent">Admin</span>'; ?></td>
                <td><?php echo $u['is_active'] ? '<span class="pill p-responded">Active</span>' : '<span class="pill p-bounced">Inactive</span>'; ?></td>
                <td style="font-size:12px"><?php echo $u['last_login'] ? timeAgo($u['last_login']) : 'Never'; ?></td>
                <td style="font-size:12px"><?php echo timeAgo($u['created_at']); ?></td>
                <td>
                    <?php if ($u['id'] != Auth::user()['id']): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <button type="submit" style="background:<?php echo $u['is_active'] ? '#ef4444' : '#10b981'; ?>;color:#fff;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px">
                            <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:12px;color:#8a9ab5">Current User</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
