<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

Auth::check();
Auth::requireSuperAdmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$group  = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($input['group'] ?? 'general')));
$userId = Auth::user()['id'];

if (empty($input['settings']) || !is_array($input['settings'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No settings provided']);
    exit;
}

// Allowed keys per group — prevents arbitrary key injection
$allowedKeys = [
    'smtp'     => ['smtp_host','smtp_port','smtp_secure','smtp_user','smtp_pass','smtp_from_email','smtp_from_name'],
    'imap'     => ['imap_host','imap_port','imap_user','imap_pass','imap_secure','imap_mailbox','imap_poll_interval'],
    'branding' => ['site_name','site_tagline','logo_url','primary_color','accent_color','footer_text'],
    'api_keys' => ['n8n_api_key','brevo_api_key','ms_oauth_client_id','ms_oauth_client_secret','ms_oauth_tenant_id','apollo_api_key'],
    'email_defaults' => ['send_delay','max_batch','test_mode_default'],
];

if (!isset($allowedKeys[$group])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown settings group']);
    exit;
}

$saved = 0;
$sensitiveKeys = ['smtp_pass','imap_pass','n8n_api_key','brevo_api_key',
                  'ms_oauth_client_id','ms_oauth_client_secret','apollo_api_key'];
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
    try {
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
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        exit;
    }
}

// Audit
try {
    require_once __DIR__ . '/../includes/functions.php';
    audit_log('settings_updated', 'settings', null, "Group: $group, Keys saved: $saved");
} catch (Exception $e) {}

echo json_encode(['success' => true, 'saved' => $saved]);
