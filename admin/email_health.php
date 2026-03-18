<?php
$pageTitle = 'Email Health';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireSuperAdmin();

require_once __DIR__ . '/../includes/spam_checker.php';
require_once __DIR__ . '/../includes/dns_checker.php';
require_once __DIR__ . '/../includes/warmup.php';

// ── Engagement Metrics ────────────────────────────────────────────────────
$metrics = [
    'total_sent'    => 0,
    'delivered'     => 0,
    'bounced'       => 0,
    'opened'        => 0,
    'responded'     => 0,
    'unsubscribed'  => 0,
];
try {
    // Total send attempts (matches dashboard counting logic)
    $metrics['total_sent']   = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM email_logs WHERE (status IS NOT NULL AND status != '') OR (message_id IS NOT NULL AND message_id != '')"
    )['c'] ?? 0);

    // Delivered = explicitly confirmed OR sent with message_id > 5 min ago (likely delivered)
    $metrics['delivered']    = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM email_logs
         WHERE (status IN ('delivered','opened','clicked'))
            OR (status = 'sent' AND message_id IS NOT NULL AND message_id != '' AND sent_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))"
    )['c'] ?? 0);

    // Bounced/failed
    $metrics['bounced']      = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM email_logs WHERE status IN ('bounced','failed')"
    )['c'] ?? 0);

    $metrics['opened']       = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE opened_at IS NOT NULL")['c'] ?? 0);
    $metrics['responded']    = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses")['c'] ?? 0);
    $metrics['unsubscribed'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='unsubscribed'")['c'] ?? 0);
} catch (Exception $e) {}

// Use total_sent as the base for bounce/delivery rates
$base            = max(1, $metrics['total_sent']);
// Use delivered count as the base for open/response/unsub rates
$deliveredBase   = max(1, $metrics['delivered']);

$deliverRate     = round(($metrics['delivered'] / $base)          * 100, 1);
$bounceRate      = round(($metrics['bounced']   / $base)          * 100, 1);
$openRate        = round(($metrics['opened']    / $deliveredBase) * 100, 1);
$responseRate    = round(($metrics['responded'] / $deliveredBase) * 100, 1);
$unsubRate       = round(($metrics['unsubscribed'] / $deliveredBase) * 100, 1);

// Sender reputation score (0–100)
$reputationScore = max(0, min(100,
    100
    - ($bounceRate  * 3)
    - ($unsubRate   * 5)
    + ($openRate    * 0.5)
    + ($responseRate* 1)
));
$reputationScore = (int)round($reputationScore);

if ($reputationScore >= 80) {
    $repLabel = 'Excellent'; $repColor = '#10b981';
} elseif ($reputationScore >= 60) {
    $repLabel = 'Good';      $repColor = '#3b82f6';
} elseif ($reputationScore >= 40) {
    $repLabel = 'Fair';      $repColor = '#f59e0b';
} else {
    $repLabel = 'Poor';      $repColor = '#ef4444';
}

// ── DNS check ─────────────────────────────────────────────────────────────
$dns = DnsChecker::checkAll();

// ── Warm-up progress ──────────────────────────────────────────────────────
$warmup = WarmupManager::getProgress();

// ── Spam check — template selector ───────────────────────────────────────
$allTemplates       = [];
$latestTemplate     = null;
$spamResult         = null;
$selectedTemplateId = (int)($_GET['check_template'] ?? 0);
try {
    $allTemplates = Database::fetchAll(
        "SELECT id, name, subject, html_body, is_default, is_active FROM email_templates WHERE is_active = 1 ORDER BY is_default DESC, updated_at DESC"
    );

    if ($selectedTemplateId) {
        $latestTemplate = Database::fetchOne(
            "SELECT id, name, subject, html_body, is_default FROM email_templates WHERE id = ? AND is_active = 1", [$selectedTemplateId]
        );
    } else {
        $latestTemplate = Database::fetchOne(
            "SELECT id, name, subject, html_body, is_default FROM email_templates WHERE is_default = 1 AND is_active = 1 LIMIT 1"
        );
        if (!$latestTemplate) {
            $latestTemplate = Database::fetchOne(
                "SELECT id, name, subject, html_body, is_default FROM email_templates WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1"
            );
        }
    }

    if ($latestTemplate) {
        $spamResult = SpamChecker::analyze($latestTemplate['subject'], $latestTemplate['html_body']);
    }
} catch (Exception $e) {}

