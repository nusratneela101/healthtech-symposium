<?php
/**
 * Installation Verification Script
 *
 * Run this file via a browser or CLI to verify that the server meets all
 * requirements before (or after) installing the HealthTech Symposium app.
 *
 * SECURITY WARNING: Delete this file once the installation is confirmed!
 */

// -----------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------

function check_ok(string $label, string $detail = ''): void {
    echo '<tr><td>' . htmlspecialchars($label) . '</td>'
       . '<td class="ok">&#10003; OK</td>'
       . '<td>' . htmlspecialchars($detail) . '</td></tr>' . "\n";
}

function check_warn(string $label, string $detail = ''): void {
    global $warnings;
    $warnings++;
    echo '<tr><td>' . htmlspecialchars($label) . '</td>'
       . '<td class="warn">&#9888; Warning</td>'
       . '<td>' . htmlspecialchars($detail) . '</td></tr>' . "\n";
}

function check_error(string $label, string $detail = ''): void {
    global $errors;
    $errors++;
    echo '<tr><td>' . htmlspecialchars($label) . '</td>'
       . '<td class="err">&#10007; Error</td>'
       . '<td>' . htmlspecialchars($detail) . '</td></tr>' . "\n";
}

function section(string $title): void {
    echo '<tr class="section"><td colspan="3"><strong>' . htmlspecialchars($title) . '</strong></td></tr>' . "\n";
}

// -----------------------------------------------------------------
// Counters
// -----------------------------------------------------------------
$errors   = 0;
$warnings = 0;

