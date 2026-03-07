<?php
/**
 * install/steps/email.php
 * Step 5 — Email Provider Setup (optional)
 */
$saved    = $_SESSION['install_data']   ?? [];
$errors   = $_SESSION['install_errors'] ?? [];
$selected = $saved['email_provider'] ?? 'brevo';
unset($_SESSION['install_errors']);
?>
<div class="card-header">
    <h2>📧 Email Provider <span style="font-size:13px;font-weight:500;color:var(--muted)">(Optional)</span></h2>
    <p>Configure how the system sends emails. You can skip this and configure it later in Settings.</p>
</div>
<div class="card-body">
    <?php if (!empty($errors['email_general'])): ?>
    <div class="alert alert-error">
        <span class="alert-icon">❌</span>
        <div><?php echo htmlspecialchars($errors['email_general']); ?></div>
    </div>
    <?php endif; ?>

    <div class="form-group">
        <label>Choose Email Provider</label>
        <div class="provider-grid">
            <div class="provider-card <?php echo $selected==='brevo'?'selected':''; ?>"
                 data-provider="brevo" onclick="selectProvider('brevo')">
                <input type="radio" name="email_provider" value="brevo" <?php echo $selected==='brevo'?'checked':''; ?>>
                <div class="provider-card-icon">📨</div>
                <div class="provider-card-title">Brevo</div>
                <div class="provider-card-desc">Recommended — API-based sending</div>
            </div>
            <div class="provider-card <?php echo $selected==='ms365'?'selected':''; ?>"
                 data-provider="ms365" onclick="selectProvider('ms365')">
                <input type="radio" name="email_provider" value="ms365" <?php echo $selected==='ms365'?'checked':''; ?>>
                <div class="provider-card-icon">🏢</div>
                <div class="provider-card-title">Microsoft 365</div>
                <div class="provider-card-desc">OAuth2 + SMTP via Outlook</div>
            </div>
            <div class="provider-card <?php echo $selected==='smtp'?'selected':''; ?>"
                 data-provider="smtp" onclick="selectProvider('smtp')">
                <input type="radio" name="email_provider" value="smtp" <?php echo $selected==='smtp'?'checked':''; ?>>
                <div class="provider-card-icon">🖥️</div>
                <div class="provider-card-title">SMTP</div>
                <div class="provider-card-desc">Any SMTP server</div>
            </div>
            <div class="provider-card <?php echo $selected==='skip'?'selected':''; ?>"
                 data-provider="skip" onclick="selectProvider('skip')">
                <input type="radio" name="email_provider" value="skip" <?php echo $selected==='skip'?'checked':''; ?>>
                <div class="provider-card-icon">⏭️</div>
                <div class="provider-card-title">Skip for Now</div>
                <div class="provider-card-desc">Configure email later in Settings</div>
            </div>
        </div>
    </div>

    <!-- Brevo fields -->
    <div class="provider-fields <?php echo $selected==='brevo'?'show':''; ?>" data-for="brevo">
        <div class="form-group">
            <label for="brevo_api_key">Brevo API Key</label>
            <input class="form-control" type="text" id="brevo_api_key" name="brevo_api_key"
                   value="<?php echo htmlspecialchars($saved['brevo_api_key'] ?? ''); ?>"
                   placeholder="xkeysib-…">
            <span class="hint">Find this in your <a href="https://app.brevo.com/settings/keys/api" target="_blank" rel="noopener">Brevo account → API Keys</a>.</span>
        </div>
    </div>

    <!-- MS365 fields -->
    <div class="provider-fields <?php echo $selected==='ms365'?'show':''; ?>" data-for="ms365">
        <div class="form-group">
            <label for="ms_client_id">Azure Client ID</label>
            <input class="form-control" type="text" id="ms_client_id" name="ms_client_id"
                   value="<?php echo htmlspecialchars($saved['ms_client_id'] ?? ''); ?>"
                   placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
        </div>
        <div class="form-group">
            <label for="ms_client_secret">Azure Client Secret</label>
            <input class="form-control" type="password" id="ms_client_secret" name="ms_client_secret"
                   value="<?php echo htmlspecialchars($saved['ms_client_secret'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="ms_tenant_id">Tenant ID</label>
            <input class="form-control" type="text" id="ms_tenant_id" name="ms_tenant_id"
                   value="<?php echo htmlspecialchars($saved['ms_tenant_id'] ?? 'common'); ?>"
                   placeholder="common">
        </div>
        <div class="form-group">
            <label for="ms_smtp_user">SMTP Username (Email Address)</label>
            <input class="form-control" type="email" id="ms_smtp_user" name="ms_smtp_user"
                   value="<?php echo htmlspecialchars($saved['ms_smtp_user'] ?? ''); ?>"
                   placeholder="you@yourdomain.com">
        </div>
        <div class="form-group">
            <label for="ms_smtp_pass">SMTP Password / App Password</label>
            <div class="pw-wrap">
                <input class="form-control" type="password" id="ms_smtp_pass" name="ms_smtp_pass"
                       value="<?php echo htmlspecialchars($saved['ms_smtp_pass'] ?? ''); ?>">
                <button type="button" class="pw-toggle" onclick="togglePassword('ms_smtp_pass')">👁️</button>
            </div>
        </div>
    </div>

    <!-- SMTP fields -->
    <div class="provider-fields <?php echo $selected==='smtp'?'show':''; ?>" data-for="smtp">
        <div class="form-row">
            <div class="form-group">
                <label for="smtp_host">SMTP Host</label>
                <input class="form-control" type="text" id="smtp_host" name="smtp_host"
                       value="<?php echo htmlspecialchars($saved['smtp_host'] ?? ''); ?>"
                       placeholder="smtp.example.com">
            </div>
            <div class="form-group" style="max-width:120px">
                <label for="smtp_port">Port</label>
                <input class="form-control" type="number" id="smtp_port" name="smtp_port" min="1" max="65535"
                       value="<?php echo htmlspecialchars($saved['smtp_port'] ?? '587'); ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="smtp_secure">Encryption</label>
            <select class="form-control" id="smtp_secure" name="smtp_secure">
                <option value="tls"  <?php echo ($saved['smtp_secure']??'tls')==='tls'  ? 'selected' : ''; ?>>TLS (recommended)</option>
                <option value="ssl"  <?php echo ($saved['smtp_secure']??'')==='ssl'  ? 'selected' : ''; ?>>SSL</option>
                <option value="none" <?php echo ($saved['smtp_secure']??'')==='none' ? 'selected' : ''; ?>>None</option>
            </select>
        </div>
        <div class="form-group">
            <label for="smtp_user">SMTP Username</label>
            <input class="form-control" type="text" id="smtp_user" name="smtp_user"
                   value="<?php echo htmlspecialchars($saved['smtp_user'] ?? ''); ?>"
                   placeholder="you@example.com">
        </div>
        <div class="form-group">
            <label for="smtp_pass">SMTP Password</label>
            <div class="pw-wrap">
                <input class="form-control" type="password" id="smtp_pass" name="smtp_pass"
                       value="<?php echo htmlspecialchars($saved['smtp_pass'] ?? ''); ?>">
                <button type="button" class="pw-toggle" onclick="togglePassword('smtp_pass')">👁️</button>
            </div>
        </div>
        <div class="form-group">
            <label for="smtp_from_email">From Email Address</label>
            <input class="form-control" type="email" id="smtp_from_email" name="smtp_from_email"
                   value="<?php echo htmlspecialchars($saved['smtp_from_email'] ?? ''); ?>"
                   placeholder="no-reply@example.com">
        </div>
    </div>

    <!-- Test button (hidden for skip) -->
    <div id="emailTestWrap" style="<?php echo $selected==='skip'?'display:none':''; ?>margin-top:8px">
        <button type="button" id="btnTestEmail" class="btn btn-outline btn-sm" onclick="testEmailConn()">
            📬 Test Connection
        </button>
        <div id="emailConnResult" class="conn-result"></div>
    </div>
</div>
<div class="card-footer">
    <button type="submit" name="step_action" value="prev" class="btn btn-secondary">← Back</button>
    <div style="display:flex;gap:10px;align-items:center">
        <button type="submit" name="step_action" value="skip" class="skip-link">Skip →</button>
        <button type="submit" name="step_action" value="next" class="btn btn-primary">Save & Continue →</button>
    </div>
</div>

<script>
// Show/hide test button based on provider selection
document.querySelectorAll('[name="email_provider"]').forEach(function(r){
    r.addEventListener('change', function(){
        var wrap = document.getElementById('emailTestWrap');
        if (wrap) wrap.style.display = this.value === 'skip' ? 'none' : '';
    });
});
</script>
