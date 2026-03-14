<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::check();
Auth::requireSuperAdmin();

$filename = $_GET['file'] ?? '';

// Validate filename pattern
if (!preg_match('/^[a-z0-9_\-]+\.json$/', $filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename']);
    exit;
}

$wfDir = realpath(__DIR__ . '/../n8n_workflows');
if ($wfDir === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Workflow directory not found']);
    exit;
}

$filePath = $wfDir . DIRECTORY_SEPARATOR . basename($filename);

// Prevent path traversal
$realFile = realpath($filePath);
if ($realFile === false || dirname($realFile) !== $wfDir) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename']);
    exit;
}

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
