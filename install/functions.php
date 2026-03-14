<?php
/**
 * install/functions.php
 * Helper functions for the installation wizard.
 */

// ─────────────────────────────────────────────────────────────
// Requirements
// ─────────────────────────────────────────────────────────────

function checkRequirements(): array
{
    $results = [];

    // PHP version
    $results[] = [
        'label'    => 'PHP Version ≥ 7.4',
        'passed'   => version_compare(PHP_VERSION, '7.4.0', '>='),
        'critical' => true,
        'info'     => 'Current: ' . PHP_VERSION,
        'fix'      => 'Upgrade PHP to 7.4 or newer.',
    ];

    // Extensions
    $exts = [
        ['mysqli',  'MySQLi extension',  true,  'Install php-mysqli.'],
        ['pdo',     'PDO extension',     true,  'Install php-pdo.'],
        ['pdo_mysql','PDO MySQL driver', true,  'Install php-pdo_mysql.'],
        ['curl',    'cURL extension',    true,  'Install php-curl.'],
        ['openssl', 'OpenSSL extension', true,  'Install php-openssl.'],
        ['json',    'JSON extension',    true,  'Install php-json.'],
        ['session', 'Session extension', true,  'Install php-session.'],
        ['mbstring','mbstring extension',false, 'Install php-mbstring (recommended).'],
    ];
    foreach ($exts as [$ext, $label, $critical, $fix]) {
        $results[] = [
            'label'    => $label,
            'passed'   => extension_loaded($ext),
            'critical' => $critical,
            'fix'      => $fix,
        ];
    }

    // Memory limit
    $mem   = return_bytes(ini_get('memory_limit'));
    $results[] = [
        'label'    => 'Memory limit ≥ 128 MB',
        'passed'   => $mem === -1 || $mem >= 128 * 1024 * 1024,
        'critical' => false,
        'info'     => 'Current: ' . ini_get('memory_limit'),
        'fix'      => 'Set memory_limit = 128M in php.ini.',
    ];

    // POST max size
    $post = return_bytes(ini_get('post_max_size'));
    $results[] = [
        'label'    => 'POST max size ≥ 8 MB',
        'passed'   => $post >= 8 * 1024 * 1024,
        'critical' => false,
        'info'     => 'Current: ' . ini_get('post_max_size'),
        'fix'      => 'Set post_max_size = 8M in php.ini.',
    ];

    // Upload max filesize
    $upload = return_bytes(ini_get('upload_max_filesize'));
    $results[] = [
        'label'    => 'Upload max filesize ≥ 8 MB',
        'passed'   => $upload >= 8 * 1024 * 1024,
        'critical' => false,
        'info'     => 'Current: ' . ini_get('upload_max_filesize'),
        'fix'      => 'Set upload_max_filesize = 8M in php.ini.',
    ];

    // Writable root (for .env)
    $root = dirname(__DIR__);
    $results[] = [
        'label'    => '.env file writable (root directory)',
        'passed'   => is_writable($root),
        'critical' => true,
        'info'     => 'Path: ' . $root,
        'fix'      => 'Run: chmod 0755 ' . $root,
    ];

    return $results;
}

function return_bytes(string $val): int
{
    $val = trim($val);
    if ($val === '-1') return -1;
    $last = strtolower($val[strlen($val) - 1]);
    $num  = (int)$val;
    switch ($last) {
        case 'g': $num *= 1024;
        // fall through
        case 'm': $num *= 1024;
        // fall through
        case 'k': $num *= 1024;
    }
    return $num;
}

// ─────────────────────────────────────────────────────────────
// Database
// ─────────────────────────────────────────────────────────────

