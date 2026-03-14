<?php
ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
ob_clean();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

Auth::check();
Auth::requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();
    $count = (int) Database::fetchOne("SELECT COUNT(*) AS cnt FROM leads")['cnt'];
    Database::query("DELETE FROM leads", []);
    $pdo->commit();
    audit_log('delete_all_leads', 'leads', null, 'All leads deleted');
    echo json_encode(['success' => true, 'deleted' => $count]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
