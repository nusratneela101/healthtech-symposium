<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
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

if (empty($_FILES['workflow'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['workflow'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Upload error code: ' . $file['error']]);
    exit;
}

// Validate file size (max 2MB)
if ($file['size'] > 2 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File too large (max 2MB)']);
    exit;
}

// Validate extension
$origName = $file['name'];
if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'json') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Only .json files are allowed']);
    exit;
}

// Validate JSON content
$content = file_get_contents($file['tmp_name']);
if ($content === false || (json_decode($content) === null && json_last_error() !== JSON_ERROR_NONE)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON content']);
    exit;
}

// Sanitize filename: allow only [a-z0-9_-], max 60 chars (without extension)
$baseName = strtolower(pathinfo($origName, PATHINFO_FILENAME));
$safeName = preg_replace('/[^a-z0-9_\-]/', '', $baseName);
if ($safeName === '') {
    $safeName = 'workflow_' . time();
}
if (strlen($safeName) > 60) {
    $safeName = substr($safeName, 0, 60);
}
$safeName .= '.json';

$destDir = realpath(__DIR__ . '/../n8n_workflows');
if ($destDir === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Workflow directory not found']);
    exit;
}

$destPath = $destDir . DIRECTORY_SEPARATOR . $safeName;

// Prevent path traversal
if (realpath(dirname($destPath)) !== $destDir) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid filename']);
    exit;
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

audit_log('workflow_uploaded', 'workflow', null, 'Uploaded: ' . $safeName);

echo json_encode(['success' => true, 'filename' => $safeName]);
