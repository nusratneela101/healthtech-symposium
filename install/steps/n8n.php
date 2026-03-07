<?php
/**
 * install/steps/n8n.php
 * Step 6 — n8n Integration (optional)
 */
$saved  = $_SESSION['install_data']   ?? [];
$errors = $_SESSION['install_errors'] ?? [];
unset($_SESSION['install_errors']);
?>
<div class="card-header">
    <h2>🔗 n8n Integration <span style="font-size:13px;font-weight:500;color:var(--muted)">(Optional)</span></h2>
    <p>Connect your n8n automation instance to enable AI-powered outreach workflows.</p>
</div>
<div class="card-body">
    <?php if (!empty($errors['n8n_general'])): ?>
    <div class="alert alert-error">
        <span class="alert-icon">❌</span>
        <div><?php echo htmlspecialchars($errors['n8n_general']); ?></div>
    </div>
    <?php endif; ?>

    <div class="alert alert-info">
        <span class="alert-icon">ℹ️</span>
        <div>
            <strong>What is n8n?</strong><br>
            n8n is a workflow automation tool used to orchestrate email campaigns, AI responses, and lead follow-ups.
            You can self-host n8n or use <a href="https://n8n.io" target="_blank" rel="noopener">n8n.io cloud</a>.
        </div>
    </div>

    <div class="form-group">
        <label for="n8n_url">n8n Instance URL</label>
        <input class="form-control <?php echo isset($errors['n8n_url']) ? 'error' : ''; ?>"
               type="url" id="n8n_url" name="n8n_url"
               value="<?php echo htmlspecialchars($saved['n8n_url'] ?? ''); ?>"
               placeholder="https://your-n8n.com">
        <span class="hint">Example: https://n8n.yourdomain.com or https://yourname.app.n8n.cloud</span>
        <?php if (isset($errors['n8n_url'])): ?>
        <span class="field-error"><?php echo htmlspecialchars($errors['n8n_url']); ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="n8n_api_key">n8n API Key</label>
        <div class="pw-wrap">
            <input class="form-control" type="password" id="n8n_api_key" name="n8n_api_key"
                   value="<?php echo htmlspecialchars($saved['n8n_api_key'] ?? ''); ?>"
                   placeholder="••••••••••••••••">
            <button type="button" class="pw-toggle" onclick="togglePassword('n8n_api_key')">👁️</button>
        </div>
        <span class="hint">Find this in n8n → Settings → API Keys.</span>
    </div>

    <div class="form-group">
        <label for="n8n_webhook_url">Webhook URL <span style="color:var(--muted);font-weight:400">(Optional)</span></label>
        <input class="form-control" type="url" id="n8n_webhook_url" name="n8n_webhook_url"
               value="<?php echo htmlspecialchars($saved['n8n_webhook_url'] ?? ''); ?>"
               placeholder="https://your-n8n.com/webhook/fintech">
        <span class="hint">The webhook URL used to trigger campaigns from n8n.</span>
    </div>

    <button type="button" id="btnTestN8n" class="btn btn-outline btn-sm" onclick="testN8nConn()">
        🔌 Test Connection
    </button>
    <div id="n8nConnResult" class="conn-result"></div>
</div>
<div class="card-footer">
    <button type="submit" name="step_action" value="prev" class="btn btn-secondary">← Back</button>
    <div style="display:flex;gap:10px;align-items:center">
        <button type="submit" name="step_action" value="skip" class="skip-link">Skip →</button>
        <button type="submit" name="step_action" value="next" class="btn btn-primary">Save & Continue →</button>
    </div>
</div>
