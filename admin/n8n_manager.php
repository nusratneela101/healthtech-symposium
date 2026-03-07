<?php
$pageTitle = 'n8n Manager';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireSuperAdmin();

// Workflow metadata
$workflowMeta = [
    'fintech_master_workflow.json' => ['name'=>'Master Campaign Sender','schedule'=>'Every 5 min','active'=>true],
    'lead_collector.json'          => ['name'=>'Lead Collector (Apollo)','schedule'=>'Daily 8 AM','active'=>true],
    'followup_sender.json'         => ['name'=>'Follow-up Sender','schedule'=>'Daily 10 AM','active'=>true],
    'response_tracker.json'        => ['name'=>'Response Tracker (IMAP)','schedule'=>'Every 10 min','active'=>true],
];
$coreWorkflows = array_keys($workflowMeta);
$wfDir = realpath(__DIR__ . '/../n8n_workflows') ?: (__DIR__ . '/../n8n_workflows');
$allFiles = glob($wfDir . '/*.json') ?: [];
$wfFiles = [];
foreach ($allFiles as $f) {
    $wfFiles[basename($f)] = $f;
}

// Check configured status
$n8nUrlOk    = getSetting('n8n_url') !== '';
$n8nKeyOk    = getSetting('n8n_api_key') !== '';
$apolloKeyOk = getSetting('apollo_api_key') !== '';
$brevoKeyOk  = getSetting('brevo_api_key') !== '';
?>

<h2 style="font-size:20px;margin-bottom:20px">🤖 n8n Manager</h2>

<div id="n8n-tabs" style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <?php
    $tabs = [
        'connection' => '🔌 Connection',
        'workflows'  => '📁 Workflows',
        'apollo'     => '🔍 Apollo',
        'brevo'      => '📧 Brevo',
        'guide'      => '📋 Setup Guide',
    ];
    $first = true;
    foreach ($tabs as $id => $label):
    ?>
    <button class="tab-btn<?php echo $first ? ' active' : ''; ?>" onclick="showTab('<?php echo $id; ?>')" id="tabn-<?php echo $id; ?>">
        <?php echo $label; $first = false; ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- ─── Connection ────────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-connection">
    <div class="gc">
        <div class="gc-title">🔌 n8n Connection</div>
        <div class="gc-sub">Configure your n8n instance URL and API key</div>
        <div class="settings-grid" style="grid-template-columns:1fr 1fr;margin-top:16px">
            <div class="sf-row">
                <label>n8n Instance URL</label>
                <input class="fi" id="n8n_instance_url" type="text" value="<?php echo htmlspecialchars(getSetting('n8n_url','')); ?>" placeholder="https://yourname.app.n8n.cloud">
            </div>
            <div class="sf-row">
                <label>n8n API Key</label>
                <div style="position:relative">
                    <input class="fi" id="n8n_instance_api_key" type="password" value="" placeholder="Leave blank to keep" style="width:100%;padding-right:80px">
                    <button type="button" onclick="togglePw('n8n_instance_api_key',this)" class="pw-toggle">Show</button>
                </div>
            </div>
            <div class="sf-row">
                <label>n8n Webhook URL <span style="color:#8a9ab5;font-size:11px">(optional)</span></label>
                <input class="fi" id="n8n_webhook_url" type="text" value="<?php echo htmlspecialchars(getSetting('n8n_webhook_url','')); ?>" placeholder="https://yourname.app.n8n.cloud/webhook/...">
            </div>
        </div>
        <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
            <button class="btn-launch" onclick="saveN8n()">💾 Save n8n Settings</button>
            <button class="btn-sec" onclick="testConn('n8n','conn-test-result')">🔗 Test n8n Connection</button>
        </div>
        <div id="conn-test-result" style="margin-top:8px;font-size:13px"></div>
    </div>
</div>

