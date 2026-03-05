<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

Auth::requireSuperAdmin();

$leads = Database::fetchAll(
    "SELECT id, job_title, company FROM leads WHERE segment = 'Other' OR segment IS NULL"
);

$fixed = 0;
$total = count($leads);

foreach ($leads as $lead) {
    $segment = detectSegment($lead['job_title'] ?? '', $lead['company'] ?? '');
    if ($segment !== 'Other') {
        Database::query("UPDATE leads SET segment = ? WHERE id = ?", [$segment, (int)$lead['id']]);
        $fixed++;
    }
}

echo json_encode(['success' => true, 'fixed' => $fixed, 'total' => $total]);
