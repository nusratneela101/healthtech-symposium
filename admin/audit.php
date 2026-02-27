<?php
require_once __DIR__ . '/../includes/layout.php';

// Summary stats
$totalSent     = Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='sent'")['c'] ?? 0;
$totalFailed   = Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='failed'")['c'] ?? 0;
$totalResponded= Database::fetchOne("SELECT COUNT(*) AS c FROM responses")['c'] ?? 0;
$totalCampaigns= Database::fetchOne("SELECT COUNT(*) AS c FROM campaigns")['c'] ?? 0;
$responseRate  = $totalSent > 0 ? round($totalResponded / $totalSent * 100, 1) : 0;

// Campaign performance
$campaigns = Database::fetchAll(
    "SELECT c.id, c.name, c.sent_count, c.failed_count, c.total_leads, c.status, c.created_at,
            COUNT(r.id) AS response_count
     FROM campaigns c
     LEFT JOIN responses r ON r.campaign_id = c.id
     GROUP BY c.id ORDER BY c.created_at DESC"
);

// Daily logs (30 days)
$daily = Database::fetchAll(
    "SELECT DATE(sent_at) AS d, COUNT(*) AS cnt FROM email_logs
     WHERE status='sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY DATE(sent_at) ORDER BY d ASC"
);
$chartDates  = json_encode(array_column($daily, 'd'));
$chartCounts = json_encode(array_map('intval', array_column($daily, 'cnt')));

// Export to CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Campaign', 'Total Leads', 'Sent', 'Failed', 'Responses', 'Status', 'Created']);
    foreach ($campaigns as $c) {
        fputcsv($out, [$c['name'], $c['total_leads'], $c['sent_count'], $c['failed_count'], $c['response_count'], $c['status'], $c['created_at']]);
    }
    fclose($out);
    exit;
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:20px">📋 Audit Report</h2>
    <a href="?export=1" class="btn-sec" style="text-decoration:none;font-size:13px">⬇️ Export CSV</a>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(5,1fr)">
    <div class="kpi-card kc-blue">
        <div class="kpi-icon">🚀</div>
        <div class="kpi-val"><?php echo $totalCampaigns; ?></div>
        <div class="kpi-lbl">Total Campaigns</div>
    </div>
    <div class="kpi-card kc-green">
        <div class="kpi-icon">✉️</div>
        <div class="kpi-val"><?php echo number_format($totalSent); ?></div>
        <div class="kpi-lbl">Emails Sent</div>
    </div>
    <div class="kpi-card kc-red">
        <div class="kpi-icon">⚠️</div>
        <div class="kpi-val"><?php echo number_format($totalFailed); ?></div>
        <div class="kpi-lbl">Failed</div>
    </div>
    <div class="kpi-card kc-yellow">
        <div class="kpi-icon">💬</div>
        <div class="kpi-val"><?php echo number_format($totalResponded); ?></div>
        <div class="kpi-lbl">Responses</div>
    </div>
    <div class="kpi-card kc-purple">
        <div class="kpi-icon">📈</div>
        <div class="kpi-val"><?php echo $responseRate; ?>%</div>
        <div class="kpi-lbl">Response Rate</div>
    </div>
</div>

<div class="gc" style="margin-bottom:20px">
    <div class="gc-title">📈 Emails Sent (Last 30 Days)</div>
    <div id="auditChart" style="min-height:200px"></div>
</div>

<div class="gc">
    <div class="gc-title">📊 Campaign Performance</div>
    <div class="tbl-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>#</th><th>Campaign Name</th><th>Total Leads</th>
                    <th>Sent</th><th>Failed</th><th>Responses</th>
                    <th>Response Rate</th><th>Status</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($campaigns as $c): ?>
            <?php $rr = $c['sent_count'] > 0 ? round($c['response_count']/$c['sent_count']*100,1) : 0; ?>
            <tr>
                <td><?php echo $c['id']; ?></td>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td><?php echo $c['total_leads']; ?></td>
                <td style="color:#10b981"><?php echo $c['sent_count']; ?></td>
                <td style="color:#ef4444"><?php echo $c['failed_count']; ?></td>
                <td style="color:#f59e0b"><?php echo $c['response_count']; ?></td>
                <td><?php echo $rr; ?>%</td>
                <td><?php echo pill($c['status']); ?></td>
                <td style="font-size:12px"><?php echo timeAgo($c['created_at']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($campaigns)): ?>
            <tr><td colspan="9" style="text-align:center;color:#8a9ab5;padding:32px">No campaigns yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
new ApexCharts(document.getElementById('auditChart'), {
    series: [{name:'Emails Sent',data:<?php echo $chartCounts; ?>}],
    chart:{type:'bar',height:200,background:'transparent',toolbar:{show:false}},
    colors:['#0d6efd'],
    xaxis:{categories:<?php echo $chartDates; ?>,labels:{style:{colors:'#8a9ab5',fontSize:'11px'}}},
    yaxis:{labels:{style:{colors:'#8a9ab5'}}},
    grid:{borderColor:'#1e3355'},
    tooltip:{theme:'dark'},
    dataLabels:{enabled:false}
}).render();
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