// ── Health alerts ─────────────────────────────────────────────────────────
$alerts = [];
if ($bounceRate > 5)    $alerts[] = ['error',   "High bounce rate: {$bounceRate}% — clean your list and check SMTP configuration."];
if ($unsubRate > 1)     $alerts[] = ['warning',  "Unsubscribe rate: {$unsubRate}% — review email relevance and frequency."];
if ($openRate < 15 && $metrics['total_sent'] > 50)
                        $alerts[] = ['warning',  "Low open rate: {$openRate}% — consider improving subject lines."];
if ($dns['spf']['status']   === 'missing') $alerts[] = ['error',   'SPF record is missing — emails may be marked as spam.'];
if ($dns['dmarc']['status'] === 'missing') $alerts[] = ['warning',  'DMARC record is missing — add one to prevent spoofing.'];
if ($dns['dkim']['status']  === 'missing') $alerts[] = ['warning',  'DKIM record not detected — configure DKIM with your email provider.'];
if ($warmup['enabled'] && !$warmup['completed'] && isset($warmup['daily_limit']))
    $alerts[] = ['info', "Warm-up active: daily limit is {$warmup['daily_limit']} emails (day {$warmup['current_day']} of {$warmup['days']})."];

// Helper
function dnsStatusIcon(string $status): string {
    return match($status) {
        'valid'   => '<span style="color:#10b981">✅ Valid</span>',
        'warning' => '<span style="color:#f59e0b">⚠️ Warning</span>',
        'missing' => '<span style="color:#ef4444">❌ Missing</span>',
        default   => '<span style="color:#8a9ab5">❓ Unknown</span>',
    };
}
function spamRiskColor(string $level): string {
    return match($level) {
        'low'      => '#10b981',
        'medium'   => '#f59e0b',
        'high'     => '#ef4444',
        'critical' => '#dc2626',
        default    => '#8a9ab5',
    };
}
// ── Webhook Health ────────────────────────────────────────────────────────
$webhookHealth = [
    'last_received'     => null,
    'total_events'      => 0,
    'event_breakdown'   => [],
    'unconfirmed_count' => 0,
    'table_exists'      => false,
];
try {
    // Count emails sent but never confirmed via webhook (likely missing delivery events)
    $webhookHealth['unconfirmed_count'] = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM email_logs
         WHERE status = 'sent' AND message_id IS NOT NULL AND message_id != ''
           AND sent_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    )['c'] ?? 0);

    // Try to read from webhook_logs (may not exist)
    $webhookHealth['last_received'] = Database::fetchOne(
        "SELECT received_at FROM webhook_logs ORDER BY received_at DESC LIMIT 1"
    )['received_at'] ?? null;

    $webhookHealth['total_events'] = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM webhook_logs WHERE source='brevo'"
    )['c'] ?? 0);

    $breakdown = Database::fetchAll(
        "SELECT event_type, COUNT(*) AS cnt FROM webhook_logs WHERE source='brevo'
         GROUP BY event_type ORDER BY cnt DESC"
    );
    foreach ($breakdown as $row) {
        $webhookHealth['event_breakdown'][$row['event_type']] = (int)$row['cnt'];
    }
    $webhookHealth['table_exists'] = true;
} catch (Exception $e) {
    // webhook_logs table may not exist
}

