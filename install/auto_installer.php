<?php
$error   = '';
$success = '';
$step    = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost  = trim($_POST['db_host']  ?? 'localhost');
    $dbName  = trim($_POST['db_name']  ?? '');
    $dbUser  = trim($_POST['db_user']  ?? '');
    $dbPass  = trim($_POST['db_pass']  ?? '');
    $appUrl  = rtrim(trim($_POST['app_url'] ?? ''), '/');
    $saEmail = trim($_POST['sa_email'] ?? '');
    $saPass  = trim($_POST['sa_pass']  ?? '');
    $adEmail = trim($_POST['ad_email'] ?? '');
    $adPass  = trim($_POST['ad_pass']  ?? '');

    if (!$dbName || !$dbUser || !$saEmail || !$saPass) {
        $error = 'Please fill all required fields.';
    } else {
        try {
            // Connect without db first
            $pdo = new PDO(
                "mysql:host=$dbHost;charset=utf8mb4",
                $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Create DB
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            // Create tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(100) NOT NULL,
              `email` varchar(150) NOT NULL,
              `password` varchar(255) NOT NULL,
              `role` enum('superadmin','admin') DEFAULT 'admin',
              `is_active` tinyint(1) DEFAULT 1,
              `last_login` datetime DEFAULT NULL,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `leads` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `first_name` varchar(100) DEFAULT '',
              `last_name` varchar(100) DEFAULT '',
              `full_name` varchar(200) DEFAULT '',
              `email` varchar(200) NOT NULL,
              `company` varchar(200) DEFAULT '',
              `job_title` varchar(200) DEFAULT '',
              `role` varchar(100) DEFAULT '',
              `segment` enum('Healthcare Providers','Health IT & Digital Health','Pharmaceutical & Biotech','Medical Devices','Venture Capital / Investors','HealthTech Startups','Other') DEFAULT 'Other',
              `country` varchar(100) DEFAULT 'Canada',
              `province` varchar(100) DEFAULT '',
              `city` varchar(100) DEFAULT '',
              `source` varchar(100) DEFAULT 'Manual',
              `status` enum('new','emailed','responded','converted','unsubscribed','bounced') DEFAULT 'new',
              `linkedin_url` varchar(500) DEFAULT '',
              `notes` text,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `email_templates` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(200) NOT NULL,
              `subject` varchar(500) NOT NULL,
              `html_body` longtext NOT NULL,
              `is_default` tinyint(1) DEFAULT 0,
              `created_by` int(11) DEFAULT NULL,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `campaigns` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `campaign_key` varchar(100) NOT NULL,
              `name` varchar(300) NOT NULL,
              `template_id` int(11) DEFAULT NULL,
              `filter_segment` varchar(100) DEFAULT '',
              `filter_role` varchar(100) DEFAULT '',
              `filter_province` varchar(100) DEFAULT '',
              `total_leads` int(11) DEFAULT 0,
              `sent_count` int(11) DEFAULT 0,
              `failed_count` int(11) DEFAULT 0,
              `status` enum('draft','running','completed','paused') DEFAULT 'draft',
              `test_mode` tinyint(1) DEFAULT 0,
              `created_by` int(11) DEFAULT NULL,
              `started_at` datetime DEFAULT NULL,
              `completed_at` datetime DEFAULT NULL,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `campaign_key` (`campaign_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `email_logs` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `campaign_id` int(11) DEFAULT NULL,
              `lead_id` int(11) DEFAULT NULL,
              `recipient_email` varchar(200) NOT NULL,
              `recipient_name` varchar(200) DEFAULT '',
              `subject` varchar(500) DEFAULT '',
              `status` enum('queued','sent','failed','bounced','opened','delivered') DEFAULT 'queued',
              `message_id` varchar(500) DEFAULT '',
              `error_message` text,
              `follow_up_sequence` tinyint(1) DEFAULT 1,
              `opened_at` datetime DEFAULT NULL,
              `sent_at` datetime DEFAULT NULL,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `responses` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `lead_id` int(11) DEFAULT NULL,
              `campaign_id` int(11) DEFAULT NULL,
              `from_email` varchar(200) NOT NULL,
              `from_name` varchar(200) DEFAULT '',
              `subject` varchar(500) DEFAULT '',
              `body_text` text,
              `body_html` longtext,
              `message_id` varchar(500) DEFAULT NULL,
              `response_type` enum('interested','not_interested','more_info','wrong_person','auto_reply','bounce','other') DEFAULT 'other',
              `is_read` tinyint(1) DEFAULT 0,
              `is_replied` tinyint(1) DEFAULT 0,
              `received_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `message_id` (`message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `response_replies` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `response_id` int(11) NOT NULL,
              `replied_by` int(11) DEFAULT NULL,
              `reply_subject` varchar(500) DEFAULT '',
              `reply_body` text,
              `message_id` varchar(500) DEFAULT '',
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_response` (`response_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `oauth_accounts` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `provider` varchar(50) NOT NULL DEFAULT 'microsoft',
              `email` varchar(200) NOT NULL,
              `access_token` text NOT NULL,
              `refresh_token` text NOT NULL,
              `token_expires_at` datetime DEFAULT NULL,
              `scopes` varchar(500) DEFAULT '',
              `connected_by` int(11) DEFAULT NULL,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `provider_email` (`provider`, `email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `reply_threads` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `lead_id` int(11) DEFAULT NULL,
              `campaign_id` int(11) DEFAULT NULL,
              `subject` varchar(500) DEFAULT '',
              `conversation_id` varchar(500) DEFAULT '',
              `last_message_at` datetime DEFAULT NULL,
              `message_count` int(11) DEFAULT 1,
              `status` enum('active','closed','archived') DEFAULT 'active',
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_lead` (`lead_id`),
              KEY `idx_campaign` (`campaign_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) DEFAULT NULL,
              `action` varchar(100) NOT NULL,
              `entity_type` varchar(50) DEFAULT '',
              `entity_id` int(11) DEFAULT NULL,
              `details` text,
              `ip_address` varchar(45) DEFAULT '',
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_user` (`user_id`),
              KEY `idx_action` (`action`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Insert superadmin
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (name,email,password,role) VALUES(?,?,?,?)");
            $stmt->execute(['Super Admin', $saEmail, password_hash($saPass, PASSWORD_BCRYPT), 'superadmin']);

            // Insert optional admin
            if ($adEmail && $adPass) {
                $stmt->execute(['Admin', $adEmail, password_hash($adPass, PASSWORD_BCRYPT), 'admin']);
            }

            // Default template
            $tplBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}.wrap{max-width:600px;margin:0 auto;background:#fff}.header{background:linear-gradient(135deg,#CC0000,#0a1628);padding:40px 30px;text-align:center}.header h1{color:#fff;font-size:24px;margin:0}.header p{color:rgba(255,255,255,.7);font-style:italic;margin:8px 0 0}.body{padding:30px}.body h2{color:#0d6efd}.body p{color:#555;line-height:1.7}.cta{text-align:center;margin:30px 0}.cta a{background:#0d6efd;color:#fff;padding:14px 32px;text-decoration:none;border-radius:8px;font-weight:bold}.footer{background:#f9f9f9;padding:20px;text-align:center;font-size:12px;color:#999}</style></head><body><div class="wrap"><div class="header"><h1>Canada HealthTech Symposium</h1><p>Igniting the Future of Health</p></div><div class="body"><h2>Dear {{first_name}},</h2><p>As <strong>{{role}}</strong> at <strong>{{company}}</strong>, your expertise makes you an ideal participant.</p><div class="cta"><a href="' . $appUrl . '/register">Register Now</a></div><p>Best regards,<br>Canada HealthTech Symposium Team</p></div><div class="footer"><a href="{{unsubscribe_link}}">Unsubscribe</a></div></div></body></html>';
            $pdo->prepare("INSERT IGNORE INTO email_templates (name,subject,html_body,is_default) VALUES(?,?,?,1)")
                ->execute(['HealthTech Symposium 2026 Invitation','Exclusive Invitation: Canada HealthTech Symposium 2026', $tplBody]);

            // Write config files
            $dbConfig = "<?php\nrequire_once __DIR__ . '/../includes/env_loader.php';\nloadEnv(__DIR__ . '/../.env');\n\nclass Database {\n    private static ?PDO \$instance = null;\n\n    public static function getConnection(): PDO {\n        if (self::\$instance === null) {\n            \$host    = \$_ENV['DB_HOST']    ?? 'localhost';\n            \$dbname  = \$_ENV['DB_NAME']    ?? '';\n            \$user    = \$_ENV['DB_USER']    ?? '';\n            \$pass    = \$_ENV['DB_PASS']    ?? '';\n            \$charset = 'utf8mb4';\n            \$dsn = \"mysql:host={\$host};dbname={\$dbname};charset={\$charset}\";\n            self::\$instance = new PDO(\$dsn, \$user, \$pass, [\n                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n                PDO::ATTR_EMULATE_PREPARES   => false,\n            ]);\n        }\n        return self::\$instance;\n    }\n    public static function query(string \$sql, array \$p = []): PDOStatement {\n        \$s = self::getConnection()->prepare(\$sql);\n        \$s->execute(\$p);\n        return \$s;\n    }\n    public static function fetchAll(string \$sql, array \$p = []): array { return self::query(\$sql, \$p)->fetchAll(); }\n    public static function fetchOne(string \$sql, array \$p = []): ?array { \$r = self::query(\$sql, \$p)->fetch(); return \$r ?: null; }\n    public static function lastInsertId(): string { return self::getConnection()->lastInsertId(); }\n}\n";

            // Write .env file
            $envContent = "APP_NAME=\"Canada HealthTech Symposium\"\n";
            $envContent .= "APP_URL=\"$appUrl\"\n";
            $envContent .= "APP_VERSION=\"2.0.0\"\n\n";
            $envContent .= "DB_HOST=$dbHost\n";
            $envContent .= "DB_NAME=$dbName\n";
            $envContent .= "DB_USER=$dbUser\n";
            $envContent .= "DB_PASS=$dbPass\n\n";
            $envContent .= "SMTP_HOST=smtp-relay.brevo.com\n";
            $envContent .= "SMTP_PORT=587\n";
            $envContent .= "SMTP_SECURE=tls\n";
            $envContent .= "SMTP_USER=\n";
            $envContent .= "SMTP_PASS=\n";
            $envContent .= "SMTP_FROM_EMAIL=\n";
            $envContent .= "SMTP_FROM_NAME=\"Canada HealthTech Symposium\"\n\n";
            $envContent .= "IMAP_HOST=\n";
            $envContent .= "IMAP_USER=\n";
            $envContent .= "IMAP_PASS=\n\n";
            $envContent .= "BREVO_API_KEY=\n\n";
            $envContent .= "MS_OAUTH_CLIENT_ID=\n";
            $envContent .= "MS_OAUTH_CLIENT_SECRET=\n";
            $envContent .= "MS_OAUTH_TENANT_ID=common\n";
            $envContent .= "MS_OAUTH_REDIRECT_URI=$appUrl/api/msgraph/callback.php\n\n";
            $envContent .= "N8N_API_KEY=change_me_secure_key\n";
            $envContent .= "SESSION_NAME=hts_session\n";

            $appConfig = "<?php\nrequire_once __DIR__ . '/../includes/env_loader.php';\nloadEnv(__DIR__ . '/../.env');\n\ndefine('APP_NAME',        \$_ENV['APP_NAME']        ?? 'Canada HealthTech Symposium');\ndefine('APP_URL',         \$_ENV['APP_URL']          ?? '$appUrl');\ndefine('APP_VERSION',     \$_ENV['APP_VERSION']      ?? '2.0.0');\n\ndefine('SMTP_HOST',       \$_ENV['SMTP_HOST']        ?? 'smtp-relay.brevo.com');\ndefine('SMTP_PORT',       (int)(\$_ENV['SMTP_PORT']  ?? 587));\ndefine('SMTP_SECURE',     \$_ENV['SMTP_SECURE']      ?? 'tls');\ndefine('SMTP_USER',       \$_ENV['SMTP_USER']        ?? '');\ndefine('SMTP_PASS',       \$_ENV['SMTP_PASS']        ?? '');\ndefine('SMTP_FROM_EMAIL', \$_ENV['SMTP_FROM_EMAIL']  ?? '');\ndefine('SMTP_FROM_NAME',  \$_ENV['SMTP_FROM_NAME']   ?? APP_NAME);\n\ndefine('IMAP_HOST',       \$_ENV['IMAP_HOST']        ?? '');\ndefine('IMAP_USER',       \$_ENV['IMAP_USER']        ?? '');\ndefine('IMAP_PASS',       \$_ENV['IMAP_PASS']        ?? '');\n\ndefine('BREVO_API_KEY',          \$_ENV['BREVO_API_KEY']         ?? '');\ndefine('MS_OAUTH_CLIENT_ID',     \$_ENV['MS_OAUTH_CLIENT_ID']    ?? '');\ndefine('MS_OAUTH_CLIENT_SECRET', \$_ENV['MS_OAUTH_CLIENT_SECRET']?? '');\ndefine('MS_OAUTH_TENANT_ID',     \$_ENV['MS_OAUTH_TENANT_ID']    ?? 'common');\ndefine('MS_OAUTH_REDIRECT_URI',  \$_ENV['MS_OAUTH_REDIRECT_URI'] ?? '');\n\ndefine('N8N_API_KEY',     \$_ENV['N8N_API_KEY']      ?? '');\ndefine('SESSION_NAME',    \$_ENV['SESSION_NAME']     ?? 'hts_session');\n\nsession_name(SESSION_NAME);\nif (session_status() === PHP_SESSION_NONE) session_start();\n";

            file_put_contents(__DIR__ . '/../config/database.php', $dbConfig);
            file_put_contents(__DIR__ . '/../config/config.php',   $appConfig);
            file_put_contents(__DIR__ . '/../.env',                $envContent);

            $step = 2;
            $success = 'Installation successful!';

        } catch (Exception $e) {
            $error = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Auto Installer — Canada HealthTech Symposium</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',Arial,sans-serif;background:#0a0f1a;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#13213a;border:1px solid #1e3355;border-radius:16px;padding:40px;max-width:560px;width:100%}
h1{font-size:24px;font-weight:700;margin-bottom:6px;color:#e2e8f0}
.sub{color:#8a9ab5;font-size:13px;margin-bottom:28px}
.group{margin-bottom:16px}
label{display:block;font-size:13px;color:#8a9ab5;margin-bottom:6px}
input{width:100%;background:#0d1f38;border:1px solid #1e3355;border-radius:8px;padding:11px 14px;color:#e2e8f0;font-size:14px;outline:none}
input:focus{border-color:#0d6efd}
input[readonly]{opacity:.5}
.btn{width:100%;background:#0d6efd;color:#fff;border:none;border-radius:8px;padding:14px;font-size:15px;font-weight:600;cursor:pointer;margin-top:8px}
.btn:hover{background:#0b5ed7}
.error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:14px;color:#ef4444;margin-bottom:16px;font-size:13px}
.success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);border-radius:8px;padding:14px;color:#34d399;margin-bottom:16px;font-size:13px}
.section{color:#8a9ab5;font-size:11px;text-transform:uppercase;letter-spacing:.8px;margin:20px 0 12px;padding-bottom:6px;border-bottom:1px solid #1e3355}
.cred-box{background:#0d1f38;border-radius:8px;padding:16px;margin-bottom:12px;font-size:13px}
.cred-box div{margin-bottom:4px}
.cred-box span{color:#10b981;font-weight:600}
.warn{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:12px;color:#fca5a5;font-size:12px;margin-top:16px}
a.go-btn{display:block;width:100%;background:#10b981;color:#fff;text-decoration:none;border-radius:8px;padding:14px;text-align:center;font-size:15px;font-weight:600;margin-top:16px}
</style>
</head>
<body>
<div class="card">
    <div style="text-align:center;margin-bottom:16px">
      <svg viewBox="0 0 100 100" width="52" height="52" xmlns="http://www.w3.org/2000/svg" style="filter:drop-shadow(0 2px 8px rgba(204,0,0,.4))">
        <path d="M50 5 L58 30 L75 20 L65 38 L85 35 L70 50 L80 70 L60 60 L55 85 L50 75 L45 85 L40 60 L20 70 L30 50 L15 35 L35 38 L25 20 L42 30 Z" fill="#CC0000"/>
        <text x="50" y="58" text-anchor="middle" fill="white" font-size="16" font-weight="900" font-family="Arial">CHTS</text>
      </svg>
    </div>
    <h1 style="text-align:center">Canada HealthTech Symposium</h1>
    <p class="sub" style="text-align:center">Auto Installer v2.0</p>

    <?php if ($error): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($step === 2): ?>
    <div class="success">✅ <?php echo $success; ?></div>
    <div class="cred-box">
        <div><strong>Super Admin Credentials</strong></div>
        <div style="margin-top:8px">Email: <span><?php echo htmlspecialchars($saEmail); ?></span></div>
        <div>Password: <span><?php echo htmlspecialchars($saPass); ?></span></div>
    </div>
    <?php if ($adEmail): ?>
    <div class="cred-box">
        <div><strong>Admin Credentials</strong></div>
        <div style="margin-top:8px">Email: <span><?php echo htmlspecialchars($adEmail); ?></span></div>
        <div>Password: <span><?php echo htmlspecialchars($adPass); ?></span></div>
    </div>
    <?php endif; ?>
    <a href="<?php echo htmlspecialchars($appUrl); ?>/admin/dashboard.php" class="go-btn">🚀 Go to Dashboard</a>
    <div class="warn">⚠️ <strong>Security:</strong> Please delete <code>install/auto_installer.php</code> after installation.</div>

    <?php else: ?>
    <form method="POST">
        <div class="section">Database Configuration</div>
        <div class="group">
            <label>DB Host</label>
            <input type="text" name="db_host" value="localhost" readonly>
        </div>
        <div class="group">
            <label>DB Name *</label>
            <input type="text" name="db_name" placeholder="healthtech_db" required>
        </div>
        <div class="group">
            <label>DB User *</label>
            <input type="text" name="db_user" placeholder="root" required>
        </div>
        <div class="group">
            <label>DB Password</label>
            <input type="password" name="db_pass" placeholder="(leave empty if no password)">
        </div>

        <div class="section">Application</div>
        <div class="group">
            <label>App URL *</label>
            <input type="url" name="app_url" value="https://yourdomain.com/healthtech" placeholder="https://yourdomain.com/healthtech" required>
        </div>

        <div class="section">Super Admin Account *</div>
        <div class="group">
            <label>Super Admin Email *</label>
            <input type="email" name="sa_email" value="sm@101bdtech.com" required>
        </div>
        <div class="group">
            <label>Super Admin Password *</label>
            <input type="password" name="sa_pass" placeholder="Enter a strong password" required>
        </div>

        <div class="section">Admin Account (Optional)</div>
        <div class="group">
            <label>Admin Email</label>
            <input type="email" name="ad_email" placeholder="admin@yourdomain.com">
        </div>
        <div class="group">
            <label>Admin Password</label>
            <input type="password" name="ad_pass" placeholder="(optional)">
        </div>

        <button type="submit" class="btn">🚀 Install Now</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
