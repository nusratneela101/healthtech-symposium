<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

// Calculate unread count from DB now that the database is loaded
$unreadCount = 0;
try {
    $unreadCount = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses WHERE is_read=0")['c'] ?? 0);
} catch (Exception $e) {
    error_log('Dashboard: unread_responses query failed: ' . $e->getMessage());
}

// KPI stats — each stat wrapped individually so one failure doesn't zero out all cards
$stats = [
    'total_leads'      => 0,
    'new_leads'        => 0,
    'emailed'          => 0,
    'responded'        => 0,
    'converted'        => 0,
    'unsubscribed'     => 0,
    'total_campaigns'  => 0,
    'emails_sent'      => 0,
    'unread_responses' => $unreadCount,
    'delivered'        => 0,
    'bounced'          => 0,
    'hot_leads'        => 0,
    'followups_sent'   => 0,
    'week_sends'       => 0,
    'month_sends'      => 0,
    'opened'           => 0,
];
try { $stats['total_leads']     = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: total_leads query failed: ' . $e->getMessage()); }
try { $stats['new_leads']       = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='new'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: new_leads query failed: ' . $e->getMessage()); }
try { $stats['emailed']         = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='emailed'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: emailed query failed: ' . $e->getMessage()); }
try { $stats['responded']       = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='responded'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: responded query failed: ' . $e->getMessage()); }
try { $stats['converted']       = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='converted'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: converted query failed: ' . $e->getMessage()); }
try { $stats['unsubscribed']    = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='unsubscribed'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: unsubscribed query failed: ' . $e->getMessage()); }
try { $stats['total_campaigns'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM campaigns")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: total_campaigns query failed: ' . $e->getMessage()); }
try { $stats['emails_sent']     = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE (status != '' AND status IS NOT NULL) OR (message_id != '' AND message_id IS NOT NULL)")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: emails_sent query failed: ' . $e->getMessage()); }
try { $stats['delivered']       = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status NOT IN ('failed','bounced') AND ((status IS NOT NULL AND status != '') OR (message_id IS NOT NULL AND message_id != ''))")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: delivered query failed: ' . $e->getMessage()); }
try { $stats['bounced']         = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status IN ('bounced','failed')")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: bounced query failed: ' . $e->getMessage()); }
// Fix: response_type column does not exist; using sentiment='positive' instead
try { $stats['hot_leads']       = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses WHERE sentiment='positive'")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: hot_leads query failed: ' . $e->getMessage()); }
try { $stats['followups_sent']  = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE follow_up_sequence=2")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: followups_sent query failed: ' . $e->getMessage()); }
try { $stats['week_sends']      = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE sent_at >= DATE(NOW() - INTERVAL WEEKDAY(NOW()) DAY)")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: week_sends query failed: ' . $e->getMessage()); }
try { $stats['month_sends']     = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE sent_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: month_sends query failed: ' . $e->getMessage()); }
try { $stats['opened']          = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE opened_at IS NOT NULL")['c'] ?? 0); } catch (Exception $e) { error_log('Dashboard: opened query failed: ' . $e->getMessage()); }

// Open rate calculation
$openRateStr = 'N/A';
if ($stats['emails_sent'] > 0 && $stats['opened'] > 0) {
    $openRateStr = round(($stats['opened'] / $stats['emails_sent']) * 100, 1) . '%';
}

// Daily email chart (last 14 days)
$daily = [];
try {
    $daily = Database::fetchAll(
        "SELECT DATE(sent_at) AS d, COUNT(*) AS cnt FROM email_logs
         WHERE status IN ('sent','delivered','opened','clicked','failed','bounced') AND sent_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
         GROUP BY DATE(sent_at) ORDER BY d ASC"
    );
} catch (Exception $e) { error_log('Dashboard: daily email chart query failed: ' . $e->getMessage()); }
$chartDates  = json_encode(array_column($daily, 'd'));
$chartCounts = json_encode(array_map('intval', array_column($daily, 'cnt')));

// Segments donut
$segments = [];
try {
    $segments = Database::fetchAll(
        "SELECT segment, COUNT(*) AS cnt FROM leads GROUP BY segment ORDER BY cnt DESC"
    );
} catch (Exception $e) { error_log('Dashboard: segments query failed: ' . $e->getMessage()); }
$segLabels = json_encode(array_column($segments, 'segment'));
$segCounts = json_encode(array_map('intval', array_column($segments, 'cnt')));

// Province bars
$provinces = [];
try {
    $provinces = Database::fetchAll(
        "SELECT province, COUNT(*) AS cnt FROM leads WHERE province != ''
         GROUP BY province ORDER BY cnt DESC LIMIT 8"
    );
} catch (Exception $e) { error_log('Dashboard: provinces query failed: ' . $e->getMessage()); }
$maxProv = $provinces ? max(array_column($provinces, 'cnt')) : 1;

// Campaign performance (last 5)
$campPerf = [];
try {
    $campPerf = Database::fetchAll(
        "SELECT c.name, c.sent_count, c.failed_count,
                COUNT(DISTINCT r.id) AS reply_count
         FROM campaigns c
         LEFT JOIN responses r ON r.campaign_id = c.id
         GROUP BY c.id ORDER BY c.created_at DESC LIMIT 5"
    );
} catch (Exception $e) { error_log('Dashboard: campaign performance query failed: ' . $e->getMessage()); }
$campNames    = json_encode(array_column($campPerf, 'name'));
$campSent     = json_encode(array_map('intval', array_column($campPerf, 'sent_count')));
$campFailed   = json_encode(array_map('intval', array_column($campPerf, 'failed_count')));
$campReplied  = json_encode(array_map('intval', array_column($campPerf, 'reply_count')));

// Hot leads — Fix: use sentiment='positive' (response_type column does not exist)
$hotLeads = [];
try {
    $hotLeads = Database::fetchAll(
        "SELECT r.id, r.email as from_email, r.subject, r.body, r.received_at,
                l.company, l.full_name as from_name
         FROM responses r
         LEFT JOIN leads l ON r.lead_id = l.id
         WHERE r.sentiment='positive'
         ORDER BY r.received_at DESC LIMIT 10"
    );
} catch (Exception $e) { error_log('Dashboard: hot leads query failed: ' . $e->getMessage()); }

// Activity feed
$activity = [];
try {
    $activity = Database::fetchAll(
        "SELECT 'email' AS type, recipient_email AS info, sent_at AS ts FROM email_logs WHERE status='sent'
         UNION ALL
         SELECT 'response', email, received_at FROM responses
         ORDER BY ts DESC LIMIT 10"
    );
} catch (Exception $e) { error_log('Dashboard: activity feed query failed: ' . $e->getMessage()); }

// Recent leads
$recentLeads = [];
try {
    $recentLeads = Database::fetchAll(
        "SELECT id, full_name, email, company, job_title, status, score, created_at FROM leads ORDER BY created_at DESC LIMIT 10"
    );
} catch (Exception $e) { error_log('Dashboard: recent leads query failed: ' . $e->getMessage()); }
$baseUrl = htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8');
?>

<div class="kpi-grid">
    <div class="kpi-card kc-blue" onclick="window.location='<?php echo $baseUrl; ?>/admin/leads.php'">
        <div class="kpi-icon">👥</div>
        <div class="kpi-val" id="kpi-total-leads" data-stat="total_leads"><?php echo number_format($stats['total_leads']); ?></div>
        <div class="kpi-lbl">Total Leads</div>
        <div class="kpi-trend t-up">↑ In Database</div>
    </div>
    <div class="kpi-card kc-green" onclick="window.location='<?php echo $baseUrl; ?>/admin/export.php?type=email_logs'">
        <div class="kpi-icon">✉️</div>
        <div class="kpi-val" id="kpi-emails-sent" data-stat="emails_sent"><?php echo number_format($stats['emails_sent']); ?></div>
        <div class="kpi-lbl">Emails Sent</div>
        <div class="kpi-trend t-up">↑ All Time</div>
    </div>
    <div class="kpi-card kc-yellow" onclick="window.location='<?php echo $baseUrl; ?>/admin/responses.php'">
        <div class="kpi-icon">💬</div>
        <div class="kpi-val" id="kpi-responded" data-stat="responded"><?php echo number_format($stats['responded']); ?></div>
        <div class="kpi-lbl">Responses</div>
        <div class="kpi-trend t-nt">→ Responded</div>
    </div>
    <div class="kpi-card kc-purple" onclick="window.location='<?php echo $baseUrl; ?>/admin/leads.php?status=responded'">
        <div class="kpi-icon">🎯</div>
        <div class="kpi-val" id="kpi-converted" data-stat="converted"><?php echo number_format($stats['converted']); ?></div>
        <div class="kpi-lbl">Converted</div>
        <div class="kpi-trend t-up">↑ Registered</div>
    </div>
    <div class="kpi-card kc-cyan" onclick="window.location='<?php echo $baseUrl; ?>/admin/leads.php?status=new'">
        <div class="kpi-icon">🆕</div>
        <div class="kpi-val" id="kpi-new-leads" data-stat="new_leads"><?php echo number_format($stats['new_leads']); ?></div>
        <div class="kpi-lbl">New Leads</div>
        <div class="kpi-trend t-nt">→ Pending</div>
    </div>
    <div class="kpi-card kc-red" onclick="window.location='<?php echo $baseUrl; ?>/admin/responses.php?filter=unread'">
        <div class="kpi-icon">📬</div>
        <div class="kpi-val" id="kpi-unread" data-stat="unread_responses"><?php echo number_format($stats['unread_responses']); ?></div>
        <div class="kpi-lbl">Unread Responses</div>
        <div class="kpi-trend t-dn">↓ Needs Action</div>
    </div>
    <div class="kpi-card kc-blue" onclick="window.location='<?php echo $baseUrl; ?>/admin/campaign.php'">
        <div class="kpi-icon">🚀</div>
        <div class="kpi-val" id="kpi-campaigns" data-stat="total_campaigns"><?php echo number_format($stats['total_campaigns']); ?></div>
        <div class="kpi-lbl">Campaigns</div>
        <div class="kpi-trend t-up">↑ Created</div>
    </div>
    <div class="kpi-card kc-yellow" onclick="window.location='<?php echo $baseUrl; ?>/admin/leads.php?status=unsubscribed'">
        <div class="kpi-icon">🔕</div>
        <div class="kpi-val" id="kpi-unsubscribed" data-stat="unsubscribed"><?php echo number_format($stats['unsubscribed']); ?></div>
        <div class="kpi-lbl">Unsubscribed</div>
        <div class="kpi-trend t-dn">↓ Opted Out</div>
    </div>
    <div class="kpi-card kc-green" onclick="window.location='<?php echo $baseUrl; ?>/admin/export.php?type=email_logs&amp;status=delivered'">
        <div class="kpi-icon">📥</div>
        <div class="kpi-val" id="kpi-delivered" data-stat="delivered"><?php echo number_format($stats['delivered']); ?></div>
        <div class="kpi-lbl">Delivered</div>
        <div class="kpi-trend t-up">↑ Confirmed</div>
    </div>
    <div class="kpi-card kc-red" onclick="window.location='<?php echo $baseUrl; ?>/admin/leads.php?status=bounced'">
        <div class="kpi-icon">⛔</div>
        <div class="kpi-val" id="kpi-bounced" data-stat="bounced"><?php echo number_format($stats['bounced']); ?></div>
        <div class="kpi-lbl">Bounced / Failed</div>
        <div class="kpi-trend t-dn">↓ Errors</div>
    </div>
    <div class="kpi-card kc-cyan" onclick="window.location='<?php echo $baseUrl; ?>/admin/email_health.php'">
        <div class="kpi-icon">📊</div>
        <div class="kpi-val" id="kpi-open-rate" data-stat="open_rate"><?php echo $openRateStr; ?></div>
        <div class="kpi-lbl">Open Rate</div>
        <div class="kpi-trend t-nt"><?php echo $stats['opened'] ? '→ Tracked' : '→ No tracking data'; ?></div>
    </div>
    <div class="kpi-card kc-purple" onclick="window.location='<?php echo $baseUrl; ?>/admin/responses.php?filter=positive'">
        <div class="kpi-icon">🔥</div>
        <div class="kpi-val" id="kpi-hot-leads" data-stat="hot_leads"><?php echo number_format($stats['hot_leads']); ?></div>
        <div class="kpi-lbl">Hot Leads</div>
        <div class="kpi-trend t-up">↑ Interested</div>
    </div>
    <div class="kpi-card kc-blue" onclick="window.location='<?php echo $baseUrl; ?>/admin/export.php?type=email_logs'">
        <div class="kpi-icon">🔁</div>
        <div class="kpi-val" id="kpi-followups" data-stat="followups_sent"><?php echo number_format($stats['followups_sent']); ?></div>
        <div class="kpi-lbl">Follow-ups Sent</div>
        <div class="kpi-trend t-up">↑ Seq. 2</div>
    </div>
    <div class="kpi-card kc-green" onclick="window.location='<?php echo $baseUrl; ?>/admin/email_health.php'">
        <div class="kpi-icon">📅</div>
        <div class="kpi-val" id="kpi-week" data-stat="week_sends"><?php echo number_format($stats['week_sends']); ?></div>
        <div class="kpi-lbl">This Week</div>
        <div class="kpi-trend t-up">↑ Sent</div>
    </div>
    <div class="kpi-card kc-yellow" onclick="window.location='<?php echo $baseUrl; ?>/admin/email_health.php'">
        <div class="kpi-icon">🗓️</div>
        <div class="kpi-val" id="kpi-month" data-stat="month_sends"><?php echo number_format($stats['month_sends']); ?></div>
        <div class="kpi-lbl">This Month</div>
        <div class="kpi-trend t-up">↑ Sent</div>
    </div>
</div>

<div class="gc" style="margin-top:20px" id="limits-widget">
    <div class="gc-title">📊 Today's Sending Limits</div>
    <div class="gc-sub">Real-time usage against configured limits</div>
    <div id="limits-content" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:16px">
        <div style="text-align:center;color:#8a9ab5;font-size:13px">Loading...</div>
    </div>
</div>

<div class="grid-2">
    <div class="gc">
        <div class="gc-title">📈 Daily Email Activity (14 Days)</div>
        <div class="gc-sub">Sent emails per day</div>
        <div id="areaChart" style="min-height:200px"></div>
    </div>
    <div class="gc">
        <div class="gc-title">🥧 Leads by Segment</div>
        <div class="gc-sub">Distribution across segments</div>
        <div id="donutChart" style="min-height:200px"></div>
    </div>
</div>

<div class="gc">
    <div class="gc-title">📊 Campaign Performance</div>
    <div class="gc-sub">Last 5 campaigns — sent / failed / replies</div>
    <div id="campChart" style="min-height:220px"></div>
    <?php foreach ($campPerf as $cp): ?>
    <div style="display:flex;justify-content:space-between;font-size:12px;color:#8a9ab5;padding:6px 0;border-bottom:1px solid #1e3355">
        <span><?php echo htmlspecialchars($cp['name']); ?></span>
        <span>
            <span style="color:#10b981">✉ <?php echo $cp['sent_count']; ?></span> &nbsp;
            <span style="color:#ef4444">✗ <?php echo $cp['failed_count']; ?></span> &nbsp;
            <span style="color:#f59e0b">💬 <?php echo $cp['reply_count']; ?></span>
            <?php if ($cp['sent_count'] > 0): ?>
            <span style="color:#8b5cf6"> · <?php echo round(($cp['reply_count'] / $cp['sent_count']) * 100, 1); ?>% reply rate</span>
            <?php endif; ?>
        </span>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($hotLeads): ?>
<div class="gc">
    <div class="gc-title">🔥 Hot Leads</div>
    <div class="gc-sub">Leads who expressed interest</div>
    <?php foreach ($hotLeads as $hl): ?>
    <div class="inbox-item">
        <div class="ia" style="background:linear-gradient(135deg,#f59e0b,#ef4444)"><?php echo strtoupper(substr($hl['from_name'] ?: $hl['from_email'], 0, 1)); ?></div>
        <div style="flex:1;min-width:0">
            <div class="if">
                <strong><?php echo htmlspecialchars($hl['from_name'] ?: $hl['from_email']); ?></strong>
                <?php if ($hl['company']): ?>
                    <small style="color:#8a9ab5"> · <?php echo htmlspecialchars($hl['company']); ?></small>
                <?php endif; ?>
                <span class="pill p-interested" style="margin-left:8px">Interested</span>
            </div>
            <div class="is"><?php echo htmlspecialchars($hl['subject']); ?></div>
            <div class="ip"><?php echo htmlspecialchars(substr(strip_tags($hl['body']), 0, 120)); ?>…</div>
        </div>
        <div style="font-size:12px;color:#8a9ab5;white-space:nowrap"><?php echo timeAgo($hl['received_at']); ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid-2">
    <div class="gc">
        <div class="gc-title">🗺️ Top Provinces</div>
        <div class="gc-sub">Lead distribution by province</div>
        <?php foreach ($provinces as $p): ?>
        <div class="prov-row">
            <div class="prov-name"><?php echo htmlspecialchars($p['province']); ?></div>
            <div class="prov-bar">
                <div class="prov-fill" data-width="<?php echo round(($p['cnt']/$maxProv)*100); ?>%"></div>
            </div>
            <div style="font-size:12px;color:#8a9ab5;min-width:32px;text-align:right"><?php echo $p['cnt']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="gc">
        <div class="gc-title">⚡ Recent Activity</div>
        <div class="gc-sub">Latest platform events</div>
        <?php foreach ($activity as $a): ?>
        <div class="act-item">
            <div class="act-dot"></div>
            <div class="act-txt">
                <?php echo $a['type'] === 'email' ? '✉️ Sent to ' : '💬 Reply from '; ?>
                <strong><?php echo htmlspecialchars($a['info']); ?></strong>
            </div>
            <div class="act-time"><?php echo timeAgo($a['ts']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="gc">
    <div class="gc-title">👥 Recent Leads</div>
    <div class="gc-sub">Latest additions to the database</div>
    <div class="tbl-wrap">
        <table class="dt">
            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Company</th><th>Job Title</th><th>Status</th><th>Score</th><th>Added</th></tr></thead>
            <tbody>
            <?php foreach ($recentLeads as $l): ?>
            <tr>
                <td><?php echo $l['id']; ?></td>
                <td><?php echo htmlspecialchars($l['full_name']); ?></td>
                <td><?php echo htmlspecialchars($l['email']); ?></td>
                <td><?php echo htmlspecialchars($l['company']); ?></td>
                <td><?php echo htmlspecialchars($l['job_title']); ?></td>
                <td><?php echo pill($l['status']); ?></td>
                <td>
                    <?php
                    $sc = (int)($l['score'] ?? 0);
                    $sc_color = $sc >= 50 ? '#ef4444' : ($sc >= 20 ? '#f59e0b' : '#8a9ab5');
                    echo '<span style="font-weight:600;color:'.$sc_color.'">'.$sc.'</span>';
                    ?>
                </td>
                <td><?php echo timeAgo($l['created_at']); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
new ApexCharts(document.getElementById('areaChart'), {
    series: [{name:'Emails Sent',data:<?php echo $chartCounts; ?>}],
    chart:{type:'area',height:200,background:'transparent',toolbar:{show:false}},
    colors:['#0d6efd'],
    fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:.4,opacityTo:.05}},
    xaxis:{categories:<?php echo $chartDates; ?>,labels:{style:{colors:'#8a9ab5',fontSize:'11px'}}},
    yaxis:{labels:{style:{colors:'#8a9ab5'}}},
    grid:{borderColor:'#1e3355'},
    stroke:{curve:'smooth',width:2},
    tooltip:{theme:'dark'}
}).render();

new ApexCharts(document.getElementById('donutChart'), {
    series: <?php echo $segCounts; ?>,
    labels: <?php echo $segLabels; ?>,
    chart:{type:'donut',height:200,background:'transparent'},
    colors:['#0d6efd','#8b5cf6','#10b981','#f59e0b','#ef4444'],
    legend:{labels:{colors:'#8a9ab5'},position:'bottom'},
    tooltip:{theme:'dark'},
    dataLabels:{enabled:false},
    plotOptions:{pie:{donut:{size:'60%'}}}
}).render();

new ApexCharts(document.getElementById('campChart'), {
    series: [
        {name:'Sent',    data:<?php echo $campSent; ?>},
        {name:'Failed',  data:<?php echo $campFailed; ?>},
        {name:'Replies', data:<?php echo $campReplied; ?>}
    ],
    chart:{type:'bar',height:220,background:'transparent',toolbar:{show:false}},
    colors:['#10b981','#ef4444','#f59e0b'],
    xaxis:{categories:<?php echo $campNames; ?>,labels:{style:{colors:'#8a9ab5',fontSize:'11px'}}},
    yaxis:{labels:{style:{colors:'#8a9ab5'}}},
    grid:{borderColor:'#1e3355'},
    plotOptions:{bar:{columnWidth:'60%',borderRadius:4}},
    legend:{labels:{colors:'#8a9ab5'}},
    tooltip:{theme:'dark'}
}).render();

(async function loadDashboardLimits() {
    try {
        const r = await fetch('<?php echo APP_URL; ?>/api/get_sending_stats.php');
        const d = await r.json();
        const container = document.getElementById('limits-content');
        if (!container) return;
        const allRows = [
            ...d.campaign.map(x => ({ ...x, type: 'Campaign' })),
            ...d.followup.map(x => ({ ...x, type: 'Follow-up' })),
        ];
        container.innerHTML = allRows.map(x => {
            const pct = Math.min(x.pct, 100);
            const barColor = pct >= 90 ? '#ef4444' : pct >= 70 ? '#f59e0b' : '#10b981';
            const caption = x.limit === 0
                ? `${x.sent} sent — Unlimited`
                : `${x.sent} / ${x.limit} (${pct}%)`;
            return `<div style="background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:12px">
                <div style="font-size:12px;color:#8a9ab5;margin-bottom:4px">${x.type} · ${x.label}</div>
                <div style="font-size:18px;font-weight:700;color:#e2e8f0">${x.sent}</div>
                <div style="background:#1e3355;border-radius:4px;height:5px;margin:6px 0;overflow:hidden">
                    <div style="height:100%;width:${pct}%;background:${barColor};border-radius:4px;transition:width .4s"></div>
                </div>
                <div style="font-size:11px;color:#8a9ab5">${caption}</div>
            </div>`;
        }).join('');
    } catch(e) {}
})();

// ── Live KPI Refresh ──────────────────────────────────────────────────────
(function pollKpis() {
    function refresh() {
        fetch('<?php echo APP_URL; ?>/api/dashboard_stats.php')
            .then(function(r){ return r.json(); })
            .then(function(d){
                document.querySelectorAll('.kpi-val[data-stat]').forEach(function(el){
                    var key = el.getAttribute('data-stat');
                    if (key === 'open_rate') {
                        el.textContent = d.open_rate_str || 'N/A';
                    } else if (d[key] !== undefined) {
                        el.textContent = Number(d[key]).toLocaleString();
                    }
                });
            })
            .catch(function(){});
    }
    // Poll every 30 seconds, pause when tab is hidden
    setInterval(function(){ if (!document.hidden) refresh(); }, 30000);
    // Also run immediately on load
    refresh();
})();
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>