// -----------------------------------------------------------------
// HTML Header
// -----------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation Check — HealthTech Symposium</title>
<style>
  body { font-family: sans-serif; background:#f4f6f8; margin:0; padding:2rem; color:#333; }
  h1   { color:#1a73e8; }
  .box { background:#fff; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.1); overflow:hidden; }
  table{ width:100%; border-collapse:collapse; }
  th   { background:#1a73e8; color:#fff; text-align:left; padding:10px 14px; }
  td   { padding:9px 14px; border-bottom:1px solid #eee; vertical-align:top; }
  tr.section td { background:#f0f4ff; font-weight:600; padding:8px 14px; }
  .ok  { color:#2e7d32; font-weight:600; white-space:nowrap; }
  .warn{ color:#e65100; font-weight:600; white-space:nowrap; }
  .err { color:#c62828; font-weight:600; white-space:nowrap; }
  .summary { margin-top:1.5rem; padding:1rem 1.5rem; border-radius:8px; }
  .summary.pass { background:#e8f5e9; border:1px solid #a5d6a7; color:#1b5e20; }
  .summary.warn { background:#fff3e0; border:1px solid #ffcc80; color:#e65100; }
  .summary.fail { background:#ffebee; border:1px solid #ef9a9a; color:#b71c1c; }
  .btn { display:inline-block; margin-top:1rem; padding:.6rem 1.4rem; background:#1a73e8;
         color:#fff; border-radius:5px; text-decoration:none; font-weight:600; }
  .security { margin-top:1rem; padding:1rem; background:#fff8e1; border:1px solid #ffe082;
              border-radius:6px; color:#5d4037; font-size:.9rem; }
</style>
</head>
<body>
<h1>&#128295; HealthTech Symposium — Installation Check</h1>
<div class="box">
<table>
<thead><tr><th style="width:40%">Check</th><th style="width:12%">Status</th><th>Details</th></tr></thead>
<tbody>
<?php

// -----------------------------------------------------------------
// 1. PHP Version
// -----------------------------------------------------------------
section('PHP Environment');

$phpVer = PHP_VERSION;
if (version_compare($phpVer, '7.4.0', '>=')) {
    check_ok('PHP Version', $phpVer);
} else {
    check_error('PHP Version', 'Found ' . $phpVer . ' — PHP 7.4 or higher is required.');
}

// Memory limit
// Memory limit — normalise to bytes regardless of suffix case
$memLimit = ini_get('memory_limit');
$memUpper = strtoupper($memLimit);
$memBytes = (int)$memUpper;
if (substr($memUpper, -1) === 'M') $memBytes = (int)$memUpper * 1024 * 1024;
if (substr($memUpper, -1) === 'G') $memBytes = (int)$memUpper * 1024 * 1024 * 1024;
if (substr($memUpper, -1) === 'K') $memBytes = (int)$memUpper * 1024;
if ($memBytes >= 128 * 1024 * 1024 || $memLimit === '-1') {
    check_ok('Memory Limit', $memLimit);
} else {
    check_warn('Memory Limit', 'Found ' . $memLimit . ' — 128M or higher is recommended.');
}

// -----------------------------------------------------------------
// 2. Required Extensions
// -----------------------------------------------------------------
section('Required PHP Extensions');

$required = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'openssl'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        check_ok('ext/' . $ext);
    } else {
        check_error('ext/' . $ext, 'Extension not loaded — required for core functionality.');
    }
}

// -----------------------------------------------------------------
// 3. Optional Extensions
// -----------------------------------------------------------------
section('Optional PHP Extensions');

$optional = [
    'imap' => 'Required for IMAP inbox polling.',
];
foreach ($optional as $ext => $hint) {
    if (extension_loaded($ext)) {
        check_ok('ext/' . $ext);
    } else {
        check_warn('ext/' . $ext, 'Not loaded — ' . $hint);
    }
}

// -----------------------------------------------------------------
// 4. Directory Permissions
// -----------------------------------------------------------------
section('Directory Permissions');

$base = __DIR__;
$dirs = [
    $base,
    $base . '/config',
    $base . '/includes',
    $base . '/api',
    $base . '/admin',
];

foreach ($dirs as $dir) {
    $label = str_replace($base, '.', $dir);
    if (!is_dir($dir)) {
        check_error($label, 'Directory does not exist.');
        continue;
    }
    $perms = substr(sprintf('%o', fileperms($dir)), -4);
    if (is_readable($dir)) {
        check_ok($label, 'Permissions: ' . $perms);
    } else {
        check_error($label, 'Directory not readable (permissions: ' . $perms . ').');
    }
}

// -----------------------------------------------------------------
// 5. Required Files
// -----------------------------------------------------------------
section('Required Files');

$files = [
    '/config/config.php',
    '/config/database.php',
    '/includes/functions.php',
    '/includes/auth.php',
    '/includes/rate_limiter.php',
    '/includes/csrf.php',
    '/includes/email.php',
    '/includes/env_loader.php',
    '/.env',
];

foreach ($files as $rel) {
    $full  = $base . $rel;
    $label = $rel;
    if (file_exists($full)) {
        check_ok($label);
    } else {
        check_error($label, 'File not found.');
    }
}

?>
</tbody>
</table>
</div>

<?php
// -----------------------------------------------------------------
// Summary
// -----------------------------------------------------------------
if ($errors === 0 && $warnings === 0) {
    $cls = 'pass';
    $msg = '&#10003; All checks passed! The server is ready for installation.';
} elseif ($errors === 0) {
    $cls = 'warn';
    $msg = '&#9888; ' . $warnings . ' warning(s) found. The application may work but review the warnings above.';
} else {
    $cls = 'fail';
    $msg = '&#10007; ' . $errors . ' error(s) and ' . $warnings . ' warning(s) found. Please resolve errors before proceeding.';
}
?>

<div class="summary <?php echo $cls; ?>">
    <strong>Summary:</strong> <?php echo $msg; ?>
    <?php if ($errors === 0): ?>
    <br><a class="btn" href="install/auto_installer.php">&#128640; Proceed to Auto-Installer</a>
    <?php endif; ?>
</div>

<div class="security">
    &#128274; <strong>Security Warning:</strong>
    Delete or restrict access to <code>check_installation.php</code> once your installation is verified.
    Leaving it publicly accessible can expose server configuration details.
</div>

</body>
</html>
