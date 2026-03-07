<?php
/**
 * install/steps/requirements.php
 * Step 1 — Welcome & Requirements Check
 */
require_once dirname(__DIR__) . '/functions.php';

$requirements = checkRequirements();
$allCriticalPassed = true;
foreach ($requirements as $req) {
    if ($req['critical'] && !$req['passed']) {
        $allCriticalPassed = false;
        break;
    }
}
?>
<div class="card-header">
    <h2>🔍 Requirements Check</h2>
    <p>Verifying your server meets all the necessary requirements to run this application.</p>
</div>
<div class="card-body">
    <?php if (!$allCriticalPassed): ?>
    <div class="alert alert-error">
        <span class="alert-icon">❌</span>
        <div>Some <strong>critical requirements</strong> are not met. Please resolve the issues below before continuing.</div>
    </div>
    <?php else: ?>
    <div class="alert alert-success">
        <span class="alert-icon">✅</span>
        <div>All critical requirements are satisfied. You are ready to proceed!</div>
    </div>
    <?php endif; ?>

    <ul class="req-list">
    <?php foreach ($requirements as $req): ?>
        <?php
        $icon  = $req['passed'] ? '✅' : ($req['critical'] ? '❌' : '⚠️');
        $style = $req['passed'] ? '' : ($req['critical'] ? 'color:var(--error)' : 'color:var(--warning)');
        ?>
        <li>
            <span class="req-icon"><?php echo $icon; ?></span>
            <span class="req-label">
                <strong style="<?php echo $style; ?>"><?php echo htmlspecialchars($req['label']); ?></strong>
                <?php if (!empty($req['info'])): ?>
                <span class="req-info"><?php echo htmlspecialchars($req['info']); ?></span>
                <?php endif; ?>
                <?php if (!$req['passed'] && !empty($req['fix'])): ?>
                <span class="req-fix">Fix: <?php echo htmlspecialchars($req['fix']); ?></span>
                <?php endif; ?>
            </span>
        </li>
    <?php endforeach; ?>
    </ul>
</div>
<div class="card-footer">
    <span></span>
    <button type="submit" name="step_action" value="next" class="btn btn-primary"
        <?php echo !$allCriticalPassed ? 'disabled' : ''; ?>>
        Continue →
    </button>
</div>
