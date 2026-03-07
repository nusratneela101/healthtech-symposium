<?php
require_once __DIR__ . '/../includes/env_loader.php';
loadEnv(__DIR__ . '/../.env');

// Canada Eastern Time (Toronto) — UTC-5 / UTC-4 DST
date_default_timezone_set('America/Toronto');

define('APP_NAME',        $_ENV['APP_NAME']        ?? 'Canada Fintech Symposium');
define('APP_URL',         $_ENV['APP_URL']          ?? 'https://fintech.softandpix.com');
define('APP_VERSION',     $_ENV['APP_VERSION']      ?? '2.0.0');

define('SMTP_HOST',       $_ENV['SMTP_HOST']        ?? 'smtp-relay.brevo.com');
define('SMTP_PORT',       (int)($_ENV['SMTP_PORT']  ?? 587));
define('SMTP_SECURE',     $_ENV['SMTP_SECURE']      ?? 'tls');
define('SMTP_USER',       $_ENV['SMTP_USER']        ?? '');
define('SMTP_PASS',       $_ENV['SMTP_PASS']        ?? '');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL']  ?? 'info@fintech.softandpix.com');
define('SMTP_FROM_NAME',  $_ENV['SMTP_FROM_NAME']   ?? 'Canada Fintech Symposium');

define('IMAP_HOST',       $_ENV['IMAP_HOST']        ?? '');
define('IMAP_USER',       $_ENV['IMAP_USER']        ?? '');
define('IMAP_PASS',       $_ENV['IMAP_PASS']        ?? '');

define('BREVO_API_KEY',         $_ENV['BREVO_API_KEY']         ?? '');
define('MS_OAUTH_CLIENT_ID',    $_ENV['MS_OAUTH_CLIENT_ID']    ?? '');
define('MS_OAUTH_CLIENT_SECRET',$_ENV['MS_OAUTH_CLIENT_SECRET']?? '');
define('MS_OAUTH_TENANT_ID',    $_ENV['MS_OAUTH_TENANT_ID']    ?? 'common');
define('MS_OAUTH_REDIRECT_URI', $_ENV['MS_OAUTH_REDIRECT_URI'] ?? '');

define('N8N_API_KEY',     $_ENV['N8N_API_KEY']      ?? '');
define('N8N_URL',         $_ENV['N8N_URL']           ?? 'https://smnurnobi.app.n8n.cloud');
define('N8N_WEBHOOK_URL', $_ENV['N8N_WEBHOOK_URL']   ?? 'https://smnurnobi.app.n8n.cloud/webhook/fintech');
define('SESSION_NAME',    $_ENV['SESSION_NAME']     ?? 'fts_session');

define('APOLLO_API_KEY',  $_ENV['APOLLO_API_KEY']   ?? '');

define('EMAIL_DAILY_LIMIT',      (int)($_ENV['EMAIL_DAILY_LIMIT']      ?? 0));
define('EMAIL_WEEKLY_LIMIT',     (int)($_ENV['EMAIL_WEEKLY_LIMIT']     ?? 0));
define('EMAIL_MONTHLY_LIMIT',    (int)($_ENV['EMAIL_MONTHLY_LIMIT']    ?? 0));
define('FOLLOWUP_DAILY_LIMIT',   (int)($_ENV['FOLLOWUP_DAILY_LIMIT']   ?? 0));
define('FOLLOWUP_WEEKLY_LIMIT',  (int)($_ENV['FOLLOWUP_WEEKLY_LIMIT']  ?? 0));
define('FOLLOWUP_MONTHLY_LIMIT', (int)($_ENV['FOLLOWUP_MONTHLY_LIMIT'] ?? 0));

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get a setting value from the site_settings DB table, falling back to
 * config constants / .env values, then the supplied $default.
 */
function getSetting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            require_once __DIR__ . '/../config/database.php';
            $rows = Database::fetchAll("SELECT setting_key, setting_value FROM site_settings");
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = $row['setting_value'] ?? '';
            }
        } catch (Exception $e) {
            // DB not available yet — fall through to defaults
        }
    }
    if (array_key_exists($key, $cache) && $cache[$key] !== '') {
        return $cache[$key];
    }
    // Map common keys to config constants
    $map = [
        'smtp_host'        => SMTP_HOST,
        'smtp_port'        => (string)SMTP_PORT,
        'smtp_secure'      => SMTP_SECURE,
        'smtp_user'        => SMTP_USER,
        'smtp_pass'        => SMTP_PASS,
        'smtp_from_email'  => SMTP_FROM_EMAIL,
        'smtp_from_name'   => SMTP_FROM_NAME,
        'imap_host'        => IMAP_HOST,
        'imap_user'        => IMAP_USER,
        'imap_pass'        => IMAP_PASS,
        'brevo_api_key'    => BREVO_API_KEY,
        'n8n_api_key'      => N8N_API_KEY,
        'n8n_url'          => N8N_URL,
        'n8n_webhook_url'  => N8N_WEBHOOK_URL,
        'ms_oauth_client_id'     => MS_OAUTH_CLIENT_ID,
        'ms_oauth_client_secret' => MS_OAUTH_CLIENT_SECRET,
        'ms_oauth_tenant_id'     => MS_OAUTH_TENANT_ID,
        'site_name'        => APP_NAME,
        'apollo_api_key'   => APOLLO_API_KEY,
        'email_daily_limit'      => (string)EMAIL_DAILY_LIMIT,
        'email_weekly_limit'     => (string)EMAIL_WEEKLY_LIMIT,
        'email_monthly_limit'    => (string)EMAIL_MONTHLY_LIMIT,
        'followup_daily_limit'   => (string)FOLLOWUP_DAILY_LIMIT,
        'followup_weekly_limit'  => (string)FOLLOWUP_WEEKLY_LIMIT,
        'followup_monthly_limit' => (string)FOLLOWUP_MONTHLY_LIMIT,
    ];
    return $map[$key] ?? $default;
}

/**
 * Save a setting to the site_settings table.
 */
function saveSetting(string $key, string $value, string $group = 'general', ?int $userId = null): void {
    try {
        require_once __DIR__ . '/../config/database.php';
        Database::query(
            "INSERT INTO site_settings (setting_key, setting_value, setting_group, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                     setting_group  = VALUES(setting_group),
                                     updated_by     = VALUES(updated_by),
                                     updated_at     = NOW()",
            [$key, $value, $group, $userId]
        );
    } catch (Exception $e) {
        // Only suppress "table not found" errors (SQLSTATE 42S02 / MySQL 1146)
        if (strpos($e->getMessage(), '42S02') === false && strpos($e->getMessage(), '1146') === false) {
            throw $e;
        }
    }
}