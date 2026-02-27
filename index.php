<?php
require_once __DIR__ . '/config/config.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}
header('Location: ' . APP_URL . '/login.php');
exit;