<!-- ─── Workflows ─────────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-workflows" style="display:none">
    <div class="gc">
        <div class="gc-title">📁 n8n Workflow Manager</div>
        <div class="gc-sub">Download, patch and manage workflow JSON files</div>
        <div class="tbl-wrap" style="margin-top:16px;overflow-x:auto">
            <table class="dt" style="width:100%">
                <thead>
                    <tr>
                        <th>Workflow</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($wfFiles as $filename => $filepath):
                    $meta  = $workflowMeta[$filename] ?? ['name'=>$filename,'schedule'=>'—','active'=>true];
                    $isCore = in_array($filename, $coreWorkflows);
                    $size  = file_exists($filepath) ? round(filesize($filepath)/1024,1).'KB' : '—';
                    $mtime = file_exists($filepath) ? date('M j, Y', filemtime($filepath)) : '—';
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:#e2e8f0"><?php echo htmlspecialchars($meta['name']); ?></div>
                        <div style="color:#8a9ab5;font-size:11px"><?php echo htmlspecialchars($filename); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($meta['schedule']); ?></td>
                    <td>
                        <?php if ($meta['active']): ?>
                        <span style="background:#10b981;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px">Active</span>
                        <?php else: ?>
                        <span style="background:#6b7280;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px">Deprecated</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $size; ?></td>
                    <td><?php echo $mtime; ?></td>
                    <td style="white-space:nowrap">
                        <a href="<?php echo APP_URL; ?>/api/download_workflow.php?file=<?php echo urlencode($filename); ?>" class="btn-sec" style="font-size:11px;padding:3px 8px;text-decoration:none;display:inline-block" title="Download">⬇️ Download</a>
                        <button class="btn-sec" style="font-size:11px;padding:3px 8px" onclick="patchWorkflow('<?php echo htmlspecialchars($filename,ENT_QUOTES); ?>')" title="Re-generate (patch placeholders)">🔄 Re-generate</button>
                        <?php if (!$isCore): ?>
                        <button class="btn-sec" style="font-size:11px;padding:3px 8px;color:#ef4444" onclick="deleteWorkflow('<?php echo htmlspecialchars($filename,ENT_QUOTES); ?>')" title="Delete">🗑️ Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:20px;border-top:1px solid #1e3a5f;padding-top:20px">
            <div style="font-size:14px;font-weight:600;color:#e2e8f0;margin-bottom:10px">📤 Upload New Workflow</div>
            <input type="file" id="wf_upload_file" accept=".json" style="color:#e2e8f0;font-size:13px">
            <div style="margin-top:10px">
                <button class="btn-launch" onclick="uploadWorkflow()">📤 Upload Workflow</button>
            </div>
            <div id="wf-upload-result" style="margin-top:8px;font-size:13px"></div>
        </div>
    </div>
</div>

<!-- ─── Apollo ────────────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-apollo" style="display:none">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div class="gc">
            <div class="gc-title">🔍 Apollo API Configuration</div>
            <div class="gc-sub">Apollo.io credentials</div>
            <div class="settings-grid" style="grid-template-columns:1fr;margin-top:16px">
                <div class="sf-row">
                    <label>Apollo API Key</label>
                    <div style="position:relative">
                        <input class="fi" id="apollo_api_key_mgr" type="password" value="" placeholder="Leave blank to keep current" style="width:100%;padding-right:80px">
                        <button type="button" onclick="togglePw('apollo_api_key_mgr',this)" class="pw-toggle">Show</button>
                    </div>
                </div>
            </div>
            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn-launch" onclick="saveApolloKey()">💾 Save</button>
                <button class="btn-sec" onclick="testConn('apollo','apollo-mgr-result')">🔍 Test Apollo</button>
            </div>
            <div id="apollo-mgr-result" style="margin-top:8px;font-size:13px"></div>
        </div>
        <div class="gc">
            <div class="gc-title">⚙️ Apollo Search Configuration</div>
            <div class="gc-sub">Parameters used by the lead_collector workflow</div>
            <div class="settings-grid" style="grid-template-columns:1fr;margin-top:16px">
                <div class="sf-row">
                    <label>Search Location</label>
                    <input class="fi" id="apollo_search_location_mgr" type="text" value="<?php echo htmlspecialchars(getSetting('apollo_search_location','Canada')); ?>" placeholder="e.g. Canada">
                </div>
                <div class="sf-row">
                    <label>Search Industry</label>
                    <input class="fi" id="apollo_search_industry_mgr" type="text" value="<?php echo htmlspecialchars(getSetting('apollo_search_industry','Health Technology')); ?>" placeholder="e.g. Health Technology">
                </div>
                <div class="sf-row">
                    <label>Job Titles to Target</label>
                    <textarea class="fi" id="apollo_search_titles_mgr" rows="4" placeholder="One per line: CEO, CTO, VP of Digital Health…" style="resize:vertical"><?php echo htmlspecialchars(getSetting('apollo_search_titles','')); ?></textarea>
                </div>
                <div class="sf-row">
                    <label>Results Per Page</label>
                    <input class="fi" id="apollo_per_page_mgr" type="number" value="<?php echo htmlspecialchars(getSetting('apollo_per_page','100')); ?>" min="10" max="200">
                </div>
                <div class="sf-row">
                    <label>Max Pages</label>
                    <input class="fi" id="apollo_max_pages_mgr" type="number" value="<?php echo htmlspecialchars(getSetting('apollo_max_pages','5')); ?>" min="1" max="20">
                </div>
            </div>
            <div style="margin-top:16px">
                <button class="btn-launch" onclick="saveApolloConfig()">💾 Save Apollo Config</button>
            </div>
            <div style="margin-top:12px;background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:10px;font-size:12px;color:#8a9ab5">
                These settings are read by n8n's lead_collector workflow via<br>
                <code style="color:#60a5fa">GET <?php echo APP_URL; ?>/api/get_apollo_config.php?api_key=YOUR_KEY</code>
            </div>
        </div>
    </div>
