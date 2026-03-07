<?php
/**
 * install/steps/admin.php
 * Step 4 — Admin User Creation
 */
$saved  = $_SESSION['install_data']   ?? [];
$errors = $_SESSION['install_errors'] ?? [];
unset($_SESSION['install_errors']);
?>
<div class="card-header">
    <h2>👤 Create Admin Account</h2>
    <p>Create your super-administrator account. You will use these credentials to log in.</p>
</div>
<div class="card-body">
    <?php if (!empty($errors['admin_general'])): ?>
    <div class="alert alert-error">
        <span class="alert-icon">❌</span>
        <div><?php echo htmlspecialchars($errors['admin_general']); ?></div>
    </div>
    <?php endif; ?>

    <div class="form-group">
        <label for="admin_name">Full Name</label>
        <input class="form-control <?php echo isset($errors['admin_name']) ? 'error' : ''; ?>"
               type="text" id="admin_name" name="admin_name"
               value="<?php echo htmlspecialchars($saved['admin_name'] ?? ''); ?>"
               placeholder="Jane Smith" required>
        <?php if (isset($errors['admin_name'])): ?>
        <span class="field-error"><?php echo htmlspecialchars($errors['admin_name']); ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="username">Username</label>
        <input class="form-control <?php echo isset($errors['username']) ? 'error' : ''; ?>"
               type="text" id="username" name="username"
               value="<?php echo htmlspecialchars($saved['username'] ?? ''); ?>"
               placeholder="admin" required minlength="3" maxlength="20"
               pattern="[a-zA-Z0-9_]+" autocomplete="username">
        <span class="hint">3–20 characters. Letters, numbers, and underscores only.</span>
        <?php if (isset($errors['username'])): ?>
        <span class="field-error"><?php echo htmlspecialchars($errors['username']); ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="admin_email">Email Address</label>
        <input class="form-control <?php echo isset($errors['admin_email']) ? 'error' : ''; ?>"
               type="email" id="admin_email" name="admin_email"
               value="<?php echo htmlspecialchars($saved['admin_email'] ?? ''); ?>"
               placeholder="admin@example.com" required autocomplete="email">
        <?php if (isset($errors['admin_email'])): ?>
        <span class="field-error"><?php echo htmlspecialchars($errors['admin_email']); ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="password">Password</label>
        <div class="pw-wrap">
            <input class="form-control <?php echo isset($errors['password']) ? 'error' : ''; ?>"
                   type="password" id="password" name="password"
                   placeholder="••••••••" required minlength="8" autocomplete="new-password">
            <button type="button" class="pw-toggle" onclick="togglePassword('password')">👁️</button>
        </div>
        <div class="pw-strength">
            <div class="pw-strength-bar"><div class="pw-strength-fill" id="pw-strength-fill"></div></div>
            <span class="pw-strength-text" id="pw-strength-text"></span>
        </div>
        <?php if (isset($errors['password'])): ?>
        <span class="field-error"><?php echo htmlspecialchars($errors['password']); ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <div class="pw-wrap">
            <input class="form-control <?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>"
                   type="password" id="confirm_password" name="confirm_password"
                   placeholder="••••••••" required minlength="8" autocomplete="new-password">
            <button type="button" class="pw-toggle" onclick="togglePassword('confirm_password')">👁️</button>
        </div>
        <?php if (isset($errors['confirm_password'])): ?>
        <span class="field-error"><?php echo htmlspecialchars($errors['confirm_password']); ?></span>
        <?php endif; ?>
    </div>

    <button type="button" class="btn btn-outline btn-sm"
            onclick="generatePassword('password','confirm_password')">
        🔑 Generate Secure Password
    </button>
</div>
<div class="card-footer">
    <button type="submit" name="step_action" value="prev" class="btn btn-secondary">← Back</button>
    <button type="submit" name="step_action" value="next" class="btn btn-primary">Create Account →</button>
</div>
