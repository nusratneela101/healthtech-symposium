<?php
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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$filename = $input['filename'] ?? '';

// Validate filename pattern
if (!preg_match('/^[a-z0-9_\-]+\.json$/', $filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid filename']);
    exit;
}

$wfDir = realpath(__DIR__ . '/../n8n_workflows');
if ($wfDir === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Workflow directory not found']);
    exit;
}

$filePath = $wfDir . DIRECTORY_SEPARATOR . basename($filename);

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

// Prevent path traversal — check resolved path stays within workflow directory
$realFile = realpath($filePath);
if ($realFile === false || dirname($realFile) !== $wfDir) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid filename']);
    exit;
}

$content = file_get_contents($filePath);
if ($content === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read file']);
    exit;
}

// Replace placeholders
$n8nApiKey = getSetting('n8n_api_key', N8N_API_KEY);
$host      = parse_url(APP_URL, PHP_URL_HOST) ?: 'YOURSITE.com';

$patched = str_replace(
    ['YOUR_N8N_API_KEY', 'YOURSITE.com', APP_URL],
    [$n8nApiKey,        $host,          APP_URL],
    $content
);

audit_log('workflow_patched', 'workflow', null, 'Patched: ' . $filename);

// Serve as download — override Content-Type header
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . basename($filename, '.json') . '_patched.json"');
header('Content-Length: ' . strlen($patched));
echo $patched;