</div>

<!-- ─── Brevo ─────────────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-brevo" style="display:none">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div class="gc">
            <div class="gc-title">📧 Brevo API</div>
            <div class="gc-sub">Brevo (Sendinblue) credentials and sender info</div>
            <div class="settings-grid" style="grid-template-columns:1fr;margin-top:16px">
                <div class="sf-row">
                    <label>Brevo API Key</label>
                    <div style="position:relative">
                        <input class="fi" id="brevo_api_key_mgr" type="password" value="" placeholder="Leave blank to keep" style="width:100%;padding-right:80px">
                        <button type="button" onclick="togglePw('brevo_api_key_mgr',this)" class="pw-toggle">Show</button>
                    </div>
                </div>
                <div class="sf-row">
                    <label>Sender Name</label>
                    <input class="fi" id="brevo_sender_name_mgr" type="text" value="<?php echo htmlspecialchars(getSetting('brevo_sender_name', SMTP_FROM_NAME)); ?>" placeholder="<?php echo htmlspecialchars(SMTP_FROM_NAME); ?>">
                </div>
                <div class="sf-row">
                    <label>Sender Email</label>
                    <input class="fi" id="brevo_sender_email_mgr" type="email" value="<?php echo htmlspecialchars(getSetting('brevo_sender_email', SMTP_FROM_EMAIL)); ?>" placeholder="<?php echo htmlspecialchars(SMTP_FROM_EMAIL); ?>">
                </div>
            </div>
            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn-launch" onclick="saveBrevoSettings()">💾 Save Brevo Settings</button>
                <button class="btn-sec" onclick="testConn('brevo','brevo-mgr-result')">🧪 Test Brevo</button>
            </div>
            <div id="brevo-mgr-result" style="margin-top:8px;font-size:13px"></div>
        </div>
        <div class="gc">
            <div class="gc-title">📊 Brevo Account Info</div>
            <div class="gc-sub">Live account data from Brevo API</div>
            <div id="brevo-account-info" style="margin-top:16px;font-size:13px;color:#8a9ab5">
                Click Refresh to load account info.
            </div>
            <div style="margin-top:16px">
                <button class="btn-sec" onclick="loadBrevoInfo()">🔄 Refresh</button>
            </div>
        </div>
    </div>
</div>

