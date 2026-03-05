<?php
require_once __DIR__ . '/../includes/layout.php';

// KPI stats
$stats = [
    'total_leads'      => Database::fetchOne("SELECT COUNT(*) AS c FROM leads")['c'] ?? 0,
    'new_leads'        => Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='new'")['c'] ?? 0,
    'emailed'          => Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='emailed'")['c'] ?? 0,
    'responded'        => Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='responded'")['c'] ?? 0,
    'converted'        => Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='converted'")['c'] ?? 0,
    'unsubscribed'     => Database::fetchOne("SELECT COUNT(*) AS c FROM leads WHERE status='unsubscribed'")['c'] ?? 0,
    'total_campaigns'  => Database::fetchOne("SELECT COUNT(*) AS c FROM campaigns")['c'] ?? 0,
    'emails_sent'      => Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='sent'")['c'] ?? 0,
    'unread_responses' => $unreadCount,
    'delivered'        => Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='delivered'")['c'] ?? 0,
    'bounced'          => Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status IN ('bounced','failed')")['c'] ?? 0,
    'hot_leads'        => Database::fetchOne("SELECT COUNT(*) AS c FROM responses WHERE response_type='interested'")['c'] ?? 0,
    'followups_sent'   => Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE follow_up_sequence=2")['c'] ?? 0,
    'week_sends'       => Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE sent_at >= DATE(NOW() - INTERVAL WEEKDAY(NOW()) DAY)")['c'] ?? 0,
    'month_sends'      => Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE sent_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")['c'] ?? 0,
    'opened'           => Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE opened_at IS NOT NULL")['c'] ?? 0,
];

// Open rate calculation
$openRateStr = 'N/A';
if ($stats['emails_sent'] > 0 && $stats['opened'] > 0) {
    $openRateStr = round(($stats['opened'] / $stats['emails_sent']) * 100, 1) . '%';
}

// Daily email chart (last 14 days)
$daily = Database::fetchAll(
    "SELECT DATE(sent_at) AS d, COUNT(*) AS cnt FROM email_logs
     WHERE status='sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     GROUP BY DATE(sent_at) ORDER BY d ASC"
);
$chartDates  = json_encode(array_column($daily, 'd'));
$chartCounts = json_encode(array_map('intval', array_column($daily, 'cnt')));

// Segments donut
$segments = Database::fetchAll(
    "SELECT segment, COUNT(*) AS cnt FROM leads GROUP BY segment ORDER BY cnt DESC"
);
$segLabels = json_encode(array_column($segments, 'segment'));
$segCounts = json_encode(array_map('intval', array_column($segments, 'cnt')));

// Province bars
$provinces = Database::fetchAll(
    "SELECT province, COUNT(*) AS cnt FROM leads WHERE province != ''
     GROUP BY province ORDER BY cnt DESC LIMIT 8"
);
$maxProv = $provinces ? max(array_column($provinces, 'cnt')) : 1;

// Campaign performance (last 5)
$campPerf = Database::fetchAll(
    "SELECT c.name, c.sent_count, c.failed_count,
            COUNT(DISTINCT r.id) AS reply_count
     FROM campaigns c
     LEFT JOIN responses r ON r.campaign_id = c.id
     GROUP BY c.id ORDER BY c.created_at DESC LIMIT 5"
);
$campNames    = json_encode(array_column($campPerf, 'name'));
$campSent     = json_encode(array_map('intval', array_column($campPerf, 'sent_count')));
$campFailed   = json_encode(array_map('intval', array_column($campPerf, 'failed_count')));
$campReplied  = json_encode(array_map('intval', array_column($campPerf, 'reply_count')));

// Follow-up effectiveness
$followupStats = Database::fetchAll(
    "SELECT
        c.name,
        SUM(CASE WHEN el.follow_up_sequence = 1 THEN 1 ELSE 0 END) AS first_sent,
        SUM(CASE WHEN el.follow_up_sequence >= 2 THEN 1 ELSE 0 END) AS followup_sent,
        COUNT(DISTINCT r.id) AS total_responses
     FROM campaigns c
     JOIN email_logs el ON el.campaign_id = c.id AND el.status = 'sent'
     LEFT JOIN responses r ON r.campaign_id = c.id
     GROUP BY c.id
     HAVING followup_sent > 0
     ORDER BY c.created_at DESC LIMIT 5"
);

// Hot leads
$hotLeads = Database::fetchAll(
    "SELECT r.id, r.from_name, r.from_email, r.subject, r.body_text, r.received_at,
            l.company
     FROM responses r
     LEFT JOIN leads l ON r.lead_id = l.id
     WHERE r.response_type='interested'
     ORDER BY r.received_at DESC LIMIT 10"
);

// Activity feed
$activity = Database::fetchAll(
    "SELECT 'email' AS type, recipient_email AS info, sent_at AS ts FROM email_logs WHERE status='sent'
     UNION ALL
     SELECT 'response', from_email, received_at FROM responses
     ORDER BY ts DESC LIMIT 10"
);

// Recent leads
$recentLeads = Database::fetchAll(
    "SELECT id, full_name, email, company, job_title, status, score, created_at FROM leads ORDER BY created_at DESC LIMIT 10"
);
?>