function testDatabaseConnection(string $host, string $dbname, string $user, string $pass, int $port = 3306): array
{
    try {
        // Connect without dbname first to validate credentials
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_CONNECT_TIMEOUT    => 5,
        ]);
        // Check if the specific database exists
        $stmt = $pdo->prepare("SHOW DATABASES LIKE ?");
        $stmt->execute([$dbname]);
        $dbExists = $stmt->fetchColumn() !== false;
        return [
            'success'   => true,
            'db_exists' => $dbExists,
            'version'   => getServerVersion($pdo),
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getServerVersion(PDO $pdo): string
{
    try {
        return $pdo->query("SELECT VERSION()")->fetchColumn();
    } catch (Exception $e) {
        return 'unknown';
    }
}

function createDatabase(string $host, string $dbname, string $user, string $pass, int $port = 3306): array
{
    try {
        // Connect without dbname to be able to CREATE it
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $safeName = '`' . str_replace('`', '', $dbname) . '`';
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$safeName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function createTables(string $host, string $dbname, string $user, string $pass, int $port = 3306): array
{
    $sqlFile = __DIR__ . '/install.sql';
    if (!file_exists($sqlFile)) {
        return ['success' => false, 'error' => 'install.sql not found.'];
    }

    try {
        $dsn = "mysql:host={$host};dbname={$dbname};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $sql        = file_get_contents($sqlFile);
        $statements = parseSql($sql);
        $results    = [];

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;
            try {
                $pdo->exec($stmt);
                // Extract a short label from the statement
                if (preg_match('/^(CREATE TABLE.*?`(\w+)`|INSERT|ALTER|SET)/i', $stmt, $m)) {
                    $label = isset($m[2]) ? 'Create table: ' . $m[2] : substr($stmt, 0, 60);
                } else {
                    $label = substr($stmt, 0, 60);
                }
                $results[] = ['label' => $label, 'status' => 'ok'];
            } catch (PDOException $e) {
                // Ignore "duplicate key" (1061) and "already exists" (1050) errors
                $code = (int)$e->getCode();
                if (in_array($code, [1050, 1061, 1068, 1060], true) ||
                    strpos($e->getMessage(), 'already exists') !== false ||
                    strpos($e->getMessage(), 'Duplicate') !== false) {
                    continue;
                }
                return ['success' => false, 'error' => $e->getMessage(), 'results' => $results];
            }
        }
        return ['success' => true, 'results' => $results];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Split a SQL file into individual statements, ignoring comment lines.
 */
function parseSql(string $sql): array
{
    // Remove single-line comments
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    // Split on semicolons
    $parts = explode(';', $sql);
    return array_filter(array_map('trim', $parts));
}

// ─────────────────────────────────────────────────────────────
// Admin user
// ─────────────────────────────────────────────────────────────

function createAdminUser(
    string $host, string $dbname, string $user, string $pass, int $port,
    string $name, string $username, string $email, string $password
): array {
    try {
        $dsn = "mysql:host={$host};dbname={$dbname};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Check duplicate email / username
        $row = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
        $row->execute([$email, $username]);
        if ($row->fetch()) {
            return ['success' => false, 'error' => 'A user with that email or username already exists.'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (name, username, email, password, role, is_active) VALUES (?, ?, ?, ?, 'superadmin', 1)"
        );
        $stmt->execute([$name, $username, $email, $hash]);
        return ['success' => true, 'user_id' => (int)$pdo->lastInsertId()];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ─────────────────────────────────────────────────────────────
// .env generation
// ─────────────────────────────────────────────────────────────

/**
 * Write a minimal .env immediately after Step 2 so the app can connect
 * to the database for the remaining installation steps.
 * Called right after DB credentials are verified.
 */
function writeEnvFromDbStep(array $data): array
{
    $root    = dirname(__DIR__);
    $envPath = $root . '/.env';

    // If .env already has content, read it and merge; otherwise start fresh
    $existing = [];
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $existing[trim($k)] = trim($v);
        }
    }

    // Merge/override with DB step values
    $appUrl = rtrim($data['app_url'] ?? '', '/');
    $existing['APP_NAME']     = $existing['APP_NAME']     ?? '"Canada FinTech Symposium"';
    $existing['APP_URL']      = '"' . $appUrl . '"';
    $existing['APP_VERSION']  = $existing['APP_VERSION']  ?? '"2.0.0"';
    $existing['APP_KEY']      = $existing['APP_KEY']      ?? ('"' . bin2hex(random_bytes(32)) . '"');
    $existing['DB_HOST']      = $data['db_host'] ?? 'localhost';
    $existing['DB_NAME']      = $data['db_name'] ?? '';
    $existing['DB_USER']      = $data['db_user'] ?? '';
    $existing['DB_PASS']      = $data['db_pass'] ?? '';
    $existing['DB_PORT']      = $data['db_port'] ?? '3306';
    $existing['SESSION_NAME'] = $existing['SESSION_NAME'] ?? 'hts_session';

    $content = "# Auto-generated by installer — do not edit manually\n";
    foreach ($existing as $k => $v) {
        $content .= "{$k}={$v}\n";
    }

    if (file_put_contents($envPath, $content, LOCK_EX) === false) {
        return ['success' => false, 'error' => 'Cannot write .env — check folder permissions on: ' . $root];
    }
    @chmod($envPath, 0640);
    return ['success' => true, 'path' => $envPath];
}

function generateEnvFile(array $data): array
{
    $root    = dirname(__DIR__);
    $envPath = $root . '/.env';

    // Read existing .env (written in Step 2) so we can merge rather than overwrite
    $existing = [];
    if (file_exists($envPath)) {
        $rawLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($rawLines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $existing[trim($k)] = trim($v);
        }
    }

    // Preserve APP_KEY from existing .env; generate one only if absent
    $appKey = $existing['APP_KEY'] ?? ('"' . bin2hex(random_bytes(32)) . '"');

    // Override / set all keys from the final step data
    $existing['APP_NAME']    = '"' . ($data['app_name'] ?? 'Canada FinTech Symposium') . '"';
    $existing['APP_URL']     = '"' . rtrim($data['app_url'] ?? '', '/') . '"';
    $existing['APP_VERSION'] = '"2.0.0"';
    $existing['APP_KEY']     = $appKey;
    $existing['DB_HOST']     = $data['db_host'] ?? 'localhost';
    $existing['DB_NAME']     = $data['db_name'] ?? '';
    $existing['DB_USER']     = $data['db_user'] ?? '';
    $existing['DB_PASS']     = $data['db_pass'] ?? '';
    $existing['DB_PORT']     = $data['db_port'] ?? '3306';
    $existing['SESSION_NAME'] = 'hts_session';

    // Email
    $emailProvider = $data['email_provider'] ?? 'skip';
    if ($emailProvider === 'brevo') {
        $existing['BREVO_API_KEY'] = $data['brevo_api_key'] ?? '';
    } elseif ($emailProvider === 'ms365') {
        $existing['MS_OAUTH_CLIENT_ID']     = $data['ms_client_id'] ?? '';
        $existing['MS_OAUTH_CLIENT_SECRET'] = $data['ms_client_secret'] ?? '';
        $existing['MS_OAUTH_TENANT_ID']     = $data['ms_tenant_id'] ?? 'common';
        $existing['MS_OAUTH_REDIRECT_URI']  = rtrim($data['app_url'] ?? '', '/') . '/api/msgraph/callback.php';
        $existing['SMTP_HOST']       = 'smtp-mail.outlook.com';
        $existing['SMTP_PORT']       = '587';
        $existing['SMTP_SECURE']     = 'tls';
        $existing['SMTP_USER']       = $data['ms_smtp_user'] ?? '';
        $existing['SMTP_PASS']       = $data['ms_smtp_pass'] ?? '';
        $existing['SMTP_FROM_EMAIL'] = $data['ms_smtp_user'] ?? '';
        $existing['SMTP_FROM_NAME']  = '"' . ($data['app_name'] ?? 'Canada FinTech Symposium') . '"';
    } elseif ($emailProvider === 'smtp') {
        $existing['SMTP_HOST']       = $data['smtp_host'] ?? '';
        $existing['SMTP_PORT']       = $data['smtp_port'] ?? '587';
        $existing['SMTP_SECURE']     = $data['smtp_secure'] ?? 'tls';
        $existing['SMTP_USER']       = $data['smtp_user'] ?? '';
        $existing['SMTP_PASS']       = $data['smtp_pass'] ?? '';
        $existing['SMTP_FROM_EMAIL'] = $data['smtp_from_email'] ?? $data['smtp_user'] ?? '';
        $existing['SMTP_FROM_NAME']  = '"' . ($data['app_name'] ?? 'Canada FinTech Symposium') . '"';
    }

    // n8n
    if (!empty($data['n8n_url'])) {
        $existing['N8N_URL']         = $data['n8n_url'];
        $existing['N8N_API_KEY']     = $data['n8n_api_key'] ?? '';
        $existing['N8N_WEBHOOK_URL'] = $data['n8n_webhook_url'] ?? '';
    }

    // Apollo
    if (!empty($data['apollo_api_key'])) {
        $existing['APOLLO_API_KEY'] = $data['apollo_api_key'];
    }

    $content = "# Auto-generated by installer — do not edit manually\n";
    foreach ($existing as $k => $v) {
        $content .= "{$k}={$v}\n";
    }

    if (file_put_contents($envPath, $content, LOCK_EX) === false) {
        return ['success' => false, 'error' => 'Cannot write .env file. Check permissions on: ' . $root];
    }
    @chmod($envPath, 0644);
    return ['success' => true, 'path' => $envPath];
}

// ─────────────────────────────────────────────────────────────
// Lock file
// ─────────────────────────────────────────────────────────────

function lockInstallation(): bool
{
    $lockFile = __DIR__ . '/install.lock';
    return file_put_contents($lockFile, date('c') . "\n", LOCK_EX) !== false;
}

function isInstallationLocked(): bool
{
    return file_exists(__DIR__ . '/install.lock');
}

// ─────────────────────────────────────────────────────────────
// Email connectivity test
// ─────────────────────────────────────────────────────────────

function testEmailConnection(array $data): array
{
    $provider = $data['email_provider'] ?? '';
    if ($provider === 'brevo') {
        return testBrevoConnection($data['brevo_api_key'] ?? '');
    }
    if ($provider === 'smtp' || $provider === 'ms365') {
        return testSmtpConnection($data);
    }
    return ['success' => false, 'error' => 'No provider selected.'];
}

function testBrevoConnection(string $apiKey): array
{
    if (!extension_loaded('curl')) {
        return ['success' => false, 'error' => 'cURL extension required.'];
    }
    $ch = curl_init('https://api.brevo.com/v3/account');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['api-key: ' . $apiKey, 'Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        $json = json_decode($body, true);
        return ['success' => true, 'info' => 'Connected as: ' . ($json['email'] ?? 'unknown')];
    }
    return ['success' => false, 'error' => 'HTTP ' . $code . ': ' . $body];
}

function testSmtpConnection(array $data): array
{
    $host   = $data['smtp_host'] ?? '';
    $port   = (int)($data['smtp_port'] ?? 587);
    $secure = $data['smtp_secure'] ?? 'tls';

    if ($secure === 'ssl') {
        $host = 'ssl://' . $host;
    }

    $errno  = 0;
    $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$fp) {
        return ['success' => false, 'error' => "Cannot connect to {$host}:{$port} — {$errstr}"];
    }
    fclose($fp);
    return ['success' => true, 'info' => "TCP connection to {$host}:{$port} succeeded."];
}

// ─────────────────────────────────────────────────────────────
// n8n connectivity test
// ─────────────────────────────────────────────────────────────

function testN8nConnection(string $url, string $apiKey): array
{
    if (!extension_loaded('curl')) {
        return ['success' => false, 'error' => 'cURL extension required.'];
    }
    $url = rtrim($url, '/') . '/api/v1/workflows';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['X-N8N-API-KEY: ' . $apiKey, 'Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        return ['success' => true, 'info' => 'n8n connected successfully.'];
    }
    return ['success' => false, 'error' => 'HTTP ' . $code . ': ' . substr($body, 0, 200)];
}

// ─────────────────────────────────────────────────────────────
// Apollo connectivity test
// ─────────────────────────────────────────────────────────────

function testApolloConnection(string $apiKey): array
{
    if (!extension_loaded('curl')) {
        return ['success' => false, 'error' => 'cURL extension required.'];
    }
    $ch = curl_init('https://api.apollo.io/api/v1/auth/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Cache-Control: no-cache',
            'X-Api-Key: ' . $apiKey,
        ],
        CURLOPT_HTTPGET        => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        $json = json_decode($body, true);
        if (!empty($json['is_logged_in'])) {
            return ['success' => true, 'info' => 'Apollo API key is valid.'];
        }
        // Some valid keys return 200 without is_logged_in field
        return ['success' => true, 'info' => 'Apollo connected successfully.'];
    }
    return ['success' => false, 'error' => 'Invalid API key or unreachable (HTTP ' . $code . ').'];
}

// ─────────────────────────────────────────────────────────────
// Input validation helpers
// ─────────────────────────────────────────────────────────────

function validateInput(array $data): array
{
    $errors = [];
    foreach ($data as $field => $rules) {
        [$value, $ruleList] = $rules;
        foreach ($ruleList as $rule) {
            if ($rule === 'required' && trim((string)$value) === '') {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
                break;
            }
            if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = 'Invalid email address.';
                break;
            }
            if (strpos($rule, 'min:') === 0) {
                $min = (int)substr($rule, 4);
                if (strlen((string)$value) < $min) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters.";
                    break;
                }
            }
            if (strpos($rule, 'max:') === 0) {
                $max = (int)substr($rule, 4);
                if (strlen((string)$value) > $max) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be at most {$max} characters.";
                    break;
                }
            }
            if ($rule === 'alphanumeric_underscore' && !preg_match('/^[a-zA-Z0-9_]+$/', (string)$value)) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' may only contain letters, numbers, and underscores.';
                break;
            }
            if ($rule === 'strong_password') {
                $v = (string)$value;
                if (!preg_match('/[A-Z]/', $v)) { $errors[$field] = 'Password must contain at least one uppercase letter.'; break; }
                if (!preg_match('/[a-z]/', $v)) { $errors[$field] = 'Password must contain at least one lowercase letter.'; break; }
                if (!preg_match('/[0-9]/', $v)) { $errors[$field] = 'Password must contain at least one number.'; break; }
                if (!preg_match('/[\W_]/', $v)) { $errors[$field] = 'Password must contain at least one special character.'; break; }
            }
        }
    }
    return $errors;
}

function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
