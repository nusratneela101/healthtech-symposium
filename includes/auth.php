<?php
class Auth {
    public static function check(): void {
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }

    public static function isAdmin(): bool {
        return !empty($_SESSION['user_id']);
    }

    public static function isSuperAdmin(): bool {
        return ($_SESSION['user_role'] ?? '') === 'superadmin';
    }

    public static function requireSuperAdmin(): void {
        self::check();
        if (!self::isSuperAdmin()) {
            header('Location: ' . APP_URL . '/admin/dashboard.php');
            exit;
        }
    }

    public static function user(): array {
        return [
            'id'    => $_SESSION['user_id']    ?? 0,
            'name'  => $_SESSION['user_name']  ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role'  => $_SESSION['user_role']  ?? '',
        ];
    }

    public static function logout(): void {
        session_destroy();
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}
