<?php
require_once __DIR__ . '/includes/session_bootstrap.php';
require_once __DIR__ . '/config/config.php';
session_destroy();
header('Location: ' . APP_URL . '/login.php');
exit;
