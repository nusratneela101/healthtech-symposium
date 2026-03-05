<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireSuperAdmin();

// Load current settings from DB
$settingsRows = [];
try {
    $rows = Database::fetchAll("SELECT setting_key, setting_value FROM site_settings");
    foreach ($rows as $r) {
        $settingsRows[$r['setting_key']] = $r['setting_value'];
    }
} catch (Exception $e) {}

// Helper: get setting value, falling back to constant or default
function s(string $key, string $fallback = ''): string {
    global $settingsRows;
    if (isset($settingsRows[$key]) && $settingsRows[$key] !== '') {
        return htmlspecialchars($settingsRows[$key]);
    }
    $consts = [
        'smtp_host'       => SMTP_HOST,
        'smtp_port'       => (string)SMTP_PORT,
        'smtp_secure'     => SMTP_SECURE,
        'smtp_user'       => SMTP_USER,
        'smtp_from_email' => SMTP_FROM_EMAIL,
        'smtp_from_name'  => SMTP_FROM_NAME,
        'imap_host'       => IMAP_HOST,
        'imap_user'       => IMAP_USER,
        'n8n_api_key'     => N8N_API_KEY,
        'brevo_api_key'   => BREVO_API_KEY,
        'ms_oauth_client_id'     => MS_OAUTH_CLIENT_ID,
        'ms_oauth_tenant_id'     => MS_OAUTH_TENANT_ID,
        'site_name'       => APP_NAME,
        'email_provider'  => '',
    ];
    return htmlspecialchars($consts[$key] ?? $fallback);
}
?>

<h2 style="font-size:20px;margin-bottom:20px">⚙️ System Settings</h2>

