<?php
/**
 * install/steps/database.php
 * Step 2 — Database Configuration
 */
$saved  = $_SESSION['install_data'] ?? [];
$errors = $_SESSION['install_errors'] ?? [];
unset($_SESSION['install_errors']);
?>
<div class="card-header">
    <h2>🗄️ Database Configuration</h2>
    <p>Enter your MySQL database credentials. The installer will create the database if it doesn't exist.</p>
</div>
<div class="card-body">
    <?php if (!empty($errors['db_general'])): ?>
    <div class="alert alert-error">
        <span class="alert-icon">❌</span>
        <div><?php echo htmlspecialchars($errors['db_general']); ?></div>
    </div>
    <?php endif; ?>

    <div class="form-row">
        <div class="form-group">
            <label for="db_host">Database Host</label>
            <input class="form-control <?php echo isset($errors['db_host']) ? 'error' : ''; ?>"
                   type="text" id="db_host" name="db_host"
                   value="<?php echo htmlspecialchars($saved['db_host'] ?? 'localhost'); ?>"
                   placeholder="localhost" required>
            <?php if (isset($errors['db_host'])): ?>
            <span class="field-error"><?php echo htmlspecialchars($errors['db_host']); ?></span>
            <?php endif; ?>
        </div>
        <div class="form-group" style="max-width:140px">
            <label for="db_port">Port</label>
            <input class="form-control <?php echo isset($errors['db_port']) ? 'error' : ''; ?>"
                   type="number" id="db_port" name="db_port" min="1" max="65535"
                   value="<?php echo htmlspecialchars($saved['db_port'] ?? '3306'); ?>"
                   placeholder="3306" required>
            <?php if (isset($errors['db_port'])): ?>
            <span class="field-error"><?php echo htmlspecialchars($errors['db_port']); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-group">
        <label for="db_name">Database Name</label>
        <input class="form-control <?php echo isset($errors['db_name']) ? 'error' : ''; ?>"
               type="text" id="db_name" name="db_name"
               value="<?php echo htmlspecialchars($saved['db_name'] ?? ''); ?>"
               placeholder="fintech_db" required>
        <span class="hint">The database will be created if it does not already exist.</span>
        <?php if (isset($errors['db_name'])): ?>
        <span class="field-error"><?php echo htmlspecialchars($errors['db_name']); ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="db_user">Database Username</label>
        <input class="form-control <?php echo isset($errors['db_user']) ? 'error' : ''; ?>"
               type="text" id="db_user" name="db_user"
               value="<?php echo htmlspecialchars($saved['db_user'] ?? ''); ?>"
               placeholder="db_username" required>
        <?php if (isset($errors['db_user'])): ?>
        <span class="field-error"><?php echo htmlspecialchars($errors['db_user']); ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="db_pass">Database Password</label>
        <div class="pw-wrap">
            <input class="form-control" type="password" id="db_pass" name="db_pass"
                   value="<?php echo htmlspecialchars($saved['db_pass'] ?? ''); ?>"
                   placeholder="••••••••••">
            <button type="button" class="pw-toggle" onclick="togglePassword('db_pass')">👁️</button>
        </div>
    </div>

    <button type="button" id="btnTestDb" class="btn btn-outline btn-sm" onclick="testDbConnection()">
        🔌 Test Connection
    </button>
    <div id="dbConnResult" class="conn-result"></div>

    <div class="form-group" style="margin-top:24px">
        <label for="app_url">Application URL</label>
        <input class="form-control" type="url" id="app_url" name="app_url"
               value="<?php echo htmlspecialchars($saved['app_url'] ?? (
                   (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
                   '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
               )); ?>"
               placeholder="https://yourdomain.com" required>
        <span class="hint">The full URL of your application (no trailing slash).</span>
    </div>
</div>
<div class="card-footer">
    <button type="submit" name="step_action" value="prev" class="btn btn-secondary">← Back</button>
    <button type="submit" name="step_action" value="next" class="btn btn-primary">Save & Continue →</button>
</div>
