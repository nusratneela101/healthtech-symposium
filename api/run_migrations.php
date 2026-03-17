<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
/**
 * Run pending migrations for existing installations.
 * Restricted to SuperAdmin users only.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$authenticated = false;
if (!empty($_SESSION['user_id'])) {
    $user = Database::fetchOne("SELECT role FROM users WHERE id=?", [$_SESSION['user_id']]);
    if ($user && $user['role'] === 'superadmin') {
        $authenticated = true;
    }
}
if (!$authenticated) {
    $apiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!empty($apiKey) && defined('N8N_API_KEY') && hash_equals(N8N_API_KEY, $apiKey)) {
        $authenticated = true;
    }
}
if (!$authenticated) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$results = [];

$migrations = [
    [
        'name' => 'site_settings',
        'sql'  => "CREATE TABLE IF NOT EXISTS `site_settings` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `setting_key` varchar(100) NOT NULL,
          `setting_value` text,
          `setting_group` varchar(50) DEFAULT 'general',
          `updated_by` int(11) DEFAULT NULL,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'notifications',
        'sql'  => "CREATE TABLE IF NOT EXISTS `notifications` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) DEFAULT NULL,
          `title` varchar(200) NOT NULL DEFAULT '',
          `message` text NOT NULL DEFAULT '',
          `type` enum('info','success','warning','error') DEFAULT 'info',
          `is_read` tinyint(1) DEFAULT 0,
          `link` varchar(500) DEFAULT NULL,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `is_read` (`is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'notifications.title_column',
        'sql'  => "ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `title` varchar(200) NOT NULL DEFAULT ''",
    ],
    [
        'name' => 'notifications.type_column',
        'sql'  => "ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `type` enum('info','success','warning','error') DEFAULT 'info'",
    ],
    [
        'name' => 'lead_collections',
        'sql'  => "CREATE TABLE IF NOT EXISTS `lead_collections` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `source` varchar(100) DEFAULT 'Apollo',
          `total_fetched` int(11) DEFAULT 0,
          `total_saved` int(11) DEFAULT 0,
          `total_skipped` int(11) DEFAULT 0,
          `total_duplicates` int(11) DEFAULT 0,
          `status` enum('running','done','failed','completed','pending') DEFAULT 'running',
          `search_params` text,
          `started_at` datetime DEFAULT NULL,
          `completed_at` datetime DEFAULT NULL,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `status` (`status`),
          KEY `started_at` (`started_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'lead_collection_items',
        'sql'  => "CREATE TABLE IF NOT EXISTS `lead_collection_items` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `collection_id` int(11) NOT NULL,
          `lead_id` int(11) NOT NULL,
          `action` enum('created','skipped','duplicate') DEFAULT 'created',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_collection` (`collection_id`),
          KEY `idx_lead` (`lead_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'lead_tags',
        'sql'  => "CREATE TABLE IF NOT EXISTS `lead_tags` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL,
          `color` varchar(7) DEFAULT '#0d6efd',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'lead_tag_map',
        'sql'  => "CREATE TABLE IF NOT EXISTS `lead_tag_map` (
          `lead_id` int(11) NOT NULL,
          `tag_id`  int(11) NOT NULL,
          PRIMARY KEY (`lead_id`, `tag_id`),
          KEY `idx_ltm_lead` (`lead_id`),
          KEY `idx_ltm_tag`  (`tag_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'lead_tag_map.fk_lead',
        'sql'  => "ALTER TABLE `lead_tag_map` ADD CONSTRAINT `fk_ltm_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE",
    ],
    [
        'name' => 'lead_tag_map.fk_tag',
        'sql'  => "ALTER TABLE `lead_tag_map` ADD CONSTRAINT `fk_ltm_tag` FOREIGN KEY (`tag_id`) REFERENCES `lead_tags`(`id`) ON DELETE CASCADE",
    ],
    [
        'name' => 'rate_limits',
        'sql'  => "CREATE TABLE IF NOT EXISTS `rate_limits` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `identifier` varchar(200) NOT NULL,
          `endpoint` varchar(200) NOT NULL,
          `requests` int DEFAULT 1,
          `window_start` datetime NOT NULL,
          PRIMARY KEY (`id`),
          KEY `identifier_endpoint` (`identifier`, `endpoint`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'campaigns.scheduled_at',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `scheduled_at` datetime DEFAULT NULL",
    ],
    [
        'name' => 'campaigns.scheduled_by',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `scheduled_by` int(11) DEFAULT NULL",
    ],
    [
        'name' => 'campaigns.status_scheduled',
        'sql'  => "ALTER TABLE `campaigns` MODIFY COLUMN `status` enum('draft','scheduled','running','completed','paused') DEFAULT 'draft'",
    ],
    [
        'name' => 'responses.hot_alert_sent',
        'sql'  => "ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `hot_alert_sent` tinyint(1) NOT NULL DEFAULT 0",
    ],
    [
        'name' => 'leads.score',
        'sql'  => "ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `score` int DEFAULT 0",
    ],
    [
        'name' => 'email_templates.signature_html',
        'sql'  => "ALTER TABLE email_templates ADD COLUMN IF NOT EXISTS signature_html LONGTEXT",
    ],
    [
        'name' => 'email_templates.attachment_path',
        'sql'  => "ALTER TABLE email_templates ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(500) DEFAULT NULL",
    ],
    [
        'name' => 'email_templates.header_image_url',
        'sql'  => "ALTER TABLE email_templates ADD COLUMN IF NOT EXISTS header_image_url VARCHAR(500) DEFAULT NULL",
    ],
    [
        'name' => 'email_logs.follow_up_sequence',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `follow_up_sequence` INT DEFAULT 1",
    ],
    [
        'name' => 'email_logs.opened_at',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `opened_at` DATETIME NULL",
    ],
    [
        'name' => 'email_logs.message_id',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `message_id` VARCHAR(255) NULL",
    ],
    [
        'name' => 'email_logs.error_message',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `error_message` TEXT NULL",
    ],
    [
        'name' => 'email_logs.sent_at',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `sent_at` DATETIME NULL",
    ],
    [
        'name' => 'responses.response_type',
        'sql'  => "ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `response_type` VARCHAR(50) NULL DEFAULT NULL",
    ],
    [
        'name' => 'responses.is_read',
        'sql'  => "ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `is_read` TINYINT(1) NOT NULL DEFAULT 0",
    ],
    [
        'name' => 'responses.is_replied',
        'sql'  => "ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `is_replied` TINYINT(1) NOT NULL DEFAULT 0",
    ],
    [
        'name' => 'responses.sentiment',
        'sql'  => "ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `sentiment` VARCHAR(20) NULL DEFAULT NULL",
    ],
    [
        'name' => 'email_logs.clicked',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `clicked` TINYINT(1) NOT NULL DEFAULT 0",
    ],
    [
        'name' => 'response_replies',
        'sql'  => "CREATE TABLE IF NOT EXISTS `response_replies` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `response_id` int(11) NOT NULL,
          `replied_by` int(11) DEFAULT NULL,
          `reply_subject` varchar(500) DEFAULT '',
          `reply_body` text,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `response_id` (`response_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'email_logs.sent_at_default',
        'sql'  => "ALTER TABLE `email_logs` MODIFY COLUMN `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP",
    ],
    [
        'name' => 'email_logs.sent_at_backfill',
        'sql'  => "UPDATE `email_logs` SET `sent_at` = `created_at` WHERE `sent_at` IS NULL",
    ],
    [
        'name' => 'cron_log',
        'sql'  => "CREATE TABLE IF NOT EXISTS `cron_log` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `job_name` varchar(100) NOT NULL,
          `status` varchar(20) DEFAULT 'ok',
          `message` text,
          `duration_ms` int DEFAULT 0,
          `last_run` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `job_name` (`job_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
];

$ok   = 0;
$fail = 0;

foreach ($migrations as $m) {
    try {
        Database::query($m['sql']);
        $results[] = ['name' => $m['name'], 'status' => 'ok'];
        $ok++;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        // Treat "duplicate key / constraint already exists" as a no-op success
        if (strpos($msg, 'Duplicate key name') !== false
            || strpos($msg, 'already exists') !== false
        ) {
            $results[] = ['name' => $m['name'], 'status' => 'ok', 'note' => 'already exists'];
            $ok++;
        } else {
            $results[] = ['name' => $m['name'], 'status' => 'fail', 'error' => $msg];
            $fail++;
        }
    }
}

echo json_encode([
    'success'  => $fail === 0,
    'ok'       => $ok,
    'failed'   => $fail,
    'results'  => $results,
]);
