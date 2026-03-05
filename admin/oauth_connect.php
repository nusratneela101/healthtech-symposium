<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/msgraph.php';

$pageTitle = 'Microsoft 365 OAuth';

// Handle disconnect
if (isset($_POST['disconnect'])) {
    if (!csrf_verify()) {
        flash('error', 'Invalid request. Please try again.');
        header('Location: ' . APP_URL . '/admin/oauth_connect.php');
        exit;
    }
    Database::query("DELETE FROM oauth_accounts WHERE provider='microsoft'");
    audit_log('ms365_disconnect', 'oauth_accounts');
    flash('success', 'Microsoft 365 account disconnected.');
    header('Location: ' . APP_URL . '/admin/oauth_connect.php');
    exit;
}

$account = Database::fetchOne("SELECT * FROM oauth_accounts WHERE provider='microsoft' ORDER BY id DESC LIMIT 1");
$authUrl  = MsGraph::getAuthUrl();
?>

<div style="max-width:600px;margin:0 auto">
    <h2 style="font-size:20px;margin-bottom:20px">🔗 Microsoft 365 Connection</h2>

    <?php if ($account): ?>
    <div class="gc" style="margin-bottom:20px">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
            <div style="font-size:32px">✅</div>
            <div>
                <div style="font-size:15px;font-weight:600;color:#10b981">Connected</div>
                <div style="font-size:13px;color:#8a9ab5">Microsoft 365 account is linked</div>
            </div>
        </div>
        <div style="background:#0d1f38;border-radius:8px;padding:16px;font-size:13px">
            <div style="margin-bottom:8px"><span style="color:#8a9ab5">Email:</span> <strong><?php echo htmlspecialchars($account['email']); ?></strong></div>
            <div style="margin-bottom:8px"><span style="color:#8a9ab5">Scopes:</span> <?php echo htmlspecialchars($account['scopes'] ?: 'Mail.Read Mail.Send'); ?></div>
            <div style="margin-bottom:8px"><span style="color:#8a9ab5">Token Expires:</span> <?php echo $account['token_expires_at'] ? htmlspecialchars($account['token_expires_at']) : 'N/A'; ?></div>
            <div><span style="color:#8a9ab5">Connected:</span> <?php echo timeAgo($account['created_at']); ?></div>
        </div>
        <form method="POST" style="margin-top:16px" onsubmit="return confirm('Disconnect Microsoft 365?')">
            <?php echo csrf_field(); ?>
            <button type="submit" name="disconnect" value="1" class="btn-sec" style="color:#ef4444;border-color:#ef4444">🔌 Disconnect</button>
        </form>
    </div>

    <?php else: ?>
    <div class="gc" style="margin-bottom:20px">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
            <div style="font-size:32px">🔌</div>
            <div>
                <div style="font-size:15px;font-weight:600;color:#8a9ab5">Not Connected</div>
                <div style="font-size:13px;color:#8a9ab5">Connect your Microsoft 365 account to use Graph API for email</div>
            </div>
        </div>
        <?php if (!MS_OAUTH_CLIENT_ID): ?>
        <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:14px;color:#fca5a5;font-size:13px;margin-bottom:16px">
            ⚠️ MS_OAUTH_CLIENT_ID is not configured. Set it in your <code>.env</code> file.
        </div>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars($authUrl); ?>" class="btn-launch" style="text-decoration:none;display:inline-block">
            🔗 Connect Microsoft 365
        </a>
    </div>
    <?php endif; ?>

    <div class="gc">
        <div class="gc-title">ℹ️ About Microsoft 365 Integration</div>
        <div style="font-size:13px;color:#8a9ab5;line-height:1.7">
            <p style="margin-bottom:8px">When connected, the platform can:</p>
            <ul style="padding-left:20px">
                <li>Poll your inbox for replies to campaigns</li>
                <li>Send emails via Microsoft 365 (bypassing SMTP limits)</li>
                <li>Track conversation threads</li>
            </ul>
            <p style="margin-top:12px">You need an Azure App Registration with <strong>Mail.Read</strong> and <strong>Mail.Send</strong> delegated permissions.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
