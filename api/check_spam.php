<?php
ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/spam_checker.php';

ob_clean();
header('Content-Type: application/json');

try {
    Auth::check();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$subject = trim($input['subject'] ?? '');
$body    = trim($input['body']    ?? '');

if ($subject === '' && $body === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'subject or body is required']);
    exit;
}

$result = SpamChecker::analyze($subject, $body);
echo json_encode(array_merge(['success' => true], $result));
