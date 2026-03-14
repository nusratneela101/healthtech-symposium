<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!isset($_ENV['SESSION_NAME'])) {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            require_once __DIR__ . '/env_loader.php';
            loadEnv($envFile);
        }
    }
    $sessionName = $_ENV['SESSION_NAME'] ?? getenv('SESSION_NAME') ?: 'fts_session';
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_domain', '.softandpix.com');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_path', '/');
    session_name($sessionName);
    session_start();
    unset($sessionName);
}
