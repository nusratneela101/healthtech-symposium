<?php
/**
 * install/steps/finish.php
 * Step 8 (Final) — Finalization
 */
$data    = $_SESSION['install_data']   ?? [];
$success = $_SESSION['install_success'] ?? false;
$envErr  = $_SESSION['install_env_error'] ?? null;
unset($_SESSION['install_env_error']);

$appUrl = rtrim($data['app_url'] ?? '', '/');
?>
<div class="card-header">
    <h2>🎉 Finalization</h2>
    <p>Almost done! Review the summary and complete the installation.</p>
</div>
<div class="card-body">

<?php if ($success): ?>
    <div class="finish-hero">
        <div class="finish-checkmark">✅</div>
        <h2>Installation Complete!</h2>
        <p>Your <?php echo htmlspecialchars($data['app_name'] ?? 'Canada Fintech Symposium'); ?> platform is ready to use.</p>
    </div>

    <div class="creds-box">
        <h3>🔑 Your Admin Credentials</h3>
        <div class="cred-row">
            <span class="cred-key">Username</span>
            <span>
                <span class="cred-val" id="cred-username"><?php echo htmlspecialchars($data['username'] ?? ''); ?></span>
                <button class="cred-copy" onclick="copyToClipboard(document.getElementById('cred-username').textContent, this)">Copy</button>
            </span>
        </div>
        <div class="cred-row">
            <span class="cred-key">Email</span>
            <span>
                <span class="cred-val" id="cred-email"><?php echo htmlspecialchars($data['admin_email'] ?? ''); ?></span>
                <button class="cred-copy" onclick="copyToClipboard(document.getElementById('cred-email').textContent, this)">Copy</button>
            </span>
        </div>
        <div class="cred-row">
            <span class="cred-key">Password</span>
            <span>
                <span class="cred-val" id="cred-pw" style="font-family:monospace"><?php echo htmlspecialchars($data['password'] ?? '(as entered)'); ?></span>
                <button class="cred-copy" onclick="copyToClipboard(document.getElementById('cred-pw').textContent, this)">Copy</button>
            </span>
        </div>
        <div class="cred-row">
            <span class="cred-key">Login URL</span>
            <span>
                <a href="<?php echo htmlspecialchars($appUrl . '/login.php'); ?>" target="_blank" rel="noopener" class="cred-val">
                    <?php echo htmlspecialchars($appUrl . '/login.php'); ?>
                </a>
            </span>
        </div>
    </div>

    <div style="margin-bottom:24px">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">📋 Post-Installation Checklist</h3>
        <ul class="checklist">
            <li>Login to the admin panel</li>
            <li>Configure email templates</li>
            <li>Import your first leads</li>
            <li>Set up n8n workflows</li>
            <li>Schedule your first campaign</li>
            <?php if (empty($data['apollo_api_key'])): ?>
            <li>Add your Apollo.io API key in Settings</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="alert alert-warning">
        <span class="alert-icon">⚠️</span>
        <div>
            <strong>Security Note:</strong> For security, consider renaming or removing the
            <code>/install</code> directory after setup. The <code>install.lock</code> file
            prevents the installer from running again, but removing the directory is the safest option.
        </div>
    </div>

    <a href="<?php echo htmlspecialchars($appUrl . '/login.php'); ?>" class="btn btn-success btn-lg btn-block">
        🚀 Go to Login Page →
    </a>

<?php else: ?>

    <?php if ($envErr): ?>
    <div class="alert alert-error">
        <span class="alert-icon">❌</span>
        <div><?php echo htmlspecialchars($envErr); ?></div>
    </div>
    <?php endif; ?>

    <div class="alert alert-info">
        <span class="alert-icon">ℹ️</span>
        <div>Click <strong>Complete Installation</strong> to generate the <code>.env</code> file and lock the installer.</div>
    </div>

    <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">📝 Installation Summary</h3>
    <table style="width:100%;font-size:14px;border-collapse:collapse">
        <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:8px 4px;color:var(--muted);width:160px">Database Host</td>
            <td style="padding:8px 4px;font-weight:600"><?php echo htmlspecialchars($data['db_host'] ?? 'localhost'); ?>:<?php echo htmlspecialchars($data['db_port'] ?? '3306'); ?></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:8px 4px;color:var(--muted)">Database Name</td>
            <td style="padding:8px 4px;font-weight:600"><?php echo htmlspecialchars($data['db_name'] ?? ''); ?></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:8px 4px;color:var(--muted)">Admin User</td>
            <td style="padding:8px 4px;font-weight:600"><?php echo htmlspecialchars($data['username'] ?? ''); ?> (<?php echo htmlspecialchars($data['admin_email'] ?? ''); ?>)</td>
        </tr>
        <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:8px 4px;color:var(--muted)">Email Provider</td>
            <td style="padding:8px 4px;font-weight:600"><?php echo htmlspecialchars(ucfirst($data['email_provider'] ?? 'Not configured')); ?></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:8px 4px;color:var(--muted)">n8n Integration</td>
            <td style="padding:8px 4px;font-weight:600"><?php echo !empty($data['n8n_url']) ? htmlspecialchars($data['n8n_url']) : 'Not configured'; ?></td>
        </tr>
        <tr>
            <td style="padding:8px 4px;color:var(--muted)">Apollo.io</td>
            <td style="padding:8px 4px;font-weight:600"><?php echo !empty($data['apollo_api_key']) ? '✅ Configured' : 'Not configured'; ?></td>
        </tr>
    </table>
</div>
<div class="card-footer">
    <button type="submit" name="step_action" value="prev" class="btn btn-secondary">← Back</button>
    <button type="submit" name="step_action" value="finish" class="btn btn-success">
        🎉 Complete Installation
    </button>
</div>

<?php endif; ?>