<div id="settings-tabs" style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <?php
    $tabs = [
        'email_setup'   => '📮 Email Setup',
        'smtp'          => '📧 SMTP',
        'imap'          => '📥 IMAP',
        'branding'      => '🎨 Branding',
        'api_keys'      => '🔑 API Keys',
        'email_defaults'=> '⚙️ Email Defaults',
        'sending_limits'=> '📊 Sending Limits',
    ];
    $firstTab = true;
    foreach ($tabs as $id => $label):
    ?>
    <button class="tab-btn<?php echo $firstTab ? ' active' : ''; ?>" onclick="showTab('<?php echo $id; ?>')" id="tabn-<?php echo $id; ?>">
        <?php echo $label; $firstTab = false; ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- ─── Email Setup ───────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-email_setup">
    <div class="gc">
        <div class="gc-title">📮 Email Provider Setup</div>
        <div class="gc-sub">Select your outgoing email provider — configuration fields will appear below</div>

        <?php $currentProvider = $settingsRows['email_provider'] ?? ''; ?>
        <div class="provider-grid">

            <!-- Microsoft 365 -->
            <div class="provider-card<?php echo $currentProvider==='microsoft365'?' provider-selected':''; ?>"
                 onclick="selectProvider('microsoft365')" id="card-microsoft365">
                <div class="provider-card-header">
                    <span class="provider-icon">🏢</span>
                    <span class="provider-name">Microsoft 365</span>
                    <span class="provider-badge provider-badge-best">✅ সেরা</span>
                </div>
                <div class="provider-email">info@canadafintechsymposium.com</div>
                <div class="provider-meta">Delivery: ⭐⭐⭐⭐⭐ &nbsp;|&nbsp; Setup: মাঝারি</div>
                <?php if($currentProvider==='microsoft365'): ?>
                <div class="provider-selected-label">✓ Selected</div>
                <?php endif; ?>
            </div>

            <!-- cPanel Email -->
            <div class="provider-card<?php echo $currentProvider==='cpanel'?' provider-selected':''; ?>"
                 onclick="selectProvider('cpanel')" id="card-cpanel">
                <div class="provider-card-header">
                    <span class="provider-icon">🌐</span>
                    <span class="provider-name">cPanel Email</span>
                    <span class="provider-badge provider-badge-good">✅ ভালো</span>
                </div>
                <div class="provider-email">info@fintech.softandpix.com</div>
                <div class="provider-meta">Delivery: ⭐⭐⭐ &nbsp;|&nbsp; Setup: সহজ</div>
                <?php if($currentProvider==='cpanel'): ?>
                <div class="provider-selected-label">✓ Selected</div>
                <?php endif; ?>
            </div>

            <!-- Business Email (SMTP) -->
            <div class="provider-card<?php echo $currentProvider==='business'?' provider-selected':''; ?>"
                 onclick="selectProvider('business')" id="card-business">
                <div class="provider-card-header">
                    <span class="provider-icon">📧</span>
                    <span class="provider-name">Business Email (SMTP)</span>
                    <span class="provider-badge provider-badge-good">✅ ভালো</span>
                </div>
                <div class="provider-email">info@canadafintechsymposium.com</div>
                <div class="provider-meta">Delivery: ⭐⭐⭐⭐ &nbsp;|&nbsp; Setup: সহজ</div>
                <?php if($currentProvider==='business'): ?>
                <div class="provider-selected-label">✓ Selected</div>
                <?php endif; ?>
            </div>

            <!-- Gmail -->
            <div class="provider-card provider-disabled<?php echo $currentProvider==='gmail'?' provider-selected':''; ?>"
                 onclick="selectProvider('gmail')" id="card-gmail">
                <div class="provider-card-header">
                    <span class="provider-icon">📮</span>
                    <span class="provider-name">Gmail</span>
                    <span class="provider-badge provider-badge-limited">❌ সীমিত</span>
                </div>
                <div class="provider-email">@gmail.com</div>
                <div class="provider-meta">Delivery: ⭐⭐ &nbsp;|&nbsp; Setup: সহজ</div>
                <?php if($currentProvider==='gmail'): ?>
                <div class="provider-selected-label">✓ Selected</div>
                <?php endif; ?>
            </div>

        </div><!-- /.provider-grid -->

        <!-- Config section (shown after selection) -->
        <div id="provider-config" style="display:<?php echo $currentProvider?'block':'none'; ?>;margin-top:24px">
            <div class="gc-title" id="provider-config-title" style="font-size:15px;margin-bottom:14px">
                <?php
                $titles = ['microsoft365'=>'🏢 Microsoft 365 Configuration','cpanel'=>'🌐 cPanel Email Configuration','business'=>'📧 Business Email Configuration','gmail'=>'📮 Gmail Configuration'];
                echo htmlspecialchars($titles[$currentProvider] ?? 'Configuration');
                ?>
            </div>
            <div class="settings-grid">
                <div class="sf-row">
                    <label>SMTP Host</label>
                    <input class="fi" id="ep_smtp_host" value="" placeholder="smtp.example.com">
                </div>
                <div class="sf-row">
                    <label>SMTP Port</label>
                    <input class="fi" id="ep_smtp_port" type="number" value="587" placeholder="587">
                </div>
                <div class="sf-row">
                    <label>Encryption</label>
                    <select class="fi" id="ep_smtp_secure">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="">None</option>
                    </select>
                </div>
                <div class="sf-row">
                    <label>SMTP Username</label>
                    <input class="fi" id="ep_smtp_user" value="" placeholder="user@domain.com">
                </div>
                <div class="sf-row">
                    <label>SMTP Password</label>
                    <div style="position:relative">
                        <input class="fi" id="ep_smtp_pass" type="password" value="" placeholder="Leave blank to keep current" style="width:100%;padding-right:80px">
                        <button type="button" onclick="togglePw('ep_smtp_pass',this)" class="pw-toggle">Show</button>
                    </div>
                </div>
                <div class="sf-row">
                    <label>From Email</label>
                    <input class="fi" id="ep_smtp_from_email" value="" placeholder="noreply@domain.com">
                </div>
                <div class="sf-row">
                    <label>From Name</label>
                    <input class="fi" id="ep_smtp_from_name" value="Canada Fintech Symposium" placeholder="Canada Fintech Symposium">
                </div>
                <div class="sf-row" id="ep_imap_host_row">
                    <label>IMAP Host</label>
                    <input class="fi" id="ep_imap_host" value="" placeholder="{mail.example.com:993/imap/ssl}INBOX">
                </div>
                <div class="sf-row" id="ep_imap_user_row">
                    <label>IMAP Username</label>
                    <input class="fi" id="ep_imap_user" value="" placeholder="user@domain.com">
                </div>
                <div class="sf-row" id="ep_imap_pass_row">
                    <label>IMAP Password</label>
                    <div style="position:relative">
                        <input class="fi" id="ep_imap_pass" type="password" value="" placeholder="Leave blank to keep current" style="width:100%;padding-right:80px">
                        <button type="button" onclick="togglePw('ep_imap_pass',this)" class="pw-toggle">Show</button>
                    </div>
                </div>
            </div>
            <div id="ep_gmail_note" style="display:none;margin-top:10px;padding:10px;background:#1a2f4e;border:1px solid #e9a800;border-radius:6px;font-size:13px;color:#e9a800">
                ⚠️ Gmail requires an App Password. Two-Factor Authentication must be enabled on your Google account.
            </div>
            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <button class="btn-launch" onclick="applyProviderSettings()">💾 Apply &amp; Save This Provider</button>
                <div style="display:flex;gap:8px;align-items:center">
                    <input class="fi" id="ep_test_email_to" placeholder="test@example.com" style="width:200px">
                    <button class="btn-sec" onclick="sendProviderTestEmail()">🧪 Send Test Email</button>
                </div>
            </div>
            <div id="ep-test-email-result" style="margin-top:10px;font-size:13px"></div>
        </div><!-- /#provider-config -->

    </div>
