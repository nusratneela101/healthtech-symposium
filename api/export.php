<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::check();
Auth::requireSuperAdmin();

$type      = preg_replace('/[^a-z_]/', '', strtolower(trim($_GET['type'] ?? 'leads')));
$format    = strtolower(trim($_GET['format'] ?? 'csv'));
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to']   ?? '');
$status    = trim($_GET['status']    ?? '');
$segment   = trim($_GET['segment']   ?? '');
$province  = trim($_GET['province']  ?? '');

// Validate date format
if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = '';
if ($dateTo   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = '';

$filename = $type . '_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');

$out = fopen('php://output', 'w');
// BOM for Excel UTF-8 compatibility
fwrite($out, "\xEF\xBB\xBF");

switch ($type) {
    // ── Leads ─────────────────────────────────────────────────────────────
    case 'leads':
        fputcsv($out, ['ID','First Name','Last Name','Full Name','Email','Company','Job Title',
                       'Role','Segment','Country','Province','City','Source','Status',
                       'LinkedIn','Score','Notes','Created At']);
        $where  = '1=1';
        $params = [];
        if ($status)   { $where .= ' AND status = ?';   $params[] = $status; }
        if ($segment)  { $where .= ' AND segment = ?';  $params[] = $segment; }
        if ($province) { $where .= ' AND province = ?'; $params[] = $province; }
        if ($dateFrom) { $where .= ' AND DATE(created_at) >= ?'; $params[] = $dateFrom; }
        if ($dateTo)   { $where .= ' AND DATE(created_at) <= ?'; $params[] = $dateTo; }

        $stmt = Database::query("SELECT * FROM leads WHERE $where ORDER BY created_at DESC", $params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['id'], $row['first_name'], $row['last_name'], $row['full_name'],
                $row['email'], $row['company'], $row['job_title'], $row['role'],
                $row['segment'], $row['country'], $row['province'], $row['city'],
                $row['source'], $row['status'], $row['linkedin_url'],
                $row['score'] ?? 0, $row['notes'] ?? '', $row['created_at'],
            ]);
        }
        break;

    // ── Campaigns ─────────────────────────────────────────────────────────
    case 'campaigns':
        fputcsv($out, ['ID','Campaign Key','Name','Template ID','Segment Filter','Province Filter',
                       'Total Leads','Sent','Failed','Status','Test Mode','Started At',
                       'Completed At','Created At']);
        $where  = '1=1';
        $params = [];
        if ($status)   { $where .= ' AND status = ?'; $params[] = $status; }
        if ($dateFrom) { $where .= ' AND DATE(created_at) >= ?'; $params[] = $dateFrom; }
        if ($dateTo)   { $where .= ' AND DATE(created_at) <= ?'; $params[] = $dateTo; }

        $stmt = Database::query("SELECT * FROM campaigns WHERE $where ORDER BY created_at DESC", $params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['id'], $row['campaign_key'], $row['name'], $row['template_id'],
                $row['filter_segment'], $row['filter_province'], $row['total_leads'],
                $row['sent_count'], $row['failed_count'], $row['status'],
                $row['test_mode'] ? 'Yes' : 'No',
                $row['started_at'] ?? '', $row['completed_at'] ?? '', $row['created_at'],
            ]);
        }
        break;

    // ── Email Logs ────────────────────────────────────────────────────────
    case 'email_logs':
        fputcsv($out, ['ID','Campaign ID','Lead ID','Recipient Email','Recipient Name','Subject',
                       'Status','Follow-Up Seq','Sent At','Opened At','Created At']);
        $where  = '1=1';
        $params = [];
        if ($status)   { $where .= ' AND status = ?'; $params[] = $status; }
        if ($dateFrom) { $where .= ' AND DATE(sent_at) >= ?'; $params[] = $dateFrom; }
        if ($dateTo)   { $where .= ' AND DATE(sent_at) <= ?'; $params[] = $dateTo; }

        $stmt = Database::query("SELECT * FROM email_logs WHERE $where ORDER BY sent_at DESC", $params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['id'], $row['campaign_id'], $row['lead_id'], $row['recipient_email'],
                $row['recipient_name'], $row['subject'], $row['status'],
                $row['follow_up_sequence'], $row['sent_at'] ?? '', $row['opened_at'] ?? '',
                $row['created_at'],
            ]);
        }
        break;

    // ── Responses ─────────────────────────────────────────────────────────
    case 'responses':
        fputcsv($out, ['ID','Lead ID','Campaign ID','From Email','From Name','Subject',
                       'Response Type','Is Read','Received At']);
        $where  = '1=1';
        $params = [];
        if ($status)   { $where .= ' AND response_type = ?'; $params[] = $status; }
        if ($dateFrom) { $where .= ' AND DATE(received_at) >= ?'; $params[] = $dateFrom; }
        if ($dateTo)   { $where .= ' AND DATE(received_at) <= ?'; $params[] = $dateTo; }

        $stmt = Database::query("SELECT * FROM responses WHERE $where ORDER BY received_at DESC", $params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['id'], $row['lead_id'] ?? '', $row['campaign_id'] ?? '',
                $row['from_email'], $row['from_name'], $row['subject'],
                $row['response_type'], $row['is_read'] ? 'Yes' : 'No', $row['received_at'],
            ]);
        }
        break;

    // ── Audit Log ─────────────────────────────────────────────────────────
    case 'audit_log':
        fputcsv($out, ['ID','User ID','Action','Entity Type','Entity ID','Details','IP Address','Created At']);
        $where  = '1=1';
        $params = [];
        if ($dateFrom) { $where .= ' AND DATE(created_at) >= ?'; $params[] = $dateFrom; }
        if ($dateTo)   { $where .= ' AND DATE(created_at) <= ?'; $params[] = $dateTo; }

        $stmt = Database::query("SELECT * FROM audit_logs WHERE $where ORDER BY created_at DESC", $params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['id'], $row['user_id'] ?? '', $row['action'], $row['entity_type'],
                $row['entity_id'] ?? '', $row['details'] ?? '', $row['ip_address'], $row['created_at'],
            ]);
        }
        break;

    default:
        fputcsv($out, ['Error', 'Unknown export type: ' . $type]);
}

fclose($out);
