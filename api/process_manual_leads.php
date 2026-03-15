<?php
ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

ob_clean();
header('Content-Type: application/json');

// Accept internal token OR session superadmin
$internalToken = $_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '';
if ($internalToken === 'fintech2026secure') {
    // Valid internal call
} elseif (!class_exists('Auth') || !Auth::isSuperAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Count manual/CSV leads with status = 'new'
$manualLeads = Database::fetchAll(
    "SELECT id FROM leads WHERE source IN ('Manual', 'CSV Import') AND status = 'new'"
);
$count = count($manualLeads);

if ($count === 0) {
    echo json_encode([
        'success' => true,
        'saved'   => 0,
        'message' => 'No new Manual or CSV Import leads found to process.',
    ]);
    exit;
}

// Create collection record
Database::query(
    "INSERT INTO lead_collections (source, total_fetched, total_saved, total_skipped, total_duplicates, status, search_params, started_at, completed_at)
     VALUES ('Manual Import', ?, ?, 0, 0, 'completed', 'Manual/CSV leads processing', NOW(), NOW())",
    [$count, $count]
);

echo json_encode([
    'success' => true,
    'saved'   => $count,
    'message' => "Manual collection complete. {$count} leads ready.",
]);