</div>

<!-- ─── SMTP ──────────────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-smtp" style="display:none">
    <div class="gc">
        <div class="gc-title">📧 SMTP Configuration</div>
        <div class="gc-sub">Outgoing email server settings</div>
        <div class="settings-grid">
            <div class="sf-row">
                <label>SMTP Host</label>
                <input class="fi" id="smtp_host" value="<?php echo s('smtp_host'); ?>" placeholder="smtp.example.com">
            </div>
            <div class="sf-row">
                <label>SMTP Port</label>
                <input class="fi" id="smtp_port" type="number" value="<?php echo s('smtp_port','587'); ?>" placeholder="587">
            </div>
            <div class="sf-row">
                <label>Encryption</label>
                <select class="fi" id="smtp_secure">
                    <?php foreach (['tls'=>'TLS','ssl'=>'SSL',''=>'None'] as $v=>$l): ?>
                    <option value="<?php echo $v; ?>" <?php echo s('smtp_secure')===$v?'selected':''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sf-row">
                <label>Username</label>
                <input class="fi" id="smtp_user" value="<?php echo s('smtp_user'); ?>" placeholder="user@domain.com">
            </div>
            <div class="sf-row">
                <label>Password</label>
                <div style="position:relative">
                    <input class="fi" id="smtp_pass" type="password" value="" placeholder="Leave blank to keep current" style="width:100%;padding-right:80px">
                    <button type="button" onclick="togglePw('smtp_pass',this)" class="pw-toggle">Show</button>
                </div>
            </div>
            <div class="sf-row">
                <label>From Email</label>
                <input class="fi" id="smtp_from_email" value="<?php echo s('smtp_from_email'); ?>" placeholder="noreply@domain.com">
            </div>
            <div class="sf-row">
                <label>From Name</label>
                <input class="fi" id="smtp_from_name" value="<?php echo s('smtp_from_name'); ?>" placeholder="Canada Fintech Symposium">
            </div>
        </div>
        <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
            <button class="btn-launch" onclick="saveSection('smtp',['smtp_host','smtp_port','smtp_secure','smtp_user','smtp_pass','smtp_from_email','smtp_from_name'])">💾 Save SMTP</button>
            <div style="display:flex;gap:8px;align-items:center">
                <input class="fi" id="test_email_to" placeholder="test@example.com" style="width:200px">
                <button class="btn-sec" onclick="sendTestEmail()">🧪 Send Test Email</button>
            </div>
        </div>
        <div id="test-email-result" style="margin-top:10px;font-size:13px"></div>
    </div>
</div>

<!-- ─── IMAP ──────────────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-imap" style="display:none">
    <div class="gc">
        <div class="gc-title">📥 IMAP Configuration</div>
        <div class="gc-sub">Incoming email / inbox polling settings</div>
        <div class="settings-grid">
            <div class="sf-row">
                <label>IMAP Host</label>
                <input class="fi" id="imap_host" value="<?php echo s('imap_host'); ?>" placeholder="{mail.example.com:993/imap/ssl}">
            </div>
            <div class="sf-row">
                <label>Port</label>
                <input class="fi" id="imap_port" type="number" value="<?php echo s('imap_port','993'); ?>" placeholder="993">
            </div>
            <div class="sf-row">
                <label>Username</label>
                <input class="fi" id="imap_user" value="<?php echo s('imap_user'); ?>" placeholder="user@domain.com">
            </div>
            <div class="sf-row">
                <label>Password</label>
                <div style="position:relative">
                    <input class="fi" id="imap_pass" type="password" value="<?php echo htmlspecialchars($settingsRows['imap_pass'] ?? ''); ?>" placeholder="••••••••" style="width:100%;padding-right:80px">
                    <button type="button" onclick="togglePw('imap_pass',this)" class="pw-toggle">Show</button>
                </div>
            </div>
            <div class="sf-row">
                <label>Encryption</label>
                <select class="fi" id="imap_secure">
                    <?php foreach (['ssl'=>'SSL','tls'=>'TLS',''=>'None'] as $v=>$l): ?>
                    <option value="<?php echo $v; ?>" <?php echo (s('imap_secure')===$v)?'selected':''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sf-row">
                <label>Mailbox Name</label>
                <input class="fi" id="imap_mailbox" value="<?php echo s('imap_mailbox','INBOX'); ?>" placeholder="INBOX">
            </div>
            <div class="sf-row">
                <label>Poll Interval (seconds)</label>
                <input class="fi" id="imap_poll_interval" type="number" value="<?php echo s('imap_poll_interval','300'); ?>" placeholder="300">
            </div>
        </div>
        <div style="margin-top:20px">
            <button class="btn-launch" onclick="saveSection('imap',['imap_host','imap_port','imap_user','imap_pass','imap_secure','imap_mailbox','imap_poll_interval'])">💾 Save IMAP</button>
        </div>
    </div>
