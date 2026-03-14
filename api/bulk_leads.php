<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

Auth::check();
Auth::requireSuperAdmin();

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($input['action'] ?? '');
$ids    = array_map('intval', $input['ids'] ?? []);

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No lead IDs provided']);
    exit;
}

// Build safe placeholders
$placeholders = implode(',', array_fill(0, count($ids), '?'));

switch ($action) {
    case 'bulk_delete':
        Database::query("DELETE FROM leads WHERE id IN ($placeholders)", $ids);
        audit_log('bulk_delete_leads', 'leads', null, 'IDs: ' . implode(',', $ids));
        echo json_encode(['success' => true, 'affected' => count($ids)]);
        break;

    case 'bulk_status_update':
        $status = trim($input['value'] ?? '');
        $validStatuses = ['new','emailed','responded','converted','unsubscribed','bounced'];
        if (!in_array($status, $validStatuses, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status value']);
            exit;
        }
        Database::query(
            "UPDATE leads SET status = ? WHERE id IN ($placeholders)",
            array_merge([$status], $ids)
        );
        audit_log('bulk_status_update', 'leads', null, "Status: $status, IDs: " . implode(',', $ids));
        echo json_encode(['success' => true, 'affected' => count($ids)]);
        break;

    case 'bulk_segment_update':
        $segment = trim($input['value'] ?? '');
        $validSegs = getSegments();
        if (!in_array($segment, $validSegs, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid segment value']);
            exit;
        }
        Database::query(
            "UPDATE leads SET segment = ? WHERE id IN ($placeholders)",
            array_merge([$segment], $ids)
        );
        audit_log('bulk_segment_update', 'leads', null, "Segment: $segment, IDs: " . implode(',', $ids));
        echo json_encode(['success' => true, 'affected' => count($ids)]);
        break;

    case 'bulk_export':
        // Return lead data as JSON for the caller to render as CSV
        $leads = Database::fetchAll(
            "SELECT id, full_name, email, company, job_title, role, segment, country, province,
                    city, source, status, linkedin_url, created_at
             FROM leads WHERE id IN ($placeholders) ORDER BY id",
            $ids
        );
        echo json_encode(['success' => true, 'leads' => $leads, 'count' => count($leads)]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action. Valid: bulk_delete, bulk_status_update, bulk_segment_update, bulk_export']);
}
