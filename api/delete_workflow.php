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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$filename = $input['filename'] ?? '';

// Protected core workflows
$protected = [
    'fintech_master_workflow.json',
    'lead_collector.json',
    'followup_sender.json',
    'response_tracker.json',
    'thursday_campaign.json',
];

if (in_array($filename, $protected, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Core workflow files cannot be deleted']);
    exit;
}

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

if (!unlink($filePath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not delete file']);
    exit;
}

audit_log('workflow_deleted', 'workflow', null, 'Deleted: ' . $filename);

echo json_encode(['success' => true]);
