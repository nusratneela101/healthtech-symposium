<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
Auth::check();

function limitRow(string $label, string $limitKey, string $seqCond, string $sqlWhere): array {
    $limit = (int)getSetting($limitKey, '0');
    $sent  = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='sent' AND follow_up_sequence $seqCond AND $sqlWhere")['c'] ?? 0);
    return [
        'label'   => $label,
        'key'     => $limitKey,
        'limit'   => $limit,
        'sent'    => $sent,
        'pct'     => $limit > 0 ? round($sent / $limit * 100) : 0,
        'ok'      => $limit === 0 || $sent < $limit,
    ];
}

echo json_encode([
    'campaign' => [
        limitRow('Daily',   'email_daily_limit',   '= 1', "DATE(sent_at) = CURDATE()"),
        limitRow('Weekly',  'email_weekly_limit',  '= 1', "YEARWEEK(sent_at,1) = YEARWEEK(NOW(),1)"),
        limitRow('Monthly', 'email_monthly_limit', '= 1', "YEAR(sent_at)=YEAR(NOW()) AND MONTH(sent_at)=MONTH(NOW())"),
    ],
    'followup' => [
        limitRow('Daily',   'followup_daily_limit',   '> 1', "DATE(sent_at) = CURDATE()"),
        limitRow('Weekly',  'followup_weekly_limit',  '> 1', "YEARWEEK(sent_at,1) = YEARWEEK(NOW(),1)"),
        limitRow('Monthly', 'followup_monthly_limit', '> 1', "YEAR(sent_at)=YEAR(NOW()) AND MONTH(sent_at)=MONTH(NOW())"),
    ],
    'generated_at' => date('Y-m-d H:i:s'),
]);
