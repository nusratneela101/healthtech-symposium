/* installer.js — Canada Fintech Symposium Installer */
'use strict';

// ─── Password strength meter ─────────────────────────────────
function updatePasswordStrength(password, fillId, textId) {
    var fill = document.getElementById(fillId);
    var text = document.getElementById(textId);
    if (!fill || !text) return;

    var score = 0;
    if (password.length >= 8)                     score++;
    if (password.length >= 12)                    score++;
    if (/[A-Z]/.test(password))                   score++;
    if (/[a-z]/.test(password))                   score++;
    if (/[0-9]/.test(password))                   score++;
    if (/[\W_]/.test(password))                   score++;

    var pct    = Math.round((score / 6) * 100);
    var labels = ['', 'Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    var colors = ['', '#EF4444', '#F59E0B', '#F59E0B', '#10B981', '#10B981', '#059669'];

    fill.style.width      = pct + '%';
    fill.style.background = colors[score] || '#E5E7EB';
    text.textContent      = score > 0 ? labels[score] : '';
    text.style.color      = colors[score] || '#6B7280';
}

// ─── Show / hide password ─────────────────────────────────────
function togglePassword(inputId) {
    var input = document.getElementById(inputId);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}

// ─── Generate secure password ────────────────────────────────
function generatePassword(inputId, confirmId) {
    var chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    var result = '';
    // Ensure at least one of each required class
    result += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)];
    result += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)];
    result += '0123456789'[Math.floor(Math.random() * 10)];
    result += '!@#$%^&*'[Math.floor(Math.random() * 8)];
    for (var i = 4; i < 16; i++) {
        result += chars[Math.floor(Math.random() * chars.length)];
    }
    // Shuffle
    result = result.split('').sort(function(){ return Math.random() - 0.5; }).join('');

    var inp = document.getElementById(inputId);
    var conf = document.getElementById(confirmId);
    if (inp)  { inp.value  = result; inp.type  = 'text'; }
    if (conf) { conf.value = result; conf.type = 'text'; }

    // Trigger strength update
    if (inp) {
        var ev = new Event('input', { bubbles: true });
        inp.dispatchEvent(ev);
    }
}

// ─── Copy text to clipboard ───────────────────────────────────
function copyToClipboard(text, btn) {
    if (!navigator.clipboard) {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    } else {
        navigator.clipboard.writeText(text).catch(function(){});
    }
    if (btn) {
        var orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function(){ btn.textContent = orig; }, 1500);
    }
}

// ─── AJAX helper ─────────────────────────────────────────────
function ajaxPost(url, data, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            var resp = null;
            try { resp = JSON.parse(xhr.responseText); } catch(e) { resp = { success: false, error: xhr.responseText }; }
            callback(resp, xhr.status);
        }
    };
    var encoded = Object.keys(data).map(function(k){
        return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
    }).join('&');
    xhr.send(encoded);
}

// ─── Test database connection ─────────────────────────────────
function testDbConnection() {
    var btn    = document.getElementById('btnTestDb');
    var result = document.getElementById('dbConnResult');
    if (!btn || !result) return;

    var fields = ['db_host','db_name','db_user','db_pass','db_port'];
    var data   = { action: 'test_db' };
    fields.forEach(function(f){
        var el = document.querySelector('[name="' + f + '"]');
        data[f] = el ? el.value : '';
    });

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="display:inline-block"></span> Testing…';
    result.className = 'conn-result';

    ajaxPost(window.installerAjaxUrl, data, function(resp) {
        btn.disabled = false;
        btn.innerHTML = '🔌 Test Connection';
        result.classList.add('show');
        if (resp.success) {
            result.className = 'conn-result show conn-ok';
            result.innerHTML = '✅ Connected! MySQL ' + (resp.version || '');
        } else {
            result.className = 'conn-result show conn-fail';
            result.innerHTML = '❌ ' + (resp.error || 'Connection failed');
        }
    });
}

// ─── Test email connection ─────────────────────────────────────
function testEmailConn() {
    var btn    = document.getElementById('btnTestEmail');
    var result = document.getElementById('emailConnResult');
    if (!btn || !result) return;

    var providerEl = document.querySelector('[name="email_provider"]:checked');
    var provider   = providerEl ? providerEl.value : '';
    var data       = { action: 'test_email', email_provider: provider };

    var fields = ['brevo_api_key','smtp_host','smtp_port','smtp_secure','smtp_user','smtp_pass','ms_client_id','ms_client_secret','ms_tenant_id'];
    fields.forEach(function(f){
        var el = document.querySelector('[name="' + f + '"]');
        if (el) data[f] = el.value;
    });

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="display:inline-block"></span> Testing…';
    result.className = 'conn-result';

    ajaxPost(window.installerAjaxUrl, data, function(resp) {
        btn.disabled = false;
        btn.innerHTML = '📬 Test Connection';
        result.classList.add('show');
        if (resp.success) {
            result.className = 'conn-result show conn-ok';
            result.innerHTML = '✅ ' + (resp.info || 'Connected successfully');
        } else {
            result.className = 'conn-result show conn-fail';
            result.innerHTML = '❌ ' + (resp.error || 'Connection failed');
        }
    });
}

