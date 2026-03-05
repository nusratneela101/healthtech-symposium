<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/msgraph.php';

Auth::check();

$code  = trim($_GET['code']  ?? '');
$error = trim($_GET['error'] ?? '');

if ($error) {
    flash('error', 'Microsoft authorization failed: ' . htmlspecialchars($_GET['error_description'] ?? $error));
    header('Location: ' . APP_URL . '/admin/oauth_connect.php');
    exit;
}

if (!$code) {
    flash('error', 'No authorization code received.');
    header('Location: ' . APP_URL . '/admin/oauth_connect.php');
    exit;
}

$tokens = MsGraph::exchangeCode($code);
if (!$tokens) {
    flash('error', 'Failed to exchange authorization code for tokens.');
    header('Location: ' . APP_URL . '/admin/oauth_connect.php');
    exit;
}

// Get account email via Graph
$ch = curl_init('https://graph.microsoft.com/v1.0/me?$select=mail,userPrincipalName');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$tokens['access_token']}"],
]);
$meBody = curl_exec($ch);
curl_close($ch);
$me    = json_decode($meBody, true);
$email = $me['mail'] ?? $me['userPrincipalName'] ?? 'unknown@microsoft.com';

$expiresAt = date('Y-m-d H:i:s', time() + (int)($tokens['expires_in'] ?? 3600));
$scopes    = $tokens['scope'] ?? 'Mail.Read Mail.Send offline_access';
$userId    = Auth::user()['id'] ?? null;

Database::query(
    "INSERT INTO oauth_accounts (provider, email, access_token, refresh_token, token_expires_at, scopes, connected_by)
     VALUES ('microsoft',?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
       access_token=VALUES(access_token),
       refresh_token=VALUES(refresh_token),
       token_expires_at=VALUES(token_expires_at),
       scopes=VALUES(scopes),
       connected_by=VALUES(connected_by),
       updated_at=NOW()",
    [$email, $tokens['access_token'], $tokens['refresh_token'] ?? '', $expiresAt, $scopes, $userId]
);

audit_log('ms365_connect', 'oauth_accounts', null, "Connected: $email");
flash('success', "Microsoft 365 connected: $email");
header('Location: ' . APP_URL . '/admin/oauth_connect.php');
exit;
