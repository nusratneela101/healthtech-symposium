<?php
/**
 * Migration script for existing installations.
 * Creates any tables that may be missing from an older install.
 *
 * Protection: requires the N8N_API_KEY (or MIGRATE_SECRET env var) to be
 * supplied as a Bearer token or as a ?secret= query-string parameter.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

// ── Auth ──────────────────────────────────────────────────────────────────────
$expectedSecret = $_ENV['MIGRATE_SECRET'] ?? (defined('N8N_API_KEY') ? N8N_API_KEY : '');
$providedSecret = '';

// Accept Bearer token header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($authHeader, 'Bearer ')) {
    $providedSecret = substr($authHeader, 7);
}
// Or accept ?secret= query param
if ($providedSecret === '' && isset($_GET['secret'])) {
    $providedSecret = $_GET['secret'];
}

if ($expectedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
    http_response_code(403);
    echo "403 Forbidden — supply the correct secret via ?secret= or Authorization: Bearer <secret>\n";
    exit;
}

// ── Migrations ────────────────────────────────────────────────────────────────
$migrations = [];

$migrations[] = [
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
];

$migrations[] = [
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
];

$migrations[] = [
    'name' => 'notifications.title_column',
    'sql'  => "ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `title` varchar(200) NOT NULL DEFAULT ''",
];

$migrations[] = [
    'name' => 'notifications.type_column',
    'sql'  => "ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `type` enum('info','success','warning','error') DEFAULT 'info'",
];

$migrations[] = [
    'name' => 'lead_collections',
    'sql'  => "CREATE TABLE IF NOT EXISTS `lead_collections` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `source` varchar(100) DEFAULT 'Apollo',
      `total_fetched` int(11) DEFAULT 0,
      `total_saved` int(11) DEFAULT 0,
      `total_skipped` int(11) DEFAULT 0,
      `status` enum('running','done','failed') DEFAULT 'running',
      `search_params` text,
      `started_at` datetime DEFAULT NULL,
      `completed_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$migrations[] = [
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
];

$migrations[] = [
    'name' => 'lead_tags',
    'sql'  => "CREATE TABLE IF NOT EXISTS `lead_tags` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `color` varchar(7) DEFAULT '#0d6efd',
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$migrations[] = [
    'name' => 'lead_tag_map',
    'sql'  => "CREATE TABLE IF NOT EXISTS `lead_tag_map` (
      `lead_id` int(11) NOT NULL,
      `tag_id` int(11) NOT NULL,
      PRIMARY KEY (`lead_id`, `tag_id`),
      FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`tag_id`) REFERENCES `lead_tags`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$migrations[] = [
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
];

$migrations[] = [
    'name' => 'campaigns.scheduled_at',
    'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `scheduled_at` datetime DEFAULT NULL",
];

$migrations[] = [
    'name' => 'campaigns.scheduled_by',
    'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `scheduled_by` int(11) DEFAULT NULL",
];

$migrations[] = [
    'name' => 'campaigns.status_scheduled',
    'sql'  => "ALTER TABLE `campaigns` MODIFY COLUMN `status` enum('draft','scheduled','running','completed','paused') DEFAULT 'draft'",
];

$migrations[] = [
    'name' => 'responses.hot_alert_sent',
    'sql'  => "ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `hot_alert_sent` tinyint(1) NOT NULL DEFAULT 0",
];

$migrations[] = [
    'name' => 'leads.score',
    'sql'  => "ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `score` int DEFAULT 0",
];

// ── Run ───────────────────────────────────────────────────────────────────────
$ok = 0;
$fail = 0;
echo "Running migrations...\n\n";

foreach ($migrations as $m) {
    try {
        Database::query($m['sql']);
        echo "[OK]   {$m['name']}\n";
        $ok++;
    } catch (Exception $e) {
        echo "[FAIL] {$m['name']}: " . $e->getMessage() . "\n";
        $fail++;
    }
}

echo "\nDone. $ok succeeded, $fail failed.\n";
