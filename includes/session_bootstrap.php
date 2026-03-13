<?php
/**
 * Session bootstrap — must be included BEFORE config/config.php in API files.
 *
 * config.php calls session_name() + session_start(), but in some server
 * configurations the session may be auto-started with the default name
 * (PHPSESSID) before config.php has a chance to set the custom name.
 * Including this file first ensures the correct session name is active
 * so the browser's fts_session cookie is read and $_SESSION is populated.
 */
if (session_status() === PHP_SESSION_NONE) {
    $sessionName = getenv('SESSION_NAME') ?: 'fts_session';
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure',
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_name($sessionName);
    session_start();
    unset($sessionName);
}
