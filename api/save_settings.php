<?php
// Start output buffering BEFORE any output
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Clear any accidental output
ob_clean();

header('Content-Type: application/json');

try {
    Auth::check();
    Auth::requireSuperAdmin();
} catch (Exception $e) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: ' . $e->getMessage()]);
    exit;
}

// Auto-create site_settings table if it doesn't exist
try {
    Database::query("CREATE TABLE IF NOT EXISTS `site_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text,
        `setting_group` varchar(50) DEFAULT 'general',
        `updated_by` int(11) DEFAULT NULL,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* already exists */ }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$group  = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($input['group'] ?? 'general')));
$userId = Auth::user()['id'];

if (empty($input['settings']) || !is_array($input['settings'])) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No settings provided']);
    exit;
}

// Allowed keys per group — prevents arbitrary key injection
$allowedKeys = [
    'smtp'     => ['smtp_host','smtp_port','smtp_secure','smtp_user','smtp_pass','smtp_from_email','smtp_from_name'],
    'imap'     => ['imap_host','imap_port','imap_user','imap_pass','imap_secure','imap_mailbox','imap_poll_interval'],
    'branding' => ['site_name','site_tagline','logo_url','primary_color','accent_color','footer_text'],
    'api_keys' => ['n8n_api_key','brevo_api_key','ms_oauth_client_id','ms_oauth_client_secret','ms_oauth_tenant_id','apollo_api_key',
                   'n8n_url','apollo_search_titles','apollo_search_location','apollo_search_industry',
                   'apollo_per_page','apollo_max_pages','brevo_sender_name','brevo_sender_email'],
    'n8n'      => ['n8n_url','n8n_api_key','n8n_webhook_url'],
    'apollo'   => ['apollo_api_key','apollo_search_url','apollo_search_titles','apollo_search_location','apollo_search_industry','apollo_per_page','apollo_max_pages'],
    'brevo'    => ['brevo_api_key','brevo_sender_name','brevo_sender_email'],
    'email_defaults' => ['send_delay','max_batch','test_mode_default'],
    'email_setup'    => ['email_provider'],
    'sending_limits' => [
        'email_daily_limit','email_weekly_limit','email_monthly_limit',
        'followup_daily_limit','followup_weekly_limit','followup_monthly_limit',
    ],
    'warmup' => [
        'warmup_enabled','warmup_start_date','warmup_days',
        'warmup_start_volume','warmup_max_volume',
    ],
    'automation' => ['automation_mode'],
    'enrichment' => [
        'enrichment_apollo_active',
        'enrichment_hunter_active',
        'enrichment_anymailfinder_active',
        'hunter_api_key',
        'anymailfinder_api_key',
    ],
    // Per-provider credential groups
    'smtp_cpanel'       => ['cpanel_smtp_host','cpanel_smtp_port','cpanel_smtp_secure','cpanel_smtp_user','cpanel_smtp_pass','cpanel_smtp_from_email','cpanel_smtp_from_name','cpanel_imap_host','cpanel_imap_user','cpanel_imap_pass'],
    'smtp_business'     => ['business_smtp_host','business_smtp_port','business_smtp_secure','business_smtp_user','business_smtp_pass','business_smtp_from_email','business_smtp_from_name'],
    'smtp_microsoft365' => ['ms365_smtp_host','ms365_smtp_port','ms365_smtp_secure','ms365_smtp_user','ms365_smtp_pass','ms365_smtp_from_email','ms365_smtp_from_name','ms365_imap_host','ms365_imap_user','ms365_imap_pass'],
    'smtp_gmail'        => ['gmail_smtp_host','gmail_smtp_port','gmail_smtp_secure','gmail_smtp_user','gmail_smtp_pass','gmail_smtp_from_email','gmail_smtp_from_name'],
    'smtp_brevo'        => ['brevo_smtp_host','brevo_smtp_port','brevo_smtp_secure','brevo_smtp_user','brevo_smtp_pass','brevo_smtp_from_email','brevo_smtp_from_name'],
];

if (!isset($allowedKeys[$group])) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown settings group: ' . $group]);
    exit;
}

$saved = 0;
$sensitiveKeys = ['smtp_pass','imap_pass','n8n_api_key','brevo_api_key',
                  'ms_oauth_client_id','ms_oauth_client_secret','apollo_api_key',
                  'cpanel_smtp_pass','business_smtp_pass','ms365_smtp_pass','gmail_smtp_pass','brevo_smtp_pass',
                  'cpanel_imap_pass','ms365_imap_pass',
                  'hunter_api_key','anymailfinder_api_key'];
try {
    foreach ($input['settings'] as $key => $value) {
        $key = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($key)));
        if (!in_array($key, $allowedKeys[$group], true)) {
            continue;
        }
        $value = (string)$value;
        // For sensitive fields, skip saving if the user left the field blank
        if (in_array($key, $sensitiveKeys, true) && $value === '') {
            continue;
        }
        Database::query(
            "INSERT INTO site_settings (setting_key, setting_value, setting_group, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                     setting_group  = VALUES(setting_group),
                                     updated_by     = VALUES(updated_by),
                                     updated_at     = NOW()",
            [$key, $value, $group, $userId]
        );
        $saved++;
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    exit;
}

// Audit
try {
    require_once __DIR__ . '/../includes/functions.php';
    audit_log('settings_updated', 'settings', null, "Group: $group, Keys saved: $saved");
} catch (Exception $e) {}

ob_clean();
echo json_encode(['success' => true, 'saved' => $saved, 'message' => "$saved setting(s) saved successfully"]);