<!-- ─── Setup Guide ───────────────────────────────────────────────────── -->
<div class="tab-panel" id="tab-guide" style="display:none">
    <div class="gc">
        <div class="gc-title">📋 Setup Guide</div>
        <div class="gc-sub">Step-by-step configuration checklist</div>
        <div style="margin-top:20px;display:flex;flex-direction:column;gap:14px">
            <!-- Status checks -->
            <div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:16px">
                <div style="font-size:14px;font-weight:600;color:#e2e8f0;margin-bottom:12px">Configuration Status</div>
                <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
                    <div><?php echo $n8nUrlOk ? '✅' : '⚠️'; ?> <strong>n8n Instance URL</strong>
                        <?php if (!$n8nUrlOk): ?><span style="color:#f59e0b"> — Not configured. Go to 🔌 Connection tab.</span><?php endif; ?>
                    </div>
                    <div><?php echo $n8nKeyOk ? '✅' : '⚠️'; ?> <strong>n8n API Key</strong>
                        <?php if (!$n8nKeyOk): ?><span style="color:#f59e0b"> — Not configured. Go to 🔌 Connection tab.</span><?php endif; ?>
                    </div>
                    <div><?php echo $apolloKeyOk ? '✅' : '⚠️'; ?> <strong>Apollo API Key</strong>
                        <?php if (!$apolloKeyOk): ?><span style="color:#f59e0b"> — Not configured. Go to 🔍 Apollo tab.</span><?php endif; ?>
                    </div>
                    <div><?php echo $brevoKeyOk ? '✅' : '⚠️'; ?> <strong>Brevo API Key</strong>
                        <?php if (!$brevoKeyOk): ?><span style="color:#f59e0b"> — Not configured. Go to 📧 Brevo tab.</span><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- API Endpoints -->
            <div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:16px">
                <div style="font-size:14px;font-weight:600;color:#e2e8f0;margin-bottom:12px">API Endpoints for n8n</div>
                <div style="display:flex;flex-direction:column;gap:10px;font-size:12px">
                    <?php
                    $endpoints = [
                        'apollo-config'    => ['label' => 'Apollo Config',     'url' => APP_URL . '/api/get_apollo_config.php?api_key=YOUR_N8N_KEY'],
                        'download-workflow'=> ['label' => 'Download Workflow',  'url' => APP_URL . '/api/download_workflow.php?file=lead_collector.json'],
                    ];
                    foreach ($endpoints as $id => $ep):
                    ?>
                    <div>
                        <div style="color:#8a9ab5;margin-bottom:3px"><?php echo htmlspecialchars($ep['label']); ?></div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <code id="ep-<?php echo htmlspecialchars($id); ?>" style="background:#0a1628;border:1px solid #1e3a5f;border-radius:4px;padding:4px 8px;color:#60a5fa;flex:1;overflow-x:auto"><?php echo htmlspecialchars($ep['url']); ?></code>
                            <button class="btn-sec" style="font-size:11px;padding:3px 10px;white-space:nowrap" onclick="copyText('ep-<?php echo htmlspecialchars($id); ?>')">📋 Copy</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Step-by-step -->
            <div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:16px">
                <div style="font-size:14px;font-weight:600;color:#e2e8f0;margin-bottom:12px">Step-by-Step Setup</div>
                <ol style="color:#8a9ab5;font-size:13px;line-height:1.8;padding-left:20px">
                    <li>Go to <strong style="color:#e2e8f0">🔌 Connection</strong> tab → enter your n8n Instance URL and API key → Save → Test.</li>
                    <li>Go to <strong style="color:#e2e8f0">🔍 Apollo</strong> tab → enter Apollo API key → configure search location, industry, and job titles → Save.</li>
                    <li>Go to <strong style="color:#e2e8f0">📧 Brevo</strong> tab → enter Brevo API key and sender details → Save → Test.</li>
                    <li>Go to <strong style="color:#e2e8f0">📁 Workflows</strong> tab → click <em>🔄 Re-generate</em> on each workflow to download patched copies with your credentials.</li>
                    <li>Import the patched JSON files into your n8n instance via the n8n web UI.</li>
                    <li>In n8n, configure each workflow to call your app's API endpoints (shown above) instead of placeholder URLs.</li>
                    <li>Activate workflows in n8n and verify they run correctly.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<style>
