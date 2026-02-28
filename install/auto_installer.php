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
              `segment` enum('Financial Institutions','Technology & Solution Providers','Venture Capital / Investors','FinTech Startups','Other') DEFAULT 'Other',
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
              `status` enum('queued','sent','failed','bounced','opened') DEFAULT 'queued',
              `message_id` varchar(500) DEFAULT '',
              `error_message` text,
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
              `response_type` enum('interested','not_interested','more_info','auto_reply','bounce','other') DEFAULT 'other',
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
              `reply_body` longtext,
              `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Insert superadmin
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (name,email,password,role) VALUES(?,?,?,?)");
            $stmt->execute(['Super Admin', $saEmail, password_hash($saPass, PASSWORD_BCRYPT), 'superadmin']);

            // Insert optional admin
            if ($adEmail && $adPass) {
                $stmt->execute(['Admin', $adEmail, password_hash($adPass, PASSWORD_BCRYPT), 'admin']);
            }

            // Default template
            $tplBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}.wrap{max-width:600px;margin:0 auto;background:#fff}.header{background:linear-gradient(135deg,#0a1628,#1a237e);padding:40px 30px 30px;text-align:center}.body{padding:30px}.body h2{color:#0d6efd}.body p{color:#555;line-height:1.7}.cta{text-align:center;margin:30px 0}.cta a{background:#0d6efd;color:#fff;padding:14px 32px;text-decoration:none;border-radius:8px;font-weight:bold}.footer{background:#f9f9f9;padding:20px;text-align:center;font-size:12px;color:#999}</style></head><body><div class="wrap"><div class="header"><img src="' . $appUrl . '/assets/images/cfts-logo.png" alt="Canada FinTech Symposium" style="width:260px;background:white;padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block;margin-left:auto;margin-right:auto;"><p style="color:rgba(255,255,255,0.7);margin:0;font-size:13px;">April 21–22, 2026 · Toronto, Canada</p></div><div class="body"><h2>Dear {{first_name}},</h2><p>As <strong>{{role}}</strong> at <strong>{{company}}</strong>, your expertise in driving financial innovation in {{city}} makes you an ideal participant for Canada\'s premier FinTech gathering.</p><div class="cta"><a href="' . $appUrl . '/register">Register Now</a></div><p>Best regards,<br>Canada FinTech Symposium Team</p></div><div class="footer"><a href="{{unsubscribe_link}}">Unsubscribe</a></div></div></body></html>';
            $pdo->prepare("INSERT IGNORE INTO email_templates (name,subject,html_body,is_default) VALUES(?,?,?,1)")
                ->execute(['Canada FinTech Symposium 2026 Invitation','Exclusive Invitation: Canada FinTech Symposium 2026', $tplBody]);

            // Write config files
            $dbConfig = "<?php\nclass Database {\n    private static ?PDO \$instance = null;\n    private static array \$config = [\n        'host'    => '$dbHost',\n        'dbname'  => '$dbName',\n        'user'    => '$dbUser',\n        'pass'    => '$dbPass',\n        'charset' => 'utf8mb4',\n    ];\n    public static function getConnection(): PDO {\n        if (self::\$instance === null) {\n            \$c = self::\$config;\n            \$dsn = \"mysql:host={\$c['host']};dbname={\$c['dbname']};charset={\$c['charset']}\";\n            self::\$instance = new PDO(\$dsn, \$c['user'], \$c['pass'], [\n                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n                PDO::ATTR_EMULATE_PREPARES   => false,\n            ]);\n        }\n        return self::\$instance;\n    }\n    public static function query(string \$sql, array \$p = []): PDOStatement {\n        \$s = self::getConnection()->prepare(\$sql);\n        \$s->execute(\$p);\n        return \$s;\n    }\n    public static function fetchAll(string \$sql, array \$p = []): array { return self::query(\$sql, \$p)->fetchAll(); }\n    public static function fetchOne(string \$sql, array \$p = []): ?array { \$r = self::query(\$sql, \$p)->fetch(); return \$r ?: null; }\n    public static function lastInsertId(): string { return self::getConnection()->lastInsertId(); }\n}\n";

            $appConfig = "<?php\ndefine('APP_NAME',        'Canada FinTech Symposium');\ndefine('APP_SHORT',       'CFTS');\ndefine('APP_TAGLINE',     'Igniting the Future of Finance');\ndefine('APP_URL',         '$appUrl');\ndefine('APP_VERSION',     '2.0.0');\ndefine('SMTP_HOST',       'mail.101bdtech.com');\ndefine('SMTP_PORT',       465);\ndefine('SMTP_SECURE',     'ssl');\ndefine('SMTP_USER',       'sm@101bdtech.com');\ndefine('SMTP_PASS',       'Nurnobi131221');\ndefine('SMTP_FROM_EMAIL', 'sm@101bdtech.com');\ndefine('SMTP_FROM_NAME',  'Canada FinTech Symposium');\ndefine('IMAP_HOST',       '{mail.101bdtech.com:993/imap/ssl/novalidate-cert}INBOX');\ndefine('IMAP_USER',       'sm@101bdtech.com');\ndefine('IMAP_PASS',       'Nurnobi131221');\ndefine('N8N_API_KEY',     'HTS2026Key');\ndefine('SESSION_NAME',    'hts_session');\nsession_name(SESSION_NAME);\nif (session_status() === PHP_SESSION_NONE) session_start();\n";

            file_put_contents(__DIR__ . '/../config/database.php', $dbConfig);
            file_put_contents(__DIR__ . '/../config/config.php',   $appConfig);

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
<title>Auto Installer — Canada FinTech Symposium</title>
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
    <div style="text-align:center; margin-bottom:28px;">
        <img src="../assets/images/cfts-logo.png"
             alt="Canada FinTech Symposium"
             style="width:300px; max-width:90%; background:white; padding:16px 20px; border-radius:10px; box-shadow:0 4px 24px rgba(204,0,0,0.3);">
        <h2 style="color:#ffffff; margin-top:16px; font-size:20px;">Installation Setup</h2>
        <p style="color:rgba(255,255,255,0.6); font-size:13px;">Canada FinTech Symposium 2026</p>
    </div>

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
