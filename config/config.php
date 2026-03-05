<?php
require_once __DIR__ . '/../includes/env_loader.php';
loadEnv(__DIR__ . '/../.env');

define('APP_NAME',        $_ENV['APP_NAME']        ?? 'Canada FinTech Symposium');
define('APP_URL',         $_ENV['APP_URL']          ?? 'https://fintech.softandpix.com');
define('APP_VERSION',     $_ENV['APP_VERSION']      ?? '2.0.0');

define('SMTP_HOST',       $_ENV['SMTP_HOST']        ?? 'smtp-mail.outlook.com');
define('SMTP_PORT',       (int)($_ENV['SMTP_PORT']  ?? 587));
define('SMTP_SECURE',     $_ENV['SMTP_SECURE']      ?? 'tls');
define('SMTP_USER',       $_ENV['SMTP_USER']        ?? 'info@canadafintechsymposium.com');
define('SMTP_PASS',       $_ENV['SMTP_PASS']        ?? '');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL']  ?? 'info@canadafintechsymposium.com');
define('SMTP_FROM_NAME',  $_ENV['SMTP_FROM_NAME']   ?? 'Canada FinTech Symposium');

define('IMAP_HOST',       $_ENV['IMAP_HOST']        ?? '{outlook.office365.com:993/imap/ssl}INBOX');
define('IMAP_USER',       $_ENV['IMAP_USER']        ?? 'info@canadafintechsymposium.com');
define('IMAP_PASS',       $_ENV['IMAP_PASS']        ?? '');

define('BREVO_API_KEY',         $_ENV['BREVO_API_KEY']         ?? '');
define('MS_OAUTH_CLIENT_ID',    $_ENV['MS_OAUTH_CLIENT_ID']    ?? '');
define('MS_OAUTH_CLIENT_SECRET',$_ENV['MS_OAUTH_CLIENT_SECRET']?? '');
define('MS_OAUTH_TENANT_ID',    $_ENV['MS_OAUTH_TENANT_ID']    ?? 'common');
define('MS_OAUTH_REDIRECT_URI', $_ENV['MS_OAUTH_REDIRECT_URI'] ?? '');

define('N8N_API_KEY',     $_ENV['N8N_API_KEY']      ?? '');
define('SESSION_NAME',    $_ENV['SESSION_NAME']     ?? 'hts_session');

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