</div>

<!-- ─── Branding ─────────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-branding" style="display:none">
    <div class="gc">
        <div class="gc-title">🎨 Branding</div>
        <div class="gc-sub">Site identity and appearance</div>
        <div class="settings-grid">
            <div class="sf-row">
                <label>Site Name</label>
                <input class="fi" id="site_name" value="<?php echo s('site_name','Canada Fintech Symposium 2026'); ?>" placeholder="Canada Fintech Symposium 2026">
            </div>
            <div class="sf-row">
                <label>Site Tagline</label>
                <input class="fi" id="site_tagline" value="<?php echo s('site_tagline','Igniting the Future of Finance'); ?>" placeholder="Igniting the Future of Finance">
            </div>
            <div class="sf-row">
                <label>Logo URL</label>
                <input class="fi" id="logo_url" value="<?php echo s('logo_url'); ?>" placeholder="https://example.com/logo.png">
            </div>
            <div class="sf-row">
                <label>Primary Color</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input class="fi" id="primary_color" value="<?php echo s('primary_color','#0d6efd'); ?>" placeholder="#0d6efd" style="flex:1">
                    <input type="color" id="primary_color_picker" value="<?php echo $settingsRows['primary_color'] ?? '#0d6efd'; ?>" style="width:40px;height:36px;border:none;background:none;cursor:pointer" oninput="document.getElementById('primary_color').value=this.value">
                </div>
            </div>
            <div class="sf-row">
                <label>Accent Color</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input class="fi" id="accent_color" value="<?php echo s('accent_color','#cc0000'); ?>" placeholder="#cc0000" style="flex:1">
                    <input type="color" id="accent_color_picker" value="<?php echo $settingsRows['accent_color'] ?? '#cc0000'; ?>" style="width:40px;height:36px;border:none;background:none;cursor:pointer" oninput="document.getElementById('accent_color').value=this.value">
                </div>
            </div>
            <div class="sf-row" style="grid-column:span 2">
                <label>Footer Text</label>
                <input class="fi" id="footer_text" value="<?php echo s('footer_text','© 2026 Canada Fintech Symposium. All rights reserved.'); ?>" placeholder="© 2026 Canada Fintech Symposium" style="width:100%">
            </div>
        </div>
        <div style="margin-top:20px">
            <button class="btn-launch" onclick="saveSection('branding',['site_name','site_tagline','logo_url','primary_color','accent_color','footer_text'])">💾 Save Branding</button>
        </div>
    </div>
</div>

<!-- ─── API Keys ──────────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-api_keys" style="display:none">
    <div class="gc">
        <div class="gc-title">🔑 API Keys</div>
        <div class="gc-sub">Third-party service credentials</div>
        <div class="settings-grid">
            <?php
            $apiFields = [
                'n8n_api_key'            => ['label'=>'N8N API Key'],
                'brevo_api_key'          => ['label'=>'Brevo API Key'],
                'ms_oauth_client_id'     => ['label'=>'MS OAuth Client ID'],
                'ms_oauth_client_secret' => ['label'=>'MS OAuth Client Secret'],
                'ms_oauth_tenant_id'     => ['label'=>'MS OAuth Tenant ID'],
                'apollo_api_key'         => ['label'=>'Apollo API Key'],
            ];
            foreach ($apiFields as $fieldId => $cfg):
            ?>
            <div class="sf-row">
                <label><?php echo $cfg['label']; ?></label>
                <div style="position:relative">
                    <input class="fi" id="<?php echo $fieldId; ?>" type="password" value="" placeholder="Leave blank to keep current" style="width:100%;padding-right:80px">
                    <button type="button" onclick="togglePw('<?php echo $fieldId; ?>',this)" class="pw-toggle">Show</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:20px">
            <button class="btn-launch" onclick="saveSection('api_keys',<?php echo json_encode(array_keys($apiFields)); ?>)">💾 Save API Keys</button>
            <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn-sec" onclick="testConnection('brevo','brevo-result')">🧪 Test Brevo</button>
                <button class="btn-sec" onclick="testConnection('n8n','n8n-result')">🔗 Test n8n</button>
                <button class="btn-sec" onclick="testConnection('apollo','apollo-result')">🔍 Test Apollo</button>
            </div>
            <div style="margin-top:8px;font-size:13px;display:flex;flex-direction:column;gap:4px">
                <div id="brevo-result"></div>
                <div id="n8n-result"></div>
                <div id="apollo-result"></div>
            </div>
        </div>
    </div>
