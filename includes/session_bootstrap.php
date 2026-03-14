<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!defined('APP_URL')) {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            require_once __DIR__ . '/env_loader.php';
            loadEnv($envFile);
        }
    }
    $sessionName = $_ENV['SESSION_NAME'] ?? getenv('SESSION_NAME') ?: 'fts_session';
    $isSecure    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    unset($sessionName, $isSecure);
}