// ─── Test n8n connection ──────────────────────────────────────
function testN8nConn() {
    var btn    = document.getElementById('btnTestN8n');
    var result = document.getElementById('n8nConnResult');
    if (!btn || !result) return;

    var urlEl    = document.querySelector('[name="n8n_url"]');
    var keyEl    = document.querySelector('[name="n8n_api_key"]');
    var data = {
        action:      'test_n8n',
        n8n_url:     urlEl  ? urlEl.value  : '',
        n8n_api_key: keyEl  ? keyEl.value  : '',
    };

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="display:inline-block"></span> Testing…';
    result.className = 'conn-result';

    ajaxPost(window.installerAjaxUrl, data, function(resp) {
        btn.disabled = false;
        btn.innerHTML = '🔌 Test Connection';
        result.classList.add('show');
        if (resp.success) {
            result.className = 'conn-result show conn-ok';
            result.innerHTML = '✅ ' + (resp.info || 'n8n connected');
        } else {
            result.className = 'conn-result show conn-fail';
            result.innerHTML = '❌ ' + (resp.error || 'Connection failed');
        }
    });
}

// ─── Test Apollo connection ───────────────────────────────────
function testApolloConn() {
    var btn    = document.getElementById('btnTestApollo');
    var result = document.getElementById('apolloConnResult');
    if (!btn || !result) return;

    var keyEl = document.querySelector('[name="apollo_api_key"]');
    var data  = { action: 'test_apollo', apollo_api_key: keyEl ? keyEl.value : '' };

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="display:inline-block"></span> Testing…';
    result.className = 'conn-result';

    ajaxPost(window.installerAjaxUrl, data, function(resp) {
        btn.disabled = false;
        btn.innerHTML = '🔑 Test API Key';
        result.classList.add('show');
        if (resp.success) {
            result.className = 'conn-result show conn-ok';
            result.innerHTML = '✅ ' + (resp.info || 'API key valid');
        } else {
            result.className = 'conn-result show conn-fail';
            result.innerHTML = '❌ ' + (resp.error || 'Invalid API key');
        }
    });
}

// ─── Provider radio card selection ───────────────────────────
function selectProvider(value) {
    // Update radio
    var radio = document.querySelector('[name="email_provider"][value="' + value + '"]');
    if (radio) radio.checked = true;

    // Toggle card highlight
    document.querySelectorAll('.provider-card').forEach(function(card) {
        card.classList.toggle('selected', card.dataset.provider === value);
    });

    // Show/hide fields
    document.querySelectorAll('.provider-fields').forEach(function(pf) {
        pf.classList.toggle('show', pf.dataset.for === value);
    });
}

// ─── Real-time validation ─────────────────────────────────────
function attachValidation() {
    // Username: alphanumeric + underscore, 3-20 chars
    var un = document.getElementById('username');
    if (un) {
        un.addEventListener('input', function() {
            var v = this.value;
            var ok = /^[a-zA-Z0-9_]{3,20}$/.test(v);
            this.classList.toggle('valid', ok && v !== '');
            this.classList.toggle('error', !ok && v !== '');
        });
    }

    // Email field
    var em = document.getElementById('admin_email');
    if (em) {
        em.addEventListener('input', function() {
            var ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
            this.classList.toggle('valid', ok);
            this.classList.toggle('error', !ok && this.value !== '');
        });
    }

    // Password
    var pw = document.getElementById('password');
    if (pw) {
        pw.addEventListener('input', function() {
            updatePasswordStrength(this.value, 'pw-strength-fill', 'pw-strength-text');
        });
    }

    // Confirm password
    var cpw = document.getElementById('confirm_password');
    if (cpw && pw) {
        cpw.addEventListener('input', function() {
            var match = this.value === pw.value;
            this.classList.toggle('valid', match && this.value !== '');
            this.classList.toggle('error', !match && this.value !== '');
        });
    }
}

// ─── Init ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    attachValidation();

    // Pre-select first provider card if none selected
    var firstProvider = document.querySelector('.provider-card');
    if (firstProvider) {
        var firstVal = firstProvider.dataset.provider;
        var anyChecked = document.querySelector('[name="email_provider"]:checked');
        if (!anyChecked && firstVal) {
            selectProvider(firstVal);
        } else if (anyChecked) {
            selectProvider(anyChecked.value);
        }
    }
});