</div>

<!-- ─── Email Defaults ────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-email_defaults" style="display:none">
    <div class="gc">
        <div class="gc-title">⚙️ Email Defaults</div>
        <div class="gc-sub">Campaign and sending behaviour defaults</div>
        <div class="settings-grid">
            <div class="sf-row">
                <label>Send Delay (seconds between emails)</label>
                <input class="fi" id="send_delay" type="number" value="<?php echo s('send_delay','5'); ?>" placeholder="5" min="1" max="600">
            </div>
            <div class="sf-row">
                <label>Max Emails per Campaign Batch</label>
                <input class="fi" id="max_batch" type="number" value="<?php echo s('max_batch','500'); ?>" placeholder="500" min="1">
            </div>
            <div class="sf-row">
                <label>Test Mode Default</label>
                <select class="fi" id="test_mode_default">
                    <option value="0" <?php echo s('test_mode_default','0')==='0'?'selected':''; ?>>Off — Send real emails</option>
                    <option value="1" <?php echo s('test_mode_default','0')==='1'?'selected':''; ?>>On — Log only, don't send</option>
                </select>
            </div>
        </div>
        <div style="margin-top:20px">
            <button class="btn-launch" onclick="saveSection('email_defaults',['send_delay','max_batch','test_mode_default'])">💾 Save Defaults</button>
        </div>
    </div>
</div>

<!-- ─── SENDING LIMITS ──────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-sending_limits" style="display:none">
    <div class="gc">
        <div class="gc-title">📊 Sending Limits</div>
        <div class="gc-sub">Set maximum emails per period. 0 = Unlimited. Limits are enforced in real-time by the sending API.</div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:20px">

            <!-- Campaign Emails -->
            <div>
                <div style="font-size:14px;font-weight:600;color:#e2e8f0;margin-bottom:16px">📧 Campaign Emails (Sequence 1)</div>
                <div class="sf-row">
                    <label>Daily Limit</label>
                    <input class="fi" id="email_daily_limit" type="number" min="0"
                           value="<?php echo s('email_daily_limit','0'); ?>" placeholder="0 = Unlimited">
                    <div class="limit-bar-wrap" id="bar_email_daily"><div class="limit-bar" style="width:0%"></div></div>
                    <div class="limit-caption" id="cap_email_daily">Loading...</div>
                </div>
                <div class="sf-row">
                    <label>Weekly Limit</label>
                    <input class="fi" id="email_weekly_limit" type="number" min="0"
                           value="<?php echo s('email_weekly_limit','0'); ?>" placeholder="0 = Unlimited">
                    <div class="limit-bar-wrap" id="bar_email_weekly"><div class="limit-bar" style="width:0%"></div></div>
                    <div class="limit-caption" id="cap_email_weekly">Loading...</div>
                </div>
                <div class="sf-row">
                    <label>Monthly Limit</label>
                    <input class="fi" id="email_monthly_limit" type="number" min="0"
                           value="<?php echo s('email_monthly_limit','0'); ?>" placeholder="0 = Unlimited">
                    <div class="limit-bar-wrap" id="bar_email_monthly"><div class="limit-bar" style="width:0%"></div></div>
                    <div class="limit-caption" id="cap_email_monthly">Loading...</div>
                </div>
            </div>

            <!-- Follow-up Emails -->
            <div>
                <div style="font-size:14px;font-weight:600;color:#e2e8f0;margin-bottom:16px">📨 Follow-up Emails (Sequence 2+)</div>
                <div class="sf-row">
                    <label>Daily Limit</label>
                    <input class="fi" id="followup_daily_limit" type="number" min="0"
                           value="<?php echo s('followup_daily_limit','0'); ?>" placeholder="0 = Unlimited">
                    <div class="limit-bar-wrap" id="bar_followup_daily"><div class="limit-bar" style="width:0%"></div></div>
                    <div class="limit-caption" id="cap_followup_daily">Loading...</div>
                </div>
                <div class="sf-row">
                    <label>Weekly Limit</label>
                    <input class="fi" id="followup_weekly_limit" type="number" min="0"
                           value="<?php echo s('followup_weekly_limit','0'); ?>" placeholder="0 = Unlimited">
                    <div class="limit-bar-wrap" id="bar_followup_weekly"><div class="limit-bar" style="width:0%"></div></div>
                    <div class="limit-caption" id="cap_followup_weekly">Loading...</div>
                </div>
                <div class="sf-row">
                    <label>Monthly Limit</label>
                    <input class="fi" id="followup_monthly_limit" type="number" min="0"
                           value="<?php echo s('followup_monthly_limit','0'); ?>" placeholder="0 = Unlimited">
                    <div class="limit-bar-wrap" id="bar_followup_monthly"><div class="limit-bar" style="width:0%"></div></div>
                    <div class="limit-caption" id="cap_followup_monthly">Loading...</div>
                </div>
            </div>
        </div>

        <div style="margin-top:20px">
            <button class="btn-launch"
                onclick="saveSection('sending_limits',[
                    'email_daily_limit','email_weekly_limit','email_monthly_limit',
                    'followup_daily_limit','followup_weekly_limit','followup_monthly_limit'
                ])">💾 Save Limits</button>
            <span style="font-size:12px;color:#8a9ab5;margin-left:12px">0 = Unlimited (no restriction applied)</span>
        </div>
        <div id="sending_limits-result" style="margin-top:10px;font-size:13px"></div>
    </div>
