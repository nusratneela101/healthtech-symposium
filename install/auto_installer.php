<?php
/**
 * install/auto_installer.php
 *
 * Standalone auto-installer with detailed error reporting.
 * Designed for fresh deployments on shared cPanel hosting.
 *
 * Usage:
 *   1. Visit https://yourdomain.com/install/auto_installer.php
 *   2. Fill in database credentials and click "Test Connection"
 *   3. If connection succeeds, click "Install Now"
 *   4. Log in at /login.php with admin / admin123
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

define('INSTALLER_ROOT', dirname(__DIR__));
define('INSTALLER_ENV_PATH', INSTALLER_ROOT . '/.env');
define('INSTALLER_LOCK_PATH', INSTALLER_ROOT . '/install/install.lock');
define('INSTALLER_SQL_PATH',  INSTALLER_ROOT . '/install/install.sql');

// ─────────────────────────────────────────────────────────────
// Lock guard
// ─────────────────────────────────────────────────────────────
if (file_exists(INSTALLER_LOCK_PATH) && ($_GET['force'] ?? '') !== '1') {
    autoInstallerPage(renderLocked());
    exit;
}

// ─────────────────────────────────────────────────────────────
// Handle AJAX / POST actions
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test_db') {
        header('Content-Type: application/json');
        echo json_encode(aiTestConnection(
            trim($_POST['db_host'] ?? 'localhost'),
            trim($_POST['db_name'] ?? ''),
            trim($_POST['db_user'] ?? ''),
            $_POST['db_pass'] ?? '',
            (int)($_POST['db_port'] ?? 3306)
        ));
        exit;
    }

    if ($action === 'install') {
        header('Content-Type: application/json');
        echo json_encode(aiRunInstall(
            trim($_POST['db_host'] ?? 'localhost'),
            trim($_POST['db_name'] ?? ''),
            trim($_POST['db_user'] ?? ''),
            $_POST['db_pass'] ?? '',
            (int)($_POST['db_port'] ?? 3306),
            trim($_POST['app_url'] ?? ''),
            trim($_POST['app_name'] ?? 'Canada Fintech Symposium')
        ));
        exit;
    }
}

// ─────────────────────────────────────────────────────────────
// Render main page
// ─────────────────────────────────────────────────────────────
autoInstallerPage(renderForm());
exit;

// ─────────────────────────────────────────────────────────────
// Functions
// ─────────────────────────────────────────────────────────────

function aiTestConnection(string $host, string $dbname, string $user, string $pass, int $port): array
{
    if ($dbname === '') {
        return ['success' => false, 'error' => 'Database name is required.'];
    }
    if ($user === '') {
        return ['success' => false, 'error' => 'Database username is required.'];
    }

    try {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE         => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_CONNECT_TIMEOUT => 10,
        ]);

        $version = $pdo->query('SELECT VERSION()')->fetchColumn();

        $stmt   = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($dbname));
        $exists = $stmt->rowCount() > 0;

        return [
            'success'    => true,
            'db_exists'  => $exists,
            'version'    => $version,
            'message'    => $exists
                ? "Connected successfully. Database '{$dbname}' exists. MySQL {$version}"
                : "Connected successfully. Database '{$dbname}' will be created during install. MySQL {$version}",
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error'   => 'PDO connection failed: ' . $e->getMessage(),
            'code'    => $e->getCode(),
        ];
    }
}

function aiRunInstall(
    string $host,
    string $dbname,
    string $user,
    string $pass,
    int    $port,
    string $appUrl,
    string $appName
): array {
    $log = [];

    // 1. Test connection
    $conn = aiTestConnection($host, $dbname, $user, $pass, $port);
    if (!$conn['success']) {
        return ['success' => false, 'error' => $conn['error'], 'log' => $log];
    }
    $log[] = ['status' => 'ok', 'msg' => 'Database connection verified. ' . ($conn['message'] ?? '')];

    // 2. Create database if needed
    if (!($conn['db_exists'] ?? false)) {
        try {
            // Validate database name: only alphanumeric and underscores allowed
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbname)) {
                return ['success' => false, 'error' => 'Invalid database name. Only letters, numbers, and underscores are allowed.', 'log' => $log];
            }
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $log[] = ['status' => 'ok', 'msg' => "Database '{$dbname}' created."];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Could not create database: ' . $e->getMessage(), 'log' => $log];
        }
    } else {
        $log[] = ['status' => 'ok', 'msg' => "Database '{$dbname}' already exists."];
    }

    // 3. Create tables
    $tableResult = aiCreateTables($host, $dbname, $user, $pass, $port);
    if (!$tableResult['success']) {
        return ['success' => false, 'error' => $tableResult['error'], 'log' => array_merge($log, $tableResult['log'] ?? [])];
    }
    $log = array_merge($log, $tableResult['log']);

    // 4. Create default admin user (admin / admin123)
    $adminResult = aiCreateAdmin($host, $dbname, $user, $pass, $port);
    if (!$adminResult['success']) {
        return ['success' => false, 'error' => $adminResult['error'], 'log' => array_merge($log, $adminResult['log'] ?? [])];
    }
    $log = array_merge($log, $adminResult['log']);

    // 5. Write .env
    $envResult = aiWriteEnv($host, $dbname, $user, $pass, $port, $appUrl, $appName);
    if (!$envResult['success']) {
        return ['success' => false, 'error' => $envResult['error'], 'log' => array_merge($log, $envResult['log'] ?? [])];
    }
    $log = array_merge($log, $envResult['log']);

    // 6. Lock installer
    $lockResult = aiLock();
    $log = array_merge($log, $lockResult['log']);

    return ['success' => true, 'log' => $log];
}

function aiCreateTables(string $host, string $dbname, string $user, string $pass, int $port): array
{
    $log = [];

    if (!file_exists(INSTALLER_SQL_PATH)) {
        return ['success' => false, 'error' => 'install.sql not found at: ' . INSTALLER_SQL_PATH, 'log' => $log];
    }

    try {
        $dsn = "mysql:host={$host};dbname={$dbname};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $sql  = file_get_contents(INSTALLER_SQL_PATH);
        // Strip single-line SQL comments (-- ...) and multi-line comments (/* ... */)
        $sql  = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql  = preg_replace('/^\s*--.*$/m', '', $sql);
        $stmts = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($stmts as $stmt) {
            if ($stmt === '') continue;
            try {
                $pdo->exec($stmt);
                if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $stmt, $m)) {
                    $log[] = ['status' => 'ok', 'msg' => "Table '{$m[1]}' created or already exists."];
                }
            } catch (PDOException $e) {
                $code = (int)$e->getCode();
                // Ignore "already exists" / duplicate index errors
                if (in_array($code, [1050, 1060, 1061, 1068], true) ||
                    stripos($e->getMessage(), 'already exists') !== false ||
                    stripos($e->getMessage(), 'Duplicate') !== false) {
                    continue;
                }
                return ['success' => false, 'error' => 'SQL error: ' . $e->getMessage(), 'log' => $log];
            }
        }

        $log[] = ['status' => 'ok', 'msg' => 'Database tables created/verified successfully.'];
        return ['success' => true, 'log' => $log];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database connection failed during table setup: ' . $e->getMessage(), 'log' => $log];
    }
}

