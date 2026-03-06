<?php
$pageTitle = 'Export Data';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireSuperAdmin();

$segmentRows = Database::fetchAll("SELECT DISTINCT segment FROM leads WHERE segment IS NOT NULL AND segment != '' ORDER BY segment ASC");
$segments = array_column($segmentRows, 'segment');
$statuses  = ['new','emailed','responded','converted','unsubscribed','bounced'];
$provinces = Database::fetchAll("SELECT DISTINCT province FROM leads WHERE province != '' ORDER BY province ASC");
?>

<h2 style="font-size:20px;margin-bottom:20px">⬇️ Export Data</h2>

<div class="grid-2">
    <div class="gc">
        <div class="gc-title">📊 Select Export</div>
        <div class="gc-sub">Choose what data to export and apply filters</div>

        <div style="margin-bottom:16px">
            <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Export Type</label>
            <select class="fi" id="exp_type" style="width:100%" onchange="updateFilters()">
                <option value="leads">Leads Database</option>
                <option value="campaigns">Campaigns</option>
                <option value="email_logs">Email Logs</option>
                <option value="responses">Responses</option>
                <option value="audit_log">Audit Log</option>
            </select>
        </div>

        <div style="margin-bottom:16px">
            <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Date Range</label>
            <div style="display:flex;gap:8px">
                <input class="fi" id="date_from" type="date" placeholder="From" style="flex:1">
                <input class="fi" id="date_to" type="date" placeholder="To" style="flex:1">
            </div>
        </div>

        <!-- Leads-specific filters -->
        <div id="leads-filters">
            <div style="margin-bottom:16px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Status Filter</label>
                <select class="fi" id="exp_status" style="width:100%">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $s): ?>
                    <option value="<?php echo $s; ?>"><?php echo ucfirst($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:16px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Segment Filter</label>
                <select class="fi" id="exp_segment" style="width:100%">
                    <option value="">All Segments</option>
                    <?php foreach ($segments as $s): ?>
                    <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:16px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Province Filter</label>
                <select class="fi" id="exp_province" style="width:100%">
                    <option value="">All Provinces</option>
                    <?php foreach ($provinces as $p): ?>
                    <option value="<?php echo htmlspecialchars($p['province']); ?>"><?php echo htmlspecialchars($p['province']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Campaign / log status filter -->
        <div id="campaign-filters" style="display:none">
            <div style="margin-bottom:16px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Status Filter</label>
                <select class="fi" id="exp_camp_status" style="width:100%">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="running">Running</option>
                    <option value="completed">Completed</option>
                    <option value="paused">Paused</option>
                </select>
            </div>
        </div>

        <button class="btn-launch" style="width:100%;margin-top:8px" onclick="doExport()">⬇️ Download CSV</button>
    </div>

    <div class="gc">
        <div class="gc-title">📋 Export Reference</div>
        <div class="gc-sub">Fields included per export type</div>

        <div id="exp-preview" style="font-size:13px;color:#8a9ab5;line-height:1.8">
            <div id="ep-leads">
                <strong style="color:#e2e8f0">Leads:</strong><br>
                ID, First Name, Last Name, Full Name, Email, Company, Job Title, Role, Segment,
                Country, Province, City, Source, Status, LinkedIn URL, Score, Notes, Created At
            </div>
            <div id="ep-campaigns" style="display:none">
                <strong style="color:#e2e8f0">Campaigns:</strong><br>
                ID, Campaign Key, Name, Template ID, Segment Filter, Province Filter,
                Total Leads, Sent, Failed, Status, Test Mode, Started At, Completed At, Created At
            </div>
            <div id="ep-email_logs" style="display:none">
                <strong style="color:#e2e8f0">Email Logs:</strong><br>
                ID, Campaign ID, Lead ID, Recipient Email, Recipient Name, Subject,
                Status, Follow-Up Seq, Sent At, Opened At, Created At
            </div>
            <div id="ep-responses" style="display:none">
                <strong style="color:#e2e8f0">Responses:</strong><br>
                ID, Lead ID, Campaign ID, From Email, From Name, Subject,
                Response Type, Is Read, Received At
            </div>
            <div id="ep-audit_log" style="display:none">
                <strong style="color:#e2e8f0">Audit Log:</strong><br>
                ID, User ID, Action, Entity Type, Entity ID, Details, IP Address, Created At
            </div>
        </div>

        <div style="margin-top:24px;padding:16px;background:#0a1628;border-radius:8px;font-size:12px;color:#8a9ab5">
            💡 <strong style="color:#e2e8f0">Tips:</strong><br>
            • CSV exports are UTF-8 with BOM for Excel compatibility<br>
            • Large datasets are streamed to avoid memory issues<br>
            • All timestamps are in the server timezone
        </div>
    </div>
</div>

<script>
function updateFilters(){
    var type = document.getElementById('exp_type').value;
    var leadTypes = ['leads'];
    var campTypes = ['campaigns','email_logs'];
    document.getElementById('leads-filters').style.display = leadTypes.includes(type) ? 'block' : 'none';
    document.getElementById('campaign-filters').style.display = campTypes.includes(type) ? 'block' : 'none';

    // Show correct preview
    ['leads','campaigns','email_logs','responses','audit_log'].forEach(function(t){
        document.getElementById('ep-'+t).style.display = t===type?'block':'none';
    });
}

function doExport(){
    var type     = document.getElementById('exp_type').value;
    var dateFrom = document.getElementById('date_from').value;
    var dateTo   = document.getElementById('date_to').value;
    var params   = { type: type };
    if(dateFrom) params.date_from = dateFrom;
    if(dateTo)   params.date_to   = dateTo;

    if(type === 'leads'){
        var status  = document.getElementById('exp_status').value;
        var segment = document.getElementById('exp_segment').value;
        var prov    = document.getElementById('exp_province').value;
        if(status)  params.status   = status;
        if(segment) params.segment  = segment;
        if(prov)    params.province = prov;
    } else if(['campaigns','email_logs'].includes(type)){
        var cs = document.getElementById('exp_camp_status').value;
        if(cs) params.status = cs;
    }

    var qs = Object.keys(params).map(function(k){ return encodeURIComponent(k)+'='+encodeURIComponent(params[k]); }).join('&');
    window.location.href = '<?php echo APP_URL; ?>/api/export.php?' + qs;
}
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