</div>

<style>
.tab-btn { background:#1a2f4e;border:1px solid #1e3a5f;color:#e2e8f0;padding:8px 18px;border-radius:8px;cursor:pointer;font-size:13px;transition:.2s }
.tab-btn.active,.tab-btn:hover { background:#0d6efd;border-color:#0d6efd;color:#fff }
.settings-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px }
.sf-row { display:flex;flex-direction:column;gap:6px }
.sf-row label { font-size:13px;color:#8a9ab5 }
.pw-toggle { position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#1a2f4e;border:1px solid #1e3a5f;color:#e2e8f0;padding:3px 10px;border-radius:5px;cursor:pointer;font-size:12px }
/* Provider cards */
.provider-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px }
.provider-card { background:#0d1b2e;border:1px solid #1e3a5f;border-radius:10px;padding:16px;cursor:pointer;transition:.2s;position:relative }
.provider-card:hover { border-color:#0d6efd;background:rgba(13,110,253,0.06) }
.provider-selected { border:2px solid #0d6efd !important;background:rgba(13,110,253,0.1) !important }
.provider-disabled { opacity:.6;cursor:not-allowed }
.provider-card-header { display:flex;align-items:center;gap:8px;margin-bottom:8px }
.provider-icon { font-size:20px }
.provider-name { font-size:14px;font-weight:600;color:#e2e8f0;flex:1 }
.provider-badge { font-size:11px;padding:2px 8px;border-radius:12px;white-space:nowrap }
.provider-badge-best { background:rgba(16,185,129,0.15);color:#10b981 }
.provider-badge-good { background:rgba(59,130,246,0.15);color:#60a5fa }
.provider-badge-limited { background:rgba(239,68,68,0.15);color:#f87171 }
.provider-email { font-size:12px;color:#8a9ab5;margin-bottom:4px }
.provider-meta { font-size:12px;color:#60738a }
.provider-selected-label { position:absolute;bottom:10px;right:12px;font-size:11px;color:#10b981;font-weight:600 }
@media(max-width:640px){ .settings-grid { grid-template-columns:1fr } .provider-grid { grid-template-columns:1fr } }
.limit-bar-wrap { background:#1e3355; border-radius:4px; height:6px; margin-top:6px; overflow:hidden; }
.limit-bar      { height:100%; background:#10b981; border-radius:4px; transition:width .4s; }
.limit-bar.warn { background:#f59e0b; }
.limit-bar.danger { background:#ef4444; }
.limit-caption  { font-size:11px; color:#8a9ab5; margin-top:3px; }
</style>

<script>
function showTab(id){
    document.querySelectorAll('.tab-panel').forEach(function(p){ p.style.display='none'; });
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
    document.getElementById('tab-'+id).style.display='block';
    document.getElementById('tabn-'+id).classList.add('active');
}

function togglePw(id, btn){
    var el = document.getElementById(id);
    if(el.type==='password'){ el.type='text'; btn.textContent='Hide'; }
    else { el.type='password'; btn.textContent='Show'; }
}

function saveSection(group, keys){
    var settings = {};
    keys.forEach(function(k){
        var el = document.getElementById(k);
        if(el) settings[k] = el.value;
    });
    fetch('<?php echo APP_URL; ?>/api/save_settings.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({group: group, settings: settings})
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if(d.success){ showToast('✅ Settings saved ('+d.saved+' values updated)', 'success'); }
        else { showToast('❌ Error: '+(d.error||'Unknown'), 'error'); }
    })
    .catch(function(e){ showToast('❌ Network error: '+e.message,'error'); });
}

function sendTestEmail(){
    var to = document.getElementById('test_email_to').value.trim();
    if(!to){ showToast('Enter a recipient email address first','warning'); return; }
    var res = document.getElementById('test-email-result');
    res.textContent = 'Sending…';
    fetch('<?php echo APP_URL; ?>/api/send_test_email.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({to_email: to})
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if(d.success){
            res.innerHTML = '<span style="color:#10b981">✅ '+d.message+' ('+d.elapsed_ms+'ms via '+d.via+')</span>';
            showToast('✅ Test email sent!','success');
        } else {
            res.innerHTML = '<span style="color:#ef4444">❌ '+d.error+' (via '+d.via+')</span>';
            showToast('❌ Test email failed','error');
        }
    })
    .catch(function(e){ res.innerHTML='<span style="color:#ef4444">❌ '+e.message+'</span>'; });
}

function showToast(msg, type){
    if(typeof window.toast === 'function'){ window.toast(msg,type); return; }
    var wrap = document.getElementById('toast-wrap');
    if(!wrap) return;
    var el = document.createElement('div');
    el.className = 'toast toast-'+(type||'info');
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(function(){ el.remove(); }, 4000);
}

var providerDefaults = {
    microsoft365: {
        smtp_host: 'smtp-mail.outlook.com',
        smtp_port: '587',
        smtp_secure: 'tls',
        smtp_user: 'info@canadafintechsymposium.com',
        smtp_from_email: 'info@canadafintechsymposium.com',
        smtp_from_name: 'Canada Fintech Symposium',
        imap_host: '{outlook.office365.com:993/imap/ssl}INBOX',
        imap_user: 'info@canadafintechsymposium.com',
        hasImap: true,
        title: '🏢 Microsoft 365 Configuration'
    },
    cpanel: {
        smtp_host: 'mail.softandpix.com',
        smtp_port: '587',
        smtp_secure: 'tls',
        smtp_user: 'info@fintech.softandpix.com',
        smtp_from_email: 'info@fintech.softandpix.com',
        smtp_from_name: 'Canada Fintech Symposium',
        imap_host: '{mail.softandpix.com:993/imap/ssl}INBOX',
        imap_user: 'info@fintech.softandpix.com',
        hasImap: true,
        title: '🌐 cPanel Email Configuration'
    },
    business: {
        smtp_host: '',
        smtp_port: '587',
        smtp_secure: 'tls',
        smtp_user: 'info@canadafintechsymposium.com',
        smtp_from_email: 'info@canadafintechsymposium.com',
        smtp_from_name: 'Canada Fintech Symposium',
        hasImap: false,
        title: '📧 Business Email Configuration'
    },
    gmail: {
        smtp_host: 'smtp.gmail.com',
        smtp_port: '587',
        smtp_secure: 'tls',
        smtp_user: '',
        smtp_from_email: '',
        smtp_from_name: 'Canada Fintech Symposium',
        hasImap: false,
        title: '📮 Gmail Configuration'
    }
};

var currentSelectedProvider = '<?php echo htmlspecialchars($settingsRows['email_provider'] ?? ''); ?>';

function selectProvider(provider) {
    // Update card styles
    ['microsoft365','cpanel','business','gmail'].forEach(function(p) {
        var card = document.getElementById('card-'+p);
        if (!card) return;
        card.classList.remove('provider-selected');
        var lbl = card.querySelector('.provider-selected-label');
        if (lbl) lbl.remove();
    });
    var selCard = document.getElementById('card-'+provider);
    if (selCard) {
        selCard.classList.add('provider-selected');
        var lbl = document.createElement('div');
        lbl.className = 'provider-selected-label';
        lbl.textContent = '✓ Selected';
        selCard.appendChild(lbl);
    }
    currentSelectedProvider = provider;

    // Populate fields
    var d = providerDefaults[provider];
    if (!d) return;
    document.getElementById('provider-config-title').textContent = d.title;
    document.getElementById('ep_smtp_host').value = d.smtp_host || '';
    document.getElementById('ep_smtp_port').value = d.smtp_port || '587';
    var secEl = document.getElementById('ep_smtp_secure');
    for (var i=0; i<secEl.options.length; i++) {
        secEl.options[i].selected = (secEl.options[i].value === (d.smtp_secure || 'tls'));
    }
    document.getElementById('ep_smtp_user').value = d.smtp_user || '';
    document.getElementById('ep_smtp_from_email').value = d.smtp_from_email || '';
    document.getElementById('ep_smtp_from_name').value = d.smtp_from_name || '';

    // IMAP fields
    var imapRows = ['ep_imap_host_row','ep_imap_user_row','ep_imap_pass_row'];
    imapRows.forEach(function(id) {
        document.getElementById(id).style.display = d.hasImap ? '' : 'none';
    });
    if (d.hasImap) {
        document.getElementById('ep_imap_host').value = d.imap_host || '';
        document.getElementById('ep_imap_user').value = d.imap_user || '';
    }

    // Gmail note
    document.getElementById('ep_gmail_note').style.display = (provider === 'gmail') ? 'block' : 'none';

    document.getElementById('provider-config').style.display = 'block';
}

function applyProviderSettings() {
    if (!currentSelectedProvider) { showToast('Please select a provider first','warning'); return; }
    var smtpSettings = {
        smtp_host: document.getElementById('ep_smtp_host').value,
        smtp_port: document.getElementById('ep_smtp_port').value,
        smtp_secure: document.getElementById('ep_smtp_secure').value,
        smtp_user: document.getElementById('ep_smtp_user').value,
        smtp_pass: document.getElementById('ep_smtp_pass').value,
        smtp_from_email: document.getElementById('ep_smtp_from_email').value,
        smtp_from_name: document.getElementById('ep_smtp_from_name').value
    };
    var d = providerDefaults[currentSelectedProvider];
    var saves = [
        fetch('<?php echo APP_URL; ?>/api/save_settings.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({group:'smtp', settings: smtpSettings})
        }).then(function(r){ return r.json(); })
    ];
    if (d && d.hasImap) {
        var imapSettings = {
            imap_host: document.getElementById('ep_imap_host').value,
            imap_user: document.getElementById('ep_imap_user').value,
            imap_pass: document.getElementById('ep_imap_pass').value
        };
        saves.push(
            fetch('<?php echo APP_URL; ?>/api/save_settings.php', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({group:'imap', settings: imapSettings})
            }).then(function(r){ return r.json(); })
        );
    }
    saves.push(
        fetch('<?php echo APP_URL; ?>/api/save_settings.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({group:'email_setup', settings: {email_provider: currentSelectedProvider}})
        }).then(function(r){ return r.json(); })
    );
    Promise.all(saves).then(function(results) {
        var allOk = results.every(function(d){ return d.success; });
        if (allOk) { showToast('✅ Provider settings saved','success'); }
        else { showToast('❌ Error saving some settings','error'); }
    }).catch(function(e){ showToast('❌ Network error: '+e.message,'error'); });
}

function sendProviderTestEmail() {
    var to = document.getElementById('ep_test_email_to').value.trim();
    if (!to) { showToast('Enter a recipient email address first','warning'); return; }
    var res = document.getElementById('ep-test-email-result');
    res.textContent = 'Sending…';
    fetch('<?php echo APP_URL; ?>/api/send_test_email.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({to_email: to})
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            res.innerHTML = '<span style="color:#10b981">✅ '+d.message+' ('+d.elapsed_ms+'ms via '+d.via+')</span>';
            showToast('✅ Test email sent!','success');
        } else {
            res.innerHTML = '<span style="color:#ef4444">❌ '+d.error+' (via '+d.via+')</span>';
            showToast('❌ Test email failed','error');
        }
    })
    .catch(function(e){ res.innerHTML='<span style="color:#ef4444">❌ '+e.message+'</span>'; });
}

function testConnection(service, resultId) {
    var res = document.getElementById(resultId);
    res.innerHTML = '<span style="color:#8a9ab5">Testing…</span>';
    fetch('<?php echo APP_URL; ?>/api/test_connection.php?service='+encodeURIComponent(service))
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            res.innerHTML = '<span style="color:#10b981">✅ '+d.message+'</span>';
        } else {
            res.innerHTML = '<span style="color:#ef4444">❌ Error: '+d.error+'</span>';
        }
    })
    .catch(function(e){ res.innerHTML='<span style="color:#ef4444">❌ '+e.message+'</span>'; });
}

// On load, if a provider was already saved, populate the config fields
if (currentSelectedProvider && providerDefaults[currentSelectedProvider]) {
    selectProvider(currentSelectedProvider);
}

async function loadLimitStats() {
    try {
        const r = await fetch('<?php echo APP_URL; ?>/api/get_sending_stats.php');
        const d = await r.json();
        const rows = [
            ...d.campaign.map(x => ({ ...x, prefix: 'email_' + x.label.toLowerCase() })),
            ...d.followup.map(x => ({ ...x, prefix: 'followup_' + x.label.toLowerCase() })),
        ];
        rows.forEach(x => {
            const barWrap = document.getElementById('bar_' + x.prefix);
            const cap     = document.getElementById('cap_' + x.prefix);
            if (!barWrap || !cap) return;
            const bar = barWrap.querySelector('.limit-bar');
            const pct = Math.min(x.pct, 100);
            bar.style.width = pct + '%';
            bar.className   = 'limit-bar' + (pct >= 90 ? ' danger' : pct >= 70 ? ' warn' : '');
            cap.textContent = x.limit === 0
                ? `${x.sent} sent — Unlimited`
                : `${x.sent} / ${x.limit} sent (${pct}%)`;
        });
    } catch(e) {}
}
// Load when tab is shown
document.getElementById('tabn-sending_limits')?.addEventListener('click', loadLimitStats);
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