function aiCreateAdmin(string $host, string $dbname, string $user, string $pass, int $port): array
{
    $log = [];

    try {
        $dsn = "mysql:host={$host};dbname={$dbname};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Check if admin user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute(['admin']);
        if ($stmt->fetch()) {
            $log[] = ['status' => 'info', 'msg' => 'Default admin user already exists (skipped).'];
            return ['success' => true, 'log' => $log];
        }

        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $ins  = $pdo->prepare(
            "INSERT INTO users (name, username, email, password, role, is_active) VALUES (?, ?, ?, ?, 'superadmin', 1)"
        );
        $ins->execute(['Administrator', 'admin', 'admin@example.com', $hash]);

        $log[] = ['status' => 'ok', 'msg' => 'Default admin user created (username: admin, password: admin123).'];
        return ['success' => true, 'log' => $log];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Could not create admin user: ' . $e->getMessage(), 'log' => $log];
    }
}

function aiWriteEnv(
    string $host,
    string $dbname,
    string $user,
    string $pass,
    int    $port,
    string $appUrl,
    string $appName
): array {
    $log = [];

    if ($appUrl === '') {
        $proto  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $appUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $appUrl  = rtrim($appUrl, '/');
    try {
        $appKey = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Could not generate APP_KEY: ' . $e->getMessage(), 'log' => $log];
    }

    $content = <<<ENV
APP_NAME="{$appName}"
APP_URL="{$appUrl}"
APP_VERSION="2.0.0"
APP_KEY="{$appKey}"

# Database
DB_HOST={$host}
DB_NAME={$dbname}
DB_USER={$user}
DB_PASS={$pass}
DB_PORT={$port}

# Session
SESSION_NAME=fts_session

# Email limits
EMAIL_DAILY_LIMIT=0
EMAIL_WEEKLY_LIMIT=0
EMAIL_MONTHLY_LIMIT=0
FOLLOWUP_DAILY_LIMIT=0
FOLLOWUP_WEEKLY_LIMIT=0
FOLLOWUP_MONTHLY_LIMIT=0
ENV;

    if (file_put_contents(INSTALLER_ENV_PATH, $content) === false) {
        return [
            'success' => false,
            'error'   => '.env file could not be written to: ' . INSTALLER_ENV_PATH
                       . '. Check directory permissions (chmod 0755 ' . INSTALLER_ROOT . ').',
            'log'     => $log,
        ];
    }

    $log[] = ['status' => 'ok', 'msg' => '.env file written successfully.'];
    return ['success' => true, 'log' => $log];
}

function aiLock(): array
{
    $log = [];
    $lockDir = dirname(INSTALLER_LOCK_PATH);
    if (!is_dir($lockDir)) {
        if (!mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
            $log[] = ['status' => 'warn', 'msg' => 'Warning: Could not create directory for install.lock: ' . $lockDir];
            return ['log' => $log];
        }
    }
    if (file_put_contents(INSTALLER_LOCK_PATH, date('Y-m-d H:i:s')) !== false) {
        $log[] = ['status' => 'ok', 'msg' => 'Installer locked (install.lock created).'];
    } else {
        $log[] = ['status' => 'warn', 'msg' => 'Warning: Could not create install.lock. Delete install/auto_installer.php manually after setup.'];
    }
    return ['log' => $log];
}

// ─────────────────────────────────────────────────────────────
// Diagnostics
// ─────────────────────────────────────────────────────────────

function getDiagnostics(): array
{
    $checks = [];

    $checks[] = [
        'label'  => 'PHP Version',
        'value'  => PHP_VERSION,
        'passed' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'note'   => PHP_VERSION . ' (requires ≥ 7.4)',
    ];

    foreach (['pdo', 'pdo_mysql', 'mysqli', 'curl', 'openssl', 'json', 'session', 'mbstring'] as $ext) {
        $loaded = extension_loaded($ext);
        $checks[] = [
            'label'  => $ext,
            'value'  => $loaded ? 'loaded' : 'MISSING',
            'passed' => $loaded,
            'note'   => '',
        ];
    }

    $mem = ini_get('memory_limit');
    $checks[] = [
        'label'  => 'memory_limit',
        'value'  => $mem,
        'passed' => true,
        'note'   => '',
    ];

    $checks[] = [
        'label'  => 'max_execution_time',
        'value'  => ini_get('max_execution_time') . 's',
        'passed' => true,
        'note'   => '',
    ];

    $root = INSTALLER_ROOT;
    $checks[] = [
        'label'  => 'Root dir writable',
        'value'  => is_writable($root) ? 'Yes' : 'No',
        'passed' => is_writable($root),
        'note'   => $root,
    ];

    $checks[] = [
        'label'  => 'install.sql exists',
        'value'  => file_exists(INSTALLER_SQL_PATH) ? 'Yes' : 'No',
        'passed' => file_exists(INSTALLER_SQL_PATH),
        'note'   => INSTALLER_SQL_PATH,
    ];

    $checks[] = [
        'label'  => '.env exists',
        'value'  => file_exists(INSTALLER_ENV_PATH) ? 'Yes' : 'No',
        'passed' => true, // not required before install
        'note'   => INSTALLER_ENV_PATH,
    ];

    $checks[] = [
        'label'  => 'installer locked',
        'value'  => file_exists(INSTALLER_LOCK_PATH) ? 'Yes' : 'No',
        'passed' => true,
        'note'   => '',
    ];

    return $checks;
}

// ─────────────────────────────────────────────────────────────
// HTML rendering
// ─────────────────────────────────────────────────────────────

function renderLocked(): string
{
    $loginUrl = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') . '/login.php';
    return <<<HTML
    <div class="ai-card">
        <div class="ai-locked">
            <div class="ai-lock-icon">🔒</div>
            <h2>Already Installed</h2>
            <p>The installer is locked. Delete <code>install/install.lock</code> to re-run, or append <code>?force=1</code> to the URL.</p>
            <a href="{$loginUrl}" class="ai-btn ai-btn-primary">Go to Login →</a>
        </div>
    </div>
HTML;
}

function renderForm(): string
{
    $checks    = getDiagnostics();
    $allOk     = array_reduce($checks, fn($c, $r) => $c && $r['passed'], true);
    $statusBg  = $allOk ? '#d4edda' : '#fff3cd';
    $statusMsg = $allOk ? '✅ All requirements met' : '⚠️ Some checks failed — review before installing';
    $selfUrl   = htmlspecialchars($_SERVER['PHP_SELF'] ?? '/install/auto_installer.php');
    $guessUrl  = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
               . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    $diagRows = '';
    foreach ($checks as $c) {
        $icon    = $c['passed'] ? '✅' : '❌';
        $note    = $c['note'] ? '<small>' . htmlspecialchars($c['note']) . '</small>' : '';
        $diagRows .= "<tr><td>{$icon} " . htmlspecialchars($c['label']) . "</td>"
                   . "<td><strong>" . htmlspecialchars($c['value']) . "</strong> {$note}</td></tr>\n";
    }

    return <<<HTML
    <!-- Diagnostics -->
    <div class="ai-card">
        <h2>System Diagnostics</h2>
        <div class="ai-status" style="background:{$statusBg};">{$statusMsg}</div>
        <table class="ai-table">
            <thead><tr><th>Check</th><th>Result</th></tr></thead>
            <tbody>{$diagRows}</tbody>
        </table>
    </div>

    <!-- Installer form -->
    <div class="ai-card">
        <h2>Database &amp; Application Configuration</h2>
        <form id="aiForm">
            <div class="ai-fieldset">
                <legend>Database</legend>
                <div class="ai-row">
                    <label>Host</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                <div class="ai-row">
                    <label>Database Name</label>
                    <input type="text" name="db_name" placeholder="e.g. easyrgai_cafintech" required>
                </div>
                <div class="ai-row">
                    <label>Username</label>
                    <input type="text" name="db_user" placeholder="e.g. easyrgai_cafintech" required>
                </div>
                <div class="ai-row">
                    <label>Password</label>
                    <input type="password" name="db_pass" placeholder="Database password">
                </div>
                <div class="ai-row">
                    <label>Port</label>
                    <input type="number" name="db_port" value="3306" min="1" max="65535">
                </div>
            </div>

            <div class="ai-fieldset">
                <legend>Application</legend>
                <div class="ai-row">
                    <label>App Name</label>
                    <input type="text" name="app_name" value="Canada Fintech Symposium">
                </div>
                <div class="ai-row">
                    <label>App URL</label>
                    <input type="text" name="app_url" value="{$guessUrl}" placeholder="https://yourdomain.com">
                </div>
            </div>

            <div id="aiTestResult" class="ai-msg" style="display:none;"></div>

            <div class="ai-actions">
                <button type="button" id="btnTest" class="ai-btn ai-btn-secondary" onclick="aiTestDb()">
                    🔌 Test Connection
                </button>
                <button type="button" id="btnInstall" class="ai-btn ai-btn-primary" disabled onclick="aiInstall()">
                    🚀 Install Now
                </button>
            </div>
        </form>
    </div>

    <!-- Install log output -->
    <div id="aiLog" class="ai-card" style="display:none;">
        <h2>Install Log</h2>
        <ul id="aiLogList"></ul>
        <div id="aiLogFinal"></div>
    </div>

    <script>
    const AI_URL = '{$selfUrl}';

    function getFormData(action) {
        const f = document.getElementById('aiForm');
        const d = new FormData(f);
        d.set('action', action);
        return d;
    }

    function setMsg(el, html, type) {
        el.innerHTML = html;
        el.className = 'ai-msg ai-msg-' + type;
        el.style.display = 'block';
    }

    async function aiTestDb() {
        const btn = document.getElementById('btnTest');
        const res = document.getElementById('aiTestResult');
        const ins = document.getElementById('btnInstall');
        btn.disabled = true;
        btn.textContent = '⏳ Testing…';
        setMsg(res, 'Testing connection…', 'info');
        ins.disabled = true;
        try {
            const r = await fetch(AI_URL, {method:'POST', body: getFormData('test_db')});
            const j = await r.json();
            if (j.success) {
                setMsg(res, '✅ ' + (j.message || 'Connection successful!'), 'ok');
                ins.disabled = false;
            } else {
                setMsg(res, '❌ ' + (j.error || 'Unknown error') + (j.code ? ' (code ' + j.code + ')' : ''), 'error');
            }
        } catch(e) {
            setMsg(res, '❌ Request failed: ' + e.message, 'error');
        }
        btn.disabled = false;
        btn.textContent = '🔌 Test Connection';
    }

    async function aiInstall() {
        const btn  = document.getElementById('btnInstall');
        const log  = document.getElementById('aiLog');
        const list = document.getElementById('aiLogList');
        const fin  = document.getElementById('aiLogFinal');
        btn.disabled = true;
        btn.textContent = '⏳ Installing…';
        list.innerHTML = '';
        fin.innerHTML  = '';
        log.style.display = 'block';
        try {
            const r = await fetch(AI_URL, {method:'POST', body: getFormData('install')});
            const j = await r.json();
            (j.log || []).forEach(entry => {
                const icon = entry.status === 'ok' ? '✅' : (entry.status === 'warn' ? '⚠️' : 'ℹ️');
                list.innerHTML += '<li>' + icon + ' ' + entry.msg + '</li>';
            });
            if (j.success) {
                fin.innerHTML = '<div class="ai-msg ai-msg-ok"><strong>✅ Installation complete!</strong><br>'
                    + 'Default login: <code>admin</code> / <code>admin123</code><br>'
                    + '<strong>⚠️ Change your password after first login.</strong><br><br>'
                    + '<a href="../login.php" class="ai-btn ai-btn-primary">Go to Login →</a></div>';
            } else {
                fin.innerHTML = '<div class="ai-msg ai-msg-error"><strong>❌ Installation failed:</strong><br>'
                    + (j.error || 'Unknown error') + '</div>';
                btn.disabled = false;
                btn.textContent = '🚀 Retry Install';
            }
        } catch(e) {
            fin.innerHTML = '<div class="ai-msg ai-msg-error">❌ Request failed: ' + e.message + '</div>';
            btn.disabled = false;
            btn.textContent = '🚀 Retry Install';
        }
    }
    </script>
HTML;
}

function autoInstallerPage(string $body): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Auto Installer — Canada Fintech Symposium</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    background: #f0f2f5;
    color: #212529;
    padding: 24px 16px 48px;
}
h1 { font-size: 1.6rem; margin-bottom: 4px; }
h2 { font-size: 1.1rem; margin-bottom: 12px; color: #343a40; }
.ai-header {
    max-width: 760px; margin: 0 auto 24px;
    background: #cc0000; color: #fff; border-radius: 10px;
    padding: 20px 24px; display: flex; align-items: center; gap: 16px;
}
.ai-header svg { flex-shrink: 0; }
.ai-card {
    max-width: 760px; margin: 0 auto 20px;
    background: #fff; border-radius: 10px;
    padding: 20px 24px; box-shadow: 0 1px 4px rgba(0,0,0,.08);
}
.ai-status {
    border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; font-weight: 500;
}
.ai-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.ai-table th, .ai-table td { text-align: left; padding: 6px 10px; border-bottom: 1px solid #e9ecef; }
.ai-table th { background: #f8f9fa; font-weight: 600; }
.ai-fieldset {
    border: 1px solid #dee2e6; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px;
}
.ai-fieldset legend { font-weight: 600; padding: 0 6px; color: #495057; }
.ai-row { display: flex; align-items: center; gap: 12px; margin-top: 10px; }
.ai-row label { width: 140px; flex-shrink: 0; font-size: .875rem; font-weight: 500; color: #495057; }
.ai-row input {
    flex: 1; padding: 7px 10px; border: 1px solid #ced4da; border-radius: 6px;
    font-size: .9rem; outline: none;
}
.ai-row input:focus { border-color: #86b7fe; box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
.ai-msg { border-radius: 6px; padding: 10px 14px; font-size: .9rem; margin-bottom: 12px; }
.ai-msg-info  { background: #cff4fc; border: 1px solid #9eeaf9; color: #055160; }
.ai-msg-ok    { background: #d1e7dd; border: 1px solid #a3cfbb; color: #0f5132; }
.ai-msg-error { background: #f8d7da; border: 1px solid #f1aeb5; color: #842029; }
.ai-msg-warn  { background: #fff3cd; border: 1px solid #ffe69c; color: #664d03; }
.ai-actions { display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap; }
.ai-btn {
    display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px;
    border-radius: 6px; border: none; cursor: pointer; font-size: .9rem; font-weight: 500;
    text-decoration: none; transition: opacity .15s;
}
.ai-btn:disabled { opacity: .5; cursor: not-allowed; }
.ai-btn-primary  { background: #cc0000; color: #fff; }
.ai-btn-primary:hover:not(:disabled) { background: #a50000; }
.ai-btn-secondary { background: #0d6efd; color: #fff; }
.ai-btn-secondary:hover:not(:disabled) { background: #0b5ed7; }
#aiLogList { list-style: none; padding: 0; }
#aiLogList li { padding: 5px 0; font-size: .875rem; border-bottom: 1px solid #f1f1f1; }
.ai-locked { text-align: center; padding: 20px 0; }
.ai-lock-icon { font-size: 3rem; margin-bottom: 12px; }
</style>
</head>
<body>
<div class="ai-header">
    <svg width="48" height="48" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <path d="M50 5 L58 30 L75 20 L65 38 L85 35 L70 50 L80 70 L60 60 L55 85 L50 75 L45 85 L40 60 L20 70 L30 50 L15 35 L35 38 L25 20 L42 30 Z" fill="rgba(255,255,255,.9)"/>
        <text x="50" y="58" text-anchor="middle" fill="#cc0000" font-size="16" font-weight="900" font-family="Arial">CFTS</text>
    </svg>
    <div>
        <h1>Auto Installer</h1>
        <p style="opacity:.85;font-size:.9rem;">Canada Fintech Symposium &mdash; Fresh Installation</p>
    </div>
</div>
<?php echo $body; ?>
</body>
</html>
    <?php
}