// Helper: friendly "time ago" for last webhook
function webhookTimeAgo(?string $datetime): string {
    if (!$datetime) return 'Never';
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}
$webhookLastReceivedAgo = webhookTimeAgo($webhookHealth['last_received']);
$webhookStatusColor = '#10b981'; // green — healthy
$webhookStatusLabel = 'Healthy';
if (!$webhookHealth['last_received']) {
    $webhookStatusColor = '#ef4444'; $webhookStatusLabel = 'No Events Received';
} elseif ((time() - strtotime($webhookHealth['last_received'])) > 86400) {
    $webhookStatusColor = '#f59e0b'; $webhookStatusLabel = 'Stale (>24h)';
}
?>

<h2 style="font-size:20px;margin-bottom:20px">📬 Email Health Dashboard</h2>

<?php if ($alerts): ?>
<div style="margin-bottom:24px;display:flex;flex-direction:column;gap:8px">
    <?php foreach ($alerts as [$type, $msg]): ?>
    <?php $col = $type === 'error' ? '#ef4444' : ($type === 'warning' ? '#f59e0b' : '#3b82f6'); ?>
    <div style="padding:10px 14px;background:#0a1628;border-left:3px solid <?php echo $col; ?>;border-radius:6px;font-size:13px;color:#e2e8f0">
        <?php echo $type === 'error' ? '🚨' : ($type === 'warning' ? '⚠️' : 'ℹ️'); ?>
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Engagement Metrics ──────────────────────────────────────────────── -->
<div class="gc" style="margin-bottom:24px">
    <div class="gc-title">📊 Engagement Metrics</div>
    <div class="gc-sub">Calculated from all-time email logs</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-top:16px">
        <?php
        $metricCards = [
            ['Emails Sent',    $metrics['total_sent'],    '#3b82f6', '✉️'],
            ['Deliverability', $deliverRate . '%',        '#10b981', '📬'],
            ['Bounce Rate',    $bounceRate  . '%',        $bounceRate > 5 ? '#ef4444' : '#f59e0b', '↩️'],
            ['Open Rate',      $openRate    . '%',        '#8b5cf6', '👁️'],
            ['Response Rate',  $responseRate. '%',        '#06b6d4', '💬'],
            ['Unsub Rate',     $unsubRate   . '%',        $unsubRate > 1 ? '#f59e0b' : '#10b981', '🚫'],
        ];
        foreach ($metricCards as [$label, $val, $color, $icon]):
        ?>
        <div style="background:#0a1628;border:1px solid #1e3a5f;border-radius:10px;padding:16px;text-align:center">
            <div style="font-size:22px;margin-bottom:6px"><?php echo $icon; ?></div>
            <div style="font-size:22px;font-weight:700;color:<?php echo $color; ?>"><?php echo $val; ?></div>
            <div style="font-size:11px;color:#8a9ab5;margin-top:4px"><?php echo $label; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Sender Reputation Score -->
    <div style="margin-top:20px;background:#0a1628;border:1px solid #1e3a5f;border-radius:10px;padding:20px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <span style="font-size:14px;font-weight:600">🏆 Sender Reputation Score</span>
            <span style="font-size:24px;font-weight:700;color:<?php echo $repColor; ?>"><?php echo $reputationScore; ?>/100 — <?php echo $repLabel; ?></span>
        </div>
        <div style="background:#1e3a5f;border-radius:4px;height:10px;overflow:hidden">
            <div style="width:<?php echo $reputationScore; ?>%;height:100%;background:<?php echo $repColor; ?>;border-radius:4px;transition:width .4s"></div>
        </div>
        <div style="font-size:11px;color:#8a9ab5;margin-top:8px">Score is based on bounce rate, unsubscribe rate, open rate, and response rate.</div>
    </div>
</div>

