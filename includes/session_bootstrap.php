<?php
/**
 * Session bootstrap — must be included BEFORE config/config.php in API files.
 *
 * Reads SESSION_NAME from $_ENV (populated by env_loader.php on cPanel/shared
 * hosting where putenv() values are not visible via getenv()), then falls back
 * to getenv(), then to the hard-coded default 'fts_session'.
 *
 * This ensures the session cookie name always matches the one set by config.php
 * so that $_SESSION['user_id'] is populated correctly in API endpoints.
 */
if (session_status() === PHP_SESSION_NONE) {
    // Populate $_ENV from .env if SESSION_NAME is not already available.
    // config.php also calls loadEnv(), so calling it here is safe — it merely
    // overwrites $_ENV keys with the same values a second time.
    if (!isset($_ENV['SESSION_NAME'])) {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            require_once __DIR__ . '/env_loader.php';
            loadEnv($envFile);
        }
    }

    // $_ENV is preferred (populated by env_loader.php on cPanel/shared hosting).
    // getenv() covers OS-level env vars; ?: is used instead of ?? because
    // getenv() returns false (not null) when the variable is missing.
    $sessionName = $_ENV['SESSION_NAME'] ?? getenv('SESSION_NAME') ?: 'fts_session';

    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure',
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_name($sessionName);
    session_start();
    unset($sessionName);
}
