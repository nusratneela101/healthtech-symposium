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
];

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

// Activity feed
$activity = Database::fetchAll(
    "SELECT 'email' AS type, recipient_email AS info, sent_at AS ts FROM email_logs WHERE status='sent'
     UNION ALL
     SELECT 'response', from_email, received_at FROM responses
     ORDER BY ts DESC LIMIT 10"
);

// Recent leads
$recentLeads = Database::fetchAll(
    "SELECT id, full_name, email, company, job_title, status, created_at FROM leads ORDER BY created_at DESC LIMIT 10"
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
            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Company</th><th>Job Title</th><th>Status</th><th>Added</th></tr></thead>
            <tbody>
            <?php foreach ($recentLeads as $l): ?>
            <tr>
                <td><?php echo $l['id']; ?></td>
                <td><?php echo htmlspecialchars($l['full_name']); ?></td>
                <td><?php echo htmlspecialchars($l['email']); ?></td>
                <td><?php echo htmlspecialchars($l['company']); ?></td>
                <td><?php echo htmlspecialchars($l['job_title']); ?></td>
                <td><?php echo pill($l['status']); ?></td>
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
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