<!-- ── Webhook Health ─────────────────────────────────────────────────── -->
<div class="gc" style="margin-bottom:24px">
    <div class="gc-title">🔗 Webhook Health</div>
    <div class="gc-sub">Brevo webhook delivery events — real-time status tracking</div>

    <!-- Status bar -->
    <div style="margin-top:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:8px;background:#0a1628;border:1px solid #1e3a5f;border-radius:8px;padding:10px 16px">
            <span style="width:10px;height:10px;border-radius:50%;background:<?php echo $webhookStatusColor; ?>;display:inline-block"></span>
            <span style="font-size:13px;font-weight:600;color:<?php echo $webhookStatusColor; ?>"><?php echo $webhookStatusLabel; ?></span>
            <span style="font-size:12px;color:#8a9ab5">— Last event: <?php echo htmlspecialchars($webhookLastReceivedAgo); ?></span>
        </div>
        <button class="btn-sec" onclick="testWebhook()" style="padding:8px 16px;font-size:12px" id="testWebhookBtn">
            🧪 Test Webhook
        </button>
        <span id="webhookTestResult" style="font-size:12px;color:#8a9ab5"></span>
    </div>

    <!-- Stats grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-top:14px">
        <div style="background:#0a1628;border:1px solid #1e3a5f;border-radius:10px;padding:14px;text-align:center">
            <div style="font-size:20px;font-weight:700;color:#3b82f6"><?php echo number_format($webhookHealth['total_events']); ?></div>
            <div style="font-size:11px;color:#8a9ab5;margin-top:4px">Total Webhook Events</div>
        </div>
        <div style="background:#0a1628;border:1px solid #1e3a5f;border-radius:10px;padding:14px;text-align:center">
            <div style="font-size:20px;font-weight:700;color:<?php echo $webhookHealth['unconfirmed_count'] > 50 ? '#f59e0b' : '#10b981'; ?>">
                <?php echo number_format($webhookHealth['unconfirmed_count']); ?>
            </div>
            <div style="font-size:11px;color:#8a9ab5;margin-top:4px">Sent Unconfirmed (&gt;1h)</div>
        </div>
        <?php foreach (['delivered' => ['📬','#10b981','Delivered'], 'opened' => ['👁️','#8b5cf6','Opened'], 'hardBounce' => ['↩️','#ef4444','Hard Bounce'], 'softBounce' => ['↩️','#f59e0b','Soft Bounce']] as $evtKey => [$evtIcon, $evtColor, $evtLabel]): ?>
        <div style="background:#0a1628;border:1px solid #1e3a5f;border-radius:10px;padding:14px;text-align:center">
            <div style="font-size:20px;font-weight:700;color:<?php echo $evtColor; ?>">
                <?php echo number_format($webhookHealth['event_breakdown'][$evtKey] ?? 0); ?>
            </div>
            <div style="font-size:11px;color:#8a9ab5;margin-top:4px"><?php echo $evtIcon; ?> <?php echo $evtLabel; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($webhookHealth['event_breakdown'])): ?>
    <!-- Full event breakdown -->
    <div style="margin-top:14px;background:#0a1628;border:1px solid #1e3a5f;border-radius:8px;padding:14px">
        <div style="font-size:13px;font-weight:600;margin-bottom:10px">📋 Event Breakdown</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($webhookHealth['event_breakdown'] as $evtName => $evtCount): ?>
            <span style="background:#1e3a5f;border-radius:20px;padding:4px 12px;font-size:12px;color:#e2e8f0">
                <?php echo htmlspecialchars($evtName); ?>: <strong><?php echo number_format($evtCount); ?></strong>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Info note about "likely delivered" logic -->
    <div style="margin-top:12px;padding:10px 14px;background:#0a1628;border-left:3px solid #3b82f6;border-radius:6px;font-size:12px;color:#8a9ab5">
        ℹ️ <strong style="color:#e2e8f0">Sync Missing:</strong>
        Emails with status <code style="color:#3b82f6">sent</code> that have a valid <code style="color:#3b82f6">message_id</code>
        and were sent more than <strong>5 minutes ago</strong> are counted as <em>likely delivered</em> in all metrics above,
        since Brevo typically sends delivery webhooks within seconds. The <?php echo number_format($webhookHealth['unconfirmed_count']); ?> email(s)
        shown as "Sent Unconfirmed" have been waiting over 1 hour with no delivery confirmation.
    </div>

    <?php if (!$webhookHealth['table_exists']): ?>
    <div style="margin-top:10px;padding:10px 14px;background:#0a1628;border-left:3px solid #f59e0b;border-radius:6px;font-size:12px;color:#f59e0b">
        ⚠️ The <code>webhook_logs</code> table does not exist yet. Webhook event history is unavailable.
        Brevo delivery events are still being processed, but not logged for diagnostics.
    </div>
    <?php endif; ?>
