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
    ];
    return htmlspecialchars($consts[$key] ?? $fallback);
}
?>

<h2 style="font-size:20px;margin-bottom:20px">⚙️ System Settings</h2>

<div id="settings-tabs" style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <?php
    $tabs = [
        'smtp'          => '📧 SMTP',
        'imap'          => '📥 IMAP',
        'branding'      => '🎨 Branding',
        'api_keys'      => '🔑 API Keys',
        'email_defaults'=> '⚙️ Email Defaults',
    ];
    $firstTab = true;
    foreach ($tabs as $id => $label):
    ?>
    <button class="tab-btn<?php echo $firstTab ? ' active' : ''; ?>" onclick="showTab('<?php echo $id; ?>')" id="tabn-<?php echo $id; ?>">
        <?php echo $label; $firstTab = false; ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- ─── SMTP ──────────────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-smtp">
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
                <input class="fi" id="smtp_from_name" value="<?php echo s('smtp_from_name'); ?>" placeholder="Canada HealthTech Symposium">
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
                <input class="fi" id="site_name" value="<?php echo s('site_name','Canada HealthTech Symposium 2026'); ?>" placeholder="Canada HealthTech Symposium 2026">
            </div>
            <div class="sf-row">
                <label>Site Tagline</label>
                <input class="fi" id="site_tagline" value="<?php echo s('site_tagline','Igniting the Future of Health'); ?>" placeholder="Igniting the Future of Health">
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
                <input class="fi" id="footer_text" value="<?php echo s('footer_text','© 2026 Canada HealthTech Symposium. All rights reserved.'); ?>" placeholder="© 2026 Canada HealthTech Symposium" style="width:100%">
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

<style>
.tab-btn { background:#1a2f4e;border:1px solid #1e3a5f;color:#e2e8f0;padding:8px 18px;border-radius:8px;cursor:pointer;font-size:13px;transition:.2s }
.tab-btn.active,.tab-btn:hover { background:#0d6efd;border-color:#0d6efd;color:#fff }
.settings-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px }
.sf-row { display:flex;flex-direction:column;gap:6px }
.sf-row label { font-size:13px;color:#8a9ab5 }
.pw-toggle { position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#1a2f4e;border:1px solid #1e3a5f;color:#e2e8f0;padding:3px 10px;border-radius:5px;cursor:pointer;font-size:12px }
@media(max-width:640px){ .settings-grid { grid-template-columns:1fr } }
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
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
