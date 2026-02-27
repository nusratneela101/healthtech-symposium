<?php
require_once __DIR__ . '/../includes/layout.php';

$message = '';
$errors  = [];

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle  = fopen($file['tmp_name'], 'r');
        $headers = array_map('strtolower', array_map('trim', fgetcsv($handle)));
        $created = 0;
        $skipped = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, array_pad($row, count($headers), ''));
            $email = strtolower(trim($data['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped++; continue; }
            $firstName = trim($data['first_name'] ?? '');
            $lastName  = trim($data['last_name']  ?? '');
            $fullName  = trim($data['full_name']  ?? "$firstName $lastName");
            $segment   = trim($data['segment'] ?? 'Other');
            $validSegs = ['Financial Institutions','Technology & Solution Providers','Venture Capital / Investors','FinTech Startups','Other'];
            if (!in_array($segment, $validSegs)) $segment = 'Other';
            try {
                Database::query(
                    "INSERT IGNORE INTO leads
                     (first_name,last_name,full_name,email,company,job_title,role,segment,country,province,city,source)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $firstName, $lastName, $fullName ?: "$firstName $lastName", $email,
                        trim($data['company'] ?? ''), trim($data['job_title'] ?? ''),
                        trim($data['role'] ?? ''), $segment,
                        trim($data['country'] ?? 'Canada'), trim($data['province'] ?? ''),
                        trim($data['city'] ?? ''), trim($data['source'] ?? 'CSV Import'),
                    ]
                );
                $created++;
            } catch (Exception $e) {
                $skipped++;
            }
        }
        fclose($handle);
        flash('success', "Import complete: $created created, $skipped skipped.");
        header('Location: ' . APP_URL . '/admin/import_leads.php');
        exit;
    } else {
        $errors[] = 'File upload failed.';
    }
}

// Handle manual add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_email'])) {
    $email = strtolower(trim($_POST['manual_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    } else {
        $fn = trim($_POST['first_name'] ?? '');
        $ln = trim($_POST['last_name']  ?? '');
        try {
            Database::query(
                "INSERT IGNORE INTO leads
                 (first_name,last_name,full_name,email,company,job_title,role,segment,country,province,city,source)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $fn, $ln, "$fn $ln", $email,
                    trim($_POST['company'] ?? ''), trim($_POST['job_title'] ?? ''),
                    trim($_POST['role'] ?? ''), trim($_POST['segment'] ?? 'Other'),
                    trim($_POST['country'] ?? 'Canada'), trim($_POST['province'] ?? ''),
                    trim($_POST['city'] ?? ''), 'Manual',
                ]
            );
            flash('success', 'Lead added successfully.');
            header('Location: ' . APP_URL . '/admin/import_leads.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

$segStats = Database::fetchAll("SELECT segment, COUNT(*) AS cnt FROM leads GROUP BY segment ORDER BY cnt DESC");
$totalLeads = Database::fetchOne("SELECT COUNT(*) AS c FROM leads")['c'] ?? 0;
?>

<h2 style="font-size:20px;margin-bottom:20px">📥 Import Leads</h2>

<?php if ($errors): ?>
<div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:16px;color:#ef4444;margin-bottom:16px">
    <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
</div>
<?php endif; ?>

<div class="grid-2">
    <div class="gc">
        <div class="gc-title">📁 CSV Import</div>
        <div class="gc-sub">Upload a CSV file with lead data</div>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group" style="margin-bottom:16px">
                <label style="display:block;font-size:13px;color:#8a9ab5;margin-bottom:6px">Select CSV File</label>
                <input type="file" name="csv_file" accept=".csv" class="fi" style="padding:10px">
            </div>
            <button type="submit" class="btn-launch">📤 Import CSV</button>
            <a href="<?php echo APP_URL; ?>/api/download_sample_csv.php" class="btn-sec" style="text-decoration:none;margin-left:8px">⬇️ Sample CSV</a>
        </form>
        <div style="margin-top:20px;padding:16px;background:#0d1f38;border-radius:8px;font-size:12px;color:#8a9ab5">
            <strong style="color:#e2e8f0">Required CSV columns:</strong><br>
            first_name, last_name, email, company, job_title, role, segment, province, city, country, source
        </div>
    </div>

    <div class="gc">
        <div class="gc-title">✏️ Add Single Lead</div>
        <div class="gc-sub">Manually add a lead to the database</div>
        <form method="POST">
            <div class="grid-2eq" style="gap:12px;margin-bottom:12px">
                <input class="fi" name="first_name" placeholder="First Name" required>
                <input class="fi" name="last_name"  placeholder="Last Name" required>
            </div>
            <input class="fi" name="manual_email" placeholder="Email Address *" style="width:100%;margin-bottom:12px" required>
            <input class="fi" name="company"   placeholder="Company"   style="width:100%;margin-bottom:12px">
            <input class="fi" name="job_title" placeholder="Job Title" style="width:100%;margin-bottom:12px">
            <input class="fi" name="role"      placeholder="Role"      style="width:100%;margin-bottom:12px">
            <select class="fi" name="segment" style="width:100%;margin-bottom:12px">
                <option value="Other">Other</option>
                <option value="Financial Institutions">Financial Institutions</option>
                <option value="Technology & Solution Providers">Technology &amp; Solution Providers</option>
                <option value="Venture Capital / Investors">Venture Capital / Investors</option>
                <option value="FinTech Startups">FinTech Startups</option>
            </select>
            <div class="grid-2eq" style="gap:12px;margin-bottom:12px">
                <input class="fi" name="province" placeholder="Province">
                <input class="fi" name="city"     placeholder="City">
            </div>
            <button type="submit" class="btn-launch">➕ Add Lead</button>
        </form>
    </div>
</div>

<div class="gc" style="margin-top:20px">
    <div class="gc-title">📊 Segment Distribution (<?php echo number_format($totalLeads); ?> Total Leads)</div>
    <div class="tbl-wrap">
        <table class="dt">
            <thead><tr><th>Segment</th><th>Count</th><th>%</th></tr></thead>
            <tbody>
            <?php foreach ($segStats as $s): ?>
            <tr>
                <td><?php echo pill($s['segment']); ?></td>
                <td><?php echo $s['cnt']; ?></td>
                <td><?php echo $totalLeads > 0 ? round($s['cnt']/$totalLeads*100,1) : 0; ?>%</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="gc" style="margin-top:20px">
    <div class="gc-title">🔧 Apollo.io Integration Guide</div>
    <div class="gc-sub">Steps to export leads from Apollo and import here</div>
    <div style="padding:16px;background:#0d1f38;border-radius:8px;font-size:13px;line-height:1.8;color:#8a9ab5">
        <ol style="padding-left:20px">
            <li>Log in to your Apollo.io account</li>
            <li>Navigate to <strong style="color:#e2e8f0">People Search</strong> and apply filters (Location: Canada, Industry: Financial Services / Technology)</li>
            <li>Select contacts and click <strong style="color:#e2e8f0">Export → CSV</strong></li>
            <li>Map fields: First Name, Last Name, Email, Company, Title → Job Title, Seniority → Role</li>
            <li>Add a <em>segment</em> column manually based on industry before importing</li>
            <li>Upload the CSV using the form above</li>
        </ol>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