</div>

<!-- ── DNS Authentication ─────────────────────────────────────────────── -->
<div class="gc" style="margin-bottom:24px">
    <div class="gc-title">🔐 DNS Authentication</div>
    <div class="gc-sub">
        Checking records for: <strong style="color:#e2e8f0"><?php echo htmlspecialchars($dns['domain'] ?: 'domain not configured'); ?></strong>
        <button class="btn-sec" onclick="recheckDns()" style="margin-left:12px;padding:4px 12px;font-size:12px">🔄 Re-check</button>
    </div>

    <?php foreach (['spf' => 'SPF', 'dmarc' => 'DMARC', 'dkim' => 'DKIM'] as $key => $label):
        $dnsRecord = $dns[$key];
    ?>
    <div style="margin-top:14px;background:#0a1628;border:1px solid #1e3a5f;border-radius:8px;padding:14px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
            <div>
                <span style="font-weight:600;font-size:14px"><?php echo $label; ?></span>
                <span style="margin-left:10px"><?php echo dnsStatusIcon($dnsRecord['status']); ?></span>
            </div>
            <?php if ($dnsRecord['record']): ?>
            <div style="font-size:11px;color:#8a9ab5;word-break:break-all;max-width:500px"><?php echo htmlspecialchars($dnsRecord['record']); ?></div>
            <?php endif; ?>
        </div>
        <div style="font-size:12px;color:#8a9ab5;margin-top:6px"><?php echo htmlspecialchars($dnsRecord['description']); ?></div>
        <?php if ($dnsRecord['instructions'] && $dnsRecord['status'] !== 'valid'): ?>
        <details style="margin-top:8px">
            <summary style="cursor:pointer;font-size:12px;color:#3b82f6">📋 Setup Instructions</summary>
            <pre style="margin-top:8px;background:#0d1b2e;border:1px solid #1e3a5f;border-radius:6px;padding:10px;font-size:11px;color:#8a9ab5;white-space:pre-wrap;word-break:break-all"><?php echo htmlspecialchars($dnsRecord['instructions']); ?></pre>
        </details>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Spam Content Checker ───────────────────────────────────────────── -->
