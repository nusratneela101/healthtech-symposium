<?php
// Ensure output buffering is active before any output
ob_start();

// Bootstrap: set session name and start session BEFORE loading config
// (config.php also calls session_start, but only if PHP_SESSION_NONE;
//  we need to set the correct session name first so the browser cookie is read)
require_once __DIR__ . '/../includes/session_bootstrap.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

ob_clean();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stats = [
    'total_leads'      => 0,
    'new_leads'        => 0,
    'emailed'          => 0,
    'responded'        => 0,
    'converted'        => 0,
    'unsubscribed'     => 0,
    'total_campaigns'  => 0,
    'emails_sent'      => 0,
    'unread_responses' => 0,
    'delivered'        => 0,
    'bounced'          => 0,
    'hot_leads'        => 0,
    'followups_sent'   => 0,
    'week_sends'       => 0,
    'month_sends'      => 0,
    'opened'           => 0,
];
try { $stats['total_leads']      = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: total_leads query failed: ' . $e->getMessage()); }
try { $stats['new_leads']        = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='new'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: new_leads query failed: ' . $e->getMessage()); }
try { $stats['emailed']          = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='emailed'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: emailed query failed: ' . $e->getMessage()); }
try { $stats['responded']        = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='responded'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: responded query failed: ' . $e->getMessage()); }
try { $stats['converted']        = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='converted'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: converted query failed: ' . $e->getMessage()); }
try { $stats['unsubscribed']     = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='unsubscribed'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: unsubscribed query failed: ' . $e->getMessage()); }
try { $stats['total_campaigns']  = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM campaigns")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: total_campaigns query failed: ' . $e->getMessage()); }
try { $stats['emails_sent']      = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE (status != '' AND status IS NOT NULL) OR (message_id != '' AND message_id IS NOT NULL)")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: emails_sent query failed: ' . $e->getMessage()); }
try { $stats['unread_responses'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses WHERE is_read=0")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: unread_responses query failed: ' . $e->getMessage()); }
try { $stats['delivered']        = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status NOT IN ('failed','bounced') AND ((status IS NOT NULL AND status != '') OR (message_id IS NOT NULL AND message_id != ''))")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: delivered query failed: ' . $e->getMessage()); }
try { $stats['bounced']          = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status IN ('bounced','failed')")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: bounced query failed: ' . $e->getMessage()); }
// Fix: response_type column does not exist; using sentiment='positive' instead
try { $stats['hot_leads']        = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses WHERE sentiment='positive'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: hot_leads query failed: ' . $e->getMessage()); }
try { $stats['followups_sent']   = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE follow_up_sequence=2")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: followups_sent query failed: ' . $e->getMessage()); }
try { $stats['week_sends']       = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE sent_at >= DATE(NOW() - INTERVAL WEEKDAY(NOW()) DAY)")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: week_sends query failed: ' . $e->getMessage()); }
try { $stats['month_sends']      = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE sent_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: month_sends query failed: ' . $e->getMessage()); }
try { $stats['opened']           = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE opened_at IS NOT NULL")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard API: opened query failed: ' . $e->getMessage()); }
$openRateStr = 'N/A';
if ($stats['emails_sent'] > 0 && $stats['opened'] > 0) {
    $openRateStr = round(($stats['opened'] / $stats['emails_sent']) * 100, 1) . '%';
}
$stats['open_rate_str'] = $openRateStr;
echo json_encode($stats);