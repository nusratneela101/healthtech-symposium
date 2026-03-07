<?php
/**
 * install/steps/apollo.php
 * Step 7 — Apollo.io Setup (optional)
 */
$saved  = $_SESSION['install_data']   ?? [];
$errors = $_SESSION['install_errors'] ?? [];
unset($_SESSION['install_errors']);
?>
<div class="card-header">
    <h2>🚀 Apollo.io Integration <span style="font-size:13px;font-weight:500;color:var(--muted)">(Optional)</span></h2>
    <p>Connect Apollo.io to automatically discover and import leads for your campaigns.</p>
</div>
<div class="card-body">
    <?php if (!empty($errors['apollo_general'])): ?>
    <div class="alert alert-error">
        <span class="alert-icon">❌</span>
        <div><?php echo htmlspecialchars($errors['apollo_general']); ?></div>
    </div>
    <?php endif; ?>

    <div class="alert alert-info">
        <span class="alert-icon">ℹ️</span>
        <div>
            <strong>What is Apollo.io?</strong><br>
            Apollo is a sales intelligence platform used to find leads by industry, job title, location, and company size.
            <a href="https://app.apollo.io/#/settings/integrations/api_keys" target="_blank" rel="noopener">Get your API key →</a>
        </div>
    </div>

    <div class="form-group">
        <label for="apollo_api_key">Apollo API Key</label>
        <div class="pw-wrap">
            <input class="form-control" type="password" id="apollo_api_key" name="apollo_api_key"
                   value="<?php echo htmlspecialchars($saved['apollo_api_key'] ?? ''); ?>"
                   placeholder="••••••••••••••••">
            <button type="button" class="pw-toggle" onclick="togglePassword('apollo_api_key')">👁️</button>
        </div>
        <span class="hint">
            Find this in <a href="https://app.apollo.io/#/settings/integrations/api_keys" target="_blank" rel="noopener">
            Apollo → Settings → API Keys</a>.
        </span>
    </div>

    <div class="form-group">
        <label for="apollo_default_location">Default Search Location</label>
        <input class="form-control" type="text" id="apollo_default_location" name="apollo_default_location"
               value="<?php echo htmlspecialchars($saved['apollo_default_location'] ?? 'Canada'); ?>"
               placeholder="Canada">
        <span class="hint">Default country/region when searching for leads.</span>
    </div>

    <div class="form-group">
        <label for="apollo_company_size">Default Company Size Filter</label>
        <select class="form-control" id="apollo_company_size" name="apollo_company_size">
            <?php
            $sizes = [
                ''          => 'Any size',
                '1,10'      => '1–10 employees',
                '11,50'     => '11–50 employees',
                '51,200'    => '51–200 employees',
                '201,500'   => '201–500 employees',
                '501,1000'  => '501–1,000 employees',
                '1001,5000' => '1,001–5,000 employees',
                '5001+'     => '5,001+ employees',
            ];
            $selectedSize = $saved['apollo_company_size'] ?? '';
            foreach ($sizes as $val => $label):
            ?>
            <option value="<?php echo htmlspecialchars($val); ?>"
                    <?php echo $val === $selectedSize ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="button" id="btnTestApollo" class="btn btn-outline btn-sm" onclick="testApolloConn()">
        🔑 Test API Key
    </button>
    <div id="apolloConnResult" class="conn-result"></div>
</div>
<div class="card-footer">
    <button type="submit" name="step_action" value="prev" class="btn btn-secondary">← Back</button>
    <div style="display:flex;gap:10px;align-items:center">
        <button type="submit" name="step_action" value="skip" class="skip-link">Skip →</button>
        <button type="submit" name="step_action" value="next" class="btn btn-primary">Save & Continue →</button>
    </div>
</div>
