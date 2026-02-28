<?php
define('APP_NAME',        'Canada FinTech Symposium');
define('APP_SHORT',       'CFTS');
define('APP_TAGLINE',     'Igniting the Future of Finance');
define('APP_URL',         'https://yourdomain.com/healthtech');
define('APP_VERSION',     '2.0.0');

define('SMTP_HOST',       'mail.101bdtech.com');
define('SMTP_PORT',       465);
define('SMTP_SECURE',     'ssl');
define('SMTP_USER',       'sm@101bdtech.com');
define('SMTP_PASS',       'Nurnobi131221');
define('SMTP_FROM_EMAIL', 'sm@101bdtech.com');
define('SMTP_FROM_NAME',  'Canada FinTech Symposium');

define('IMAP_HOST',       '{mail.101bdtech.com:993/imap/ssl/novalidate-cert}INBOX');
define('IMAP_USER',       'sm@101bdtech.com');
define('IMAP_PASS',       'Nurnobi131221');

define('N8N_API_KEY',     'HTS2026Key');
define('SESSION_NAME',    'hts_session');

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