<div class="kpi-grid">
    <div class="kpi-card kc-blue">
        <div class="kpi-icon">👥</div>
        <div class="kpi-val"><?php echo number_format($stats['total_leads']); ?></div>
        <div class="kpi-lbl">Total Leads</div>
        <div class="kpi-trend t-up">↑ In Database</div>
    </div>
    <div class="kpi-card kc-green">
        <div class="kpi-icon">✉️</div>
        <div class="kpi-val"><?php echo number_format($stats['emails_sent']); ?></div>
        <div class="kpi-lbl">Emails Sent</div>
        <div class="kpi-trend t-up">↑ All Time</div>
    </div>
    <div class="kpi-card kc-yellow">
        <div class="kpi-icon">💬</div>
        <div class="kpi-val"><?php echo number_format($stats['responded']); ?></div>
        <div class="kpi-lbl">Responses</div>
        <div class="kpi-trend t-nt">→ Responded</div>
    </div>
    <div class="kpi-card kc-purple">
        <div class="kpi-icon">🎯</div>
        <div class="kpi-val"><?php echo number_format($stats['converted']); ?></div>
        <div class="kpi-lbl">Converted</div>
        <div class="kpi-trend t-up">↑ Registered</div>
    </div>
    <div class="kpi-card kc-cyan">
        <div class="kpi-icon">🆕</div>
        <div class="kpi-val"><?php echo number_format($stats['new_leads']); ?></div>
        <div class="kpi-lbl">New Leads</div>
        <div class="kpi-trend t-nt">→ Pending</div>
    </div>
    <div class="kpi-card kc-red">
        <div class="kpi-icon">📬</div>
        <div class="kpi-val"><?php echo number_format($stats['unread_responses']); ?></div>
        <div class="kpi-lbl">Unread Responses</div>
        <div class="kpi-trend t-dn">↓ Needs Action</div>
    </div>
    <div class="kpi-card kc-blue">
        <div class="kpi-icon">🚀</div>
        <div class="kpi-val"><?php echo number_format($stats['total_campaigns']); ?></div>
        <div class="kpi-lbl">Campaigns</div>
        <div class="kpi-trend t-up">↑ Created</div>
    </div>
    <div class="kpi-card kc-yellow">
        <div class="kpi-icon">🔕</div>
        <div class="kpi-val"><?php echo number_format($stats['unsubscribed']); ?></div>
        <div class="kpi-lbl">Unsubscribed</div>
        <div class="kpi-trend t-dn">↓ Opted Out</div>
    </div>
    <div class="kpi-card kc-green">
        <div class="kpi-icon">📥</div>
        <div class="kpi-val"><?php echo number_format($stats['delivered']); ?></div>
        <div class="kpi-lbl">Delivered</div>
        <div class="kpi-trend t-up">↑ Confirmed</div>
    </div>
    <div class="kpi-card kc-red">
        <div class="kpi-icon">⛔</div>
        <div class="kpi-val"><?php echo number_format($stats['bounced']); ?></div>
        <div class="kpi-lbl">Bounced / Failed</div>
        <div class="kpi-trend t-dn">↓ Errors</div>
    </div>
    <div class="kpi-card kc-cyan">
        <div class="kpi-icon">📊</div>
        <div class="kpi-val"><?php echo $openRateStr; ?></div>
        <div class="kpi-lbl">Open Rate</div>
        <div class="kpi-trend t-nt"><?php echo $stats['opened'] ? '→ Tracked' : '→ No tracking data'; ?></div>
    </div>
    <div class="kpi-card kc-purple">
        <div class="kpi-icon">🔥</div>
        <div class="kpi-val"><?php echo number_format($stats['hot_leads']); ?></div>
        <div class="kpi-lbl">Hot Leads</div>
        <div class="kpi-trend t-up">↑ Interested</div>
    </div>
    <div class="kpi-card kc-blue">
        <div class="kpi-icon">🔁</div>
        <div class="kpi-val"><?php echo number_format($stats['followups_sent']); ?></div>
        <div class="kpi-lbl">Follow-ups Sent</div>
        <div class="kpi-trend t-up">↑ Seq. 2</div>
    </div>
    <div class="kpi-card kc-green">
        <div class="kpi-icon">📅</div>
        <div class="kpi-val"><?php echo number_format($stats['week_sends']); ?></div>
        <div class="kpi-lbl">This Week</div>
        <div class="kpi-trend t-up">↑ Sent</div>
    </div>
    <div class="kpi-card kc-yellow">
        <div class="kpi-icon">🗓️</div>
        <div class="kpi-val"><?php echo number_format($stats['month_sends']); ?></div>
        <div class="kpi-lbl">This Month</div>
        <div class="kpi-trend t-up">↑ Sent</div>
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

<?php if ($followupStats): ?>
<div class="gc">
    <div class="gc-title">🔁 Follow-up Effectiveness</div>
    <div class="gc-sub">Campaigns with follow-up emails — send counts and response totals</div>
    <div class="tbl-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>1st Emails Sent</th>
                    <th>Follow-ups Sent</th>
                    <th>Total Responses</th>
                    <th>Overall Reply Rate</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($followupStats as $fs): ?>
            <?php
                $totalSent   = (int)$fs['first_sent'] + (int)$fs['followup_sent'];
                $replyRate   = $totalSent > 0 ? round(((int)$fs['total_responses'] / $totalSent) * 100, 1) : 0;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($fs['name']); ?></td>
                <td><?php echo (int)$fs['first_sent']; ?></td>
                <td style="color:#8b5cf6"><?php echo (int)$fs['followup_sent']; ?></td>
                <td style="color:#10b981"><?php echo (int)$fs['total_responses']; ?></td>
                <td><span class="pill <?php echo $replyRate >= 5 ? 'p-responded' : 'p-queued'; ?>"><?php echo $replyRate; ?>%</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

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
            <div class="ip"><?php echo htmlspecialchars(substr(strip_tags($hl['body_text']), 0, 120)); ?>…</div>
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
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