<div class="gc" style="margin-bottom:24px">
    <div class="gc-title">🔍 Spam Content Checker</div>
    <div class="gc-sub">Analyze any subject / body for spam trigger words and deliverability issues</div>

    <!-- Template selector -->
    <?php if (!empty($allTemplates)): ?>
    <div style="margin-top:12px;margin-bottom:16px">
        <label style="font-size:12px;color:#8a9ab5;display:block;margin-bottom:4px">Select Template to Analyze</label>
        <div style="display:flex;gap:8px;align-items:center">
            <select class="fi" id="spam_template_select" onchange="changeSpamTemplate(this.value)" style="flex:1">
                <?php foreach ($allTemplates as $t): ?>
                <option value="<?php echo $t['id']; ?>" <?php echo ($latestTemplate && $t['id'] == $latestTemplate['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($t['name']); ?>
                    <?php if ($t['is_default']): ?> ⭐ (Default)<?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($spamResult && $latestTemplate): ?>
    <div style="margin-bottom:16px;padding:10px 14px;background:#0a1628;border:1px solid #1e3a5f;border-radius:8px;font-size:12px;color:#8a9ab5">
        Analyzing: <strong style="color:#e2e8f0"><?php echo htmlspecialchars($latestTemplate['name'] ?? $latestTemplate['subject']); ?></strong>
        <?php echo (!empty($latestTemplate['is_default']) ? ' ⭐ Default' : ''); ?>
        — Risk score:
        <strong style="color:<?php echo spamRiskColor($spamResult['risk_level']); ?>">
            <?php echo $spamResult['score']; ?>/100 (<?php echo ucfirst($spamResult['risk_level']); ?>)
        </strong>
    </div>
    <?php if ($spamResult['warnings']): ?>
    <div style="margin-bottom:8px">
        <?php foreach ($spamResult['warnings'] as $w): ?>
        <div style="font-size:12px;color:#f59e0b;padding:3px 0">⚠️ <?php echo htmlspecialchars($w); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($spamResult['suggestions']): ?>
    <div>
        <?php foreach ($spamResult['suggestions'] as $s): ?>
        <div style="font-size:12px;color:#10b981;padding:3px 0">💡 <?php echo htmlspecialchars($s); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Live checker -->
    <div style="margin-top:18px;display:grid;grid-template-columns:1fr 1fr;gap:12px" id="live-checker">
        <div>
            <label style="font-size:12px;color:#8a9ab5;display:block;margin-bottom:4px">Email Subject</label>
            <input class="fi" id="check_subject" placeholder="Enter subject line to check…" style="width:100%">
        </div>
        <div style="display:flex;align-items:flex-end">
            <button class="btn-launch" onclick="runSpamCheck()" style="width:100%">🔍 Analyze</button>
        </div>
    </div>
    <div style="margin-top:8px">
        <label style="font-size:12px;color:#8a9ab5;display:block;margin-bottom:4px">Email Body (HTML or plain text)</label>
        <textarea class="fi" id="check_body" rows="5" placeholder="Paste email body here…" style="width:100%;resize:vertical"></textarea>
    </div>
    <div id="spam-check-result" style="margin-top:12px"></div>
</div>

<!-- ── Email Warm-up ──────────────────────────────────────────────────── -->
<div class="gc" style="margin-bottom:24px">
    <div class="gc-title">🔥 Email Warm-up</div>
    <div class="gc-sub">Gradual sending volume ramp-up to build sender reputation</div>

    <?php if (!$warmup['enabled']): ?>
    <div style="padding:14px;background:#0a1628;border:1px solid #1e3a5f;border-radius:8px;font-size:13px;color:#8a9ab5;margin-top:12px">
        Warm-up is currently <strong style="color:#ef4444">disabled</strong>.
        Enable it in <a href="<?php echo APP_URL; ?>/admin/settings.php#tab-warmup" style="color:#3b82f6">Settings → Warm-up</a>.
    </div>
    <?php elseif ($warmup['completed']): ?>
    <div style="padding:14px;background:#0a1628;border:1px solid #10b981;border-radius:8px;font-size:13px;color:#10b981;margin-top:12px">
        ✅ Warm-up complete! Sender started on <?php echo htmlspecialchars($warmup['start_date']); ?> and reached the maximum volume of <?php echo $warmup['max_vol']; ?> emails/day.
        You can disable warm-up mode in Settings.
    </div>
    <?php else: ?>
    <div style="margin-top:14px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:13px">Day <?php echo $warmup['current_day']; ?> of <?php echo $warmup['days']; ?></span>
            <span style="font-size:13px;color:#10b981">Today's limit: <strong><?php echo $warmup['daily_limit']; ?></strong> emails</span>
        </div>
        <div style="background:#1e3a5f;border-radius:4px;height:8px;overflow:hidden;margin-bottom:16px">
            <div style="width:<?php echo min(100, round($warmup['current_day']/$warmup['days']*100)); ?>%;height:100%;background:#f59e0b;border-radius:4px"></div>
        </div>

        <!-- Schedule preview (first 10 days) -->
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
                <tr style="color:#8a9ab5;border-bottom:1px solid #1e3a5f">
                    <th style="text-align:left;padding:6px 8px">Day</th>
                    <th style="text-align:left;padding:6px 8px">Volume</th>
                    <th style="text-align:left;padding:6px 8px">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($warmup['schedule'], 0, 21) as $day): ?>
            <tr style="border-bottom:1px solid #0d1b2e;<?php echo $day['today'] ? 'background:#1e3a5f;' : ''; ?>">
                <td style="padding:5px 8px;color:<?php echo $day['today'] ? '#e2e8f0' : '#8a9ab5'; ?>">
                    <?php echo $day['today'] ? '▶ ' : ''; ?>Day <?php echo $day['day']; ?>
                </td>
                <td style="padding:5px 8px;color:#e2e8f0"><?php echo $day['volume']; ?></td>
                <td style="padding:5px 8px">
                    <?php if ($day['done']): ?>
                    <span style="color:#10b981">✅ Done</span>
                    <?php elseif ($day['today']): ?>
                    <span style="color:#f59e0b">▶ Today</span>
                    <?php else: ?>
                    <span style="color:#8a9ab5">Pending</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function changeSpamTemplate(templateId) {
    const url = new URL(window.location.href);
    url.searchParams.set('check_template', templateId);
    window.location.href = url.toString();
}

async function runSpamCheck() {
    const subject = document.getElementById('check_subject').value;
    const body    = document.getElementById('check_body').value;
    const res     = document.getElementById('spam-check-result');
    if (!subject && !body) { showToast('Enter subject or body to check','warning'); return; }
    res.innerHTML = '<span style="color:#8a9ab5">Analyzing…</span>';
    try {
        const r = await fetch('<?php echo APP_URL; ?>/api/check_spam.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({subject, body})
        });
        const d = await r.json();
        const colors = {low:'#10b981', medium:'#f59e0b', high:'#ef4444', critical:'#dc2626'};
        const col = colors[d.risk_level] || '#8a9ab5';
        let html = `<div style="padding:14px;background:#0a1628;border:1px solid #1e3a5f;border-radius:8px">
            <div style="font-size:16px;font-weight:700;color:${col};margin-bottom:10px">
                Risk Score: ${d.score}/100 — ${d.risk_level.charAt(0).toUpperCase()+d.risk_level.slice(1)}
            </div>`;
        if (d.warnings && d.warnings.length) {
            html += d.warnings.map(w => `<div style="font-size:12px;color:#f59e0b;padding:2px 0">⚠️ ${escapeHtml(w)}</div>`).join('');
        }
        if (d.suggestions && d.suggestions.length) {
            html += '<div style="margin-top:8px">' + d.suggestions.map(s => `<div style="font-size:12px;color:#10b981;padding:2px 0">💡 ${escapeHtml(s)}</div>`).join('') + '</div>';
        }
        html += '</div>';
        res.innerHTML = html;
    } catch(e) {
        res.innerHTML = '<span style="color:#ef4444">❌ ' + e.message + '</span>';
    }
}

function recheckDns() {
    location.reload();
}

async function testWebhook() {
    const btn = document.getElementById('testWebhookBtn');
    const res = document.getElementById('webhookTestResult');
    btn.disabled = true;
    res.textContent = 'Testing…';
    res.style.color = '#8a9ab5';
    try {
        const r = await fetch('<?php echo APP_URL; ?>/api/webhook_diagnostics.php?action=test', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({test: true})
        });
        const d = await r.json();
        if (d.success) {
            res.textContent = '✅ Webhook endpoint reachable — ' + (d.message || 'OK');
            res.style.color = '#10b981';
        } else {
            res.textContent = '❌ ' + (d.error || 'Webhook test failed');
            res.style.color = '#ef4444';
        }
    } catch(e) {
        res.textContent = '❌ ' + e.message;
        res.style.color = '#ef4444';
    }
    btn.disabled = false;
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
