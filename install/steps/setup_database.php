<?php
/**
 * install/steps/setup_database.php
 * Step 3 — Create Database Tables
 */
$dbResult  = $_SESSION['install_db_result']  ?? null;
$tableList = $_SESSION['install_table_list'] ?? null;
$dbError   = $_SESSION['install_db_error']   ?? null;
unset($_SESSION['install_db_result'], $_SESSION['install_table_list'], $_SESSION['install_db_error']);

// Core tables for display purposes
$expectedTables = [
    'users', 'leads', 'campaigns', 'email_logs', 'responses',
    'templates', 'site_settings', 'lead_collections',
    'lead_collection_history', 'audit_logs', 'tags', 'lead_tags',
];
?>
<div class="card-header">
    <h2>⚙️ Database Setup</h2>
    <p>Creating all required database tables, indexes, and default settings.</p>
</div>
<div class="card-body">

<?php if ($dbError): ?>
    <div class="alert alert-error">
        <span class="alert-icon">❌</span>
        <div><strong>Database setup failed:</strong><br><?php echo htmlspecialchars($dbError); ?></div>
    </div>
    <p style="color:var(--muted);font-size:13px;margin-bottom:20px">
        Please go back, verify your credentials, and try again.
    </p>

<?php elseif ($dbResult && $dbResult['success']): ?>
    <div class="alert alert-success">
        <span class="alert-icon">✅</span>
        <div><strong>Database setup complete!</strong> All tables created successfully.</div>
    </div>
    <ul class="table-progress">
    <?php foreach ($expectedTables as $table): ?>
        <li>
            <span class="tp-icon">✅</span>
            <span class="tp-label"><?php echo htmlspecialchars($table); ?></span>
        </li>
    <?php endforeach; ?>
    </ul>

<?php else: ?>
    <div class="alert alert-info">
        <span class="alert-icon">ℹ️</span>
        <div>Click <strong>Run Setup</strong> to create the following tables in your database.</div>
    </div>
    <ul class="table-progress">
    <?php foreach ($expectedTables as $table): ?>
        <li>
            <span class="tp-icon">⬜</span>
            <span class="tp-label"><?php echo htmlspecialchars($table); ?></span>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>

</div>
<div class="card-footer">
    <button type="submit" name="step_action" value="prev" class="btn btn-secondary">← Back</button>
    <?php if ($dbResult && $dbResult['success']): ?>
        <button type="submit" name="step_action" value="next" class="btn btn-primary">Continue →</button>
    <?php elseif ($dbError): ?>
        <button type="submit" name="step_action" value="prev" class="btn btn-danger">← Fix Credentials</button>
    <?php else: ?>
        <button type="submit" name="step_action" value="next" class="btn btn-primary">▶ Run Setup</button>
    <?php endif; ?>
</div>