.tab-btn { background:#1a2f4e;border:1px solid #1e3a5f;color:#e2e8f0;padding:8px 18px;border-radius:8px;cursor:pointer;font-size:13px;transition:.2s }
.tab-btn.active,.tab-btn:hover { background:#0d6efd;border-color:#0d6efd;color:#fff }
.sf-row { display:flex;flex-direction:column;gap:6px }
.sf-row label { font-size:13px;color:#8a9ab5 }
.pw-toggle { position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#1a2f4e;border:1px solid #1e3a5f;color:#e2e8f0;padding:3px 10px;border-radius:5px;cursor:pointer;font-size:12px }
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

function testConn(service, resultId) {
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

function saveN8n() {
    var settings = {
        n8n_url:         document.getElementById('n8n_instance_url').value,
        n8n_api_key:     document.getElementById('n8n_instance_api_key').value,
        n8n_webhook_url: document.getElementById('n8n_webhook_url').value
    };
    fetch('<?php echo APP_URL; ?>/api/save_settings.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({group:'n8n', settings: settings})
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if(d.success){ showToast('✅ n8n settings saved','success'); }
        else { showToast('❌ '+(d.error||'Save failed'),'error'); }
    })
    .catch(function(e){ showToast('❌ '+e.message,'error'); });
}

function saveApolloKey() {
    var settings = { apollo_api_key: document.getElementById('apollo_api_key_mgr').value };
    fetch('<?php echo APP_URL; ?>/api/save_settings.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({group:'api_keys', settings: settings})
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if(d.success){ showToast('✅ Apollo key saved','success'); }
        else { showToast('❌ '+(d.error||'Save failed'),'error'); }
    })
    .catch(function(e){ showToast('❌ '+e.message,'error'); });
}

function saveApolloConfig() {
    var settings = {
        apollo_search_location: document.getElementById('apollo_search_location_mgr').value,
        apollo_search_industry: document.getElementById('apollo_search_industry_mgr').value,
        apollo_search_titles:   document.getElementById('apollo_search_titles_mgr').value,
        apollo_per_page:        document.getElementById('apollo_per_page_mgr').value,
        apollo_max_pages:       document.getElementById('apollo_max_pages_mgr').value
    };
    fetch('<?php echo APP_URL; ?>/api/save_settings.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({group:'api_keys', settings: settings})
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if(d.success){ showToast('✅ Apollo config saved','success'); }
        else { showToast('❌ '+(d.error||'Save failed'),'error'); }
    })
    .catch(function(e){ showToast('❌ '+e.message,'error'); });
}

function saveBrevoSettings() {
    var settings = {
        brevo_api_key:      document.getElementById('brevo_api_key_mgr').value,
        brevo_sender_name:  document.getElementById('brevo_sender_name_mgr').value,
        brevo_sender_email: document.getElementById('brevo_sender_email_mgr').value
    };
    fetch('<?php echo APP_URL; ?>/api/save_settings.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({group:'api_keys', settings: settings})
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if(d.success){ showToast('✅ Brevo settings saved','success'); }
        else { showToast('❌ '+(d.error||'Save failed'),'error'); }
    })
    .catch(function(e){ showToast('❌ '+e.message,'error'); });
}

function loadBrevoInfo() {
    var el = document.getElementById('brevo-account-info');
    el.innerHTML = '<span style="color:#8a9ab5">Loading…</span>';
    fetch('<?php echo APP_URL; ?>/api/test_connection.php?service=brevo_info')
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            el.innerHTML = '<table style="font-size:13px;color:#e2e8f0;border-collapse:collapse;width:100%">'
                + '<tr><td style="color:#8a9ab5;padding:4px 0">Email</td><td>'+d.email+'</td></tr>'
                + '<tr><td style="color:#8a9ab5;padding:4px 0">Plan</td><td>'+d.plan+'</td></tr>'
                + '<tr><td style="color:#8a9ab5;padding:4px 0">Credits</td><td>'+d.credits+'</td></tr>'
                + '<tr><td style="color:#8a9ab5;padding:4px 0">Remaining</td><td>'+d.creditsRemaining+'</td></tr>'
                + '</table>';
        } else {
            el.innerHTML = '<span style="color:#ef4444">❌ '+d.error+'</span>';
        }
    })
    .catch(function(e){ el.innerHTML='<span style="color:#ef4444">❌ '+e.message+'</span>'; });
}

function uploadWorkflow() {
    var fi = document.getElementById('wf_upload_file');
    var res = document.getElementById('wf-upload-result');
    if (!fi.files.length) { showToast('Select a .json file first','warning'); return; }
    var fd = new FormData();
    fd.append('workflow', fi.files[0]);
    res.innerHTML = '<span style="color:#8a9ab5">Uploading…</span>';
    fetch('<?php echo APP_URL; ?>/api/upload_workflow.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            res.innerHTML = '<span style="color:#10b981">✅ Uploaded: '+d.filename+'</span>';
            showToast('✅ Workflow uploaded','success');
            setTimeout(function(){ location.reload(); }, 1500);
        } else {
            res.innerHTML = '<span style="color:#ef4444">❌ '+(d.error||'Upload failed')+'</span>';
        }
    })
    .catch(function(e){ res.innerHTML='<span style="color:#ef4444">❌ '+e.message+'</span>'; });
}

function patchWorkflow(filename) {
    fetch('<?php echo APP_URL; ?>/api/patch_workflow.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({filename: filename})
    })
    .then(function(r){
        if (!r.ok) return r.json().then(function(d){ throw new Error(d.error||'Error'); });
        return r.blob();
    })
    .then(function(blob){
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = filename.replace('.json','_patched.json');
        link.click();
        URL.revokeObjectURL(url);
        showToast('✅ Patched workflow downloaded','success');
    })
    .catch(function(e){ showToast('❌ '+e.message,'error'); });
}

function deleteWorkflow(filename) {
    if (!confirm('Delete '+filename+'? This cannot be undone.')) return;
    fetch('<?php echo APP_URL; ?>/api/delete_workflow.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({filename: filename})
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) { showToast('✅ Deleted','success'); setTimeout(function(){ location.reload(); },1000); }
        else { showToast('❌ '+(d.error||'Delete failed'),'error'); }
    })
    .catch(function(e){ showToast('❌ '+e.message,'error'); });
}

function copyText(elemId) {
    var el = document.getElementById(elemId);
    if (!el) return;
    var text = el.textContent || el.innerText;
    navigator.clipboard.writeText(text).then(function(){
        showToast('📋 Copied to clipboard','success');
    }).catch(function(){
        showToast('Copy failed — please copy manually','error');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
