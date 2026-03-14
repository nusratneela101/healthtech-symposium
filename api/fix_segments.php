<?php
ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
ob_clean();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

Auth::requireSuperAdmin();

$leads = Database::fetchAll(
    "SELECT id, job_title, company, segment FROM leads WHERE segment = 'Other' OR segment IS NULL OR segment = ''"
);

$fixed = 0;
$total = count($leads);

foreach ($leads as $lead) {
    // First try keyword detection on job title + company
    $segment = detectSegment($lead['job_title'] ?? '', $lead['company'] ?? '');
    if ($segment === 'Other') {
        // Fall back to mapping the stored raw segment value
        $segment = mapApolloSegment($lead['segment'] ?? '');
    }
    if ($segment !== 'Other') {
        Database::query("UPDATE leads SET segment = ? WHERE id = ?", [$segment, (int)$lead['id']]);
        $fixed++;
    }
}

echo json_encode(['success' => true, 'fixed' => $fixed, 'total' => $total]);
