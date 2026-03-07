<?php
/**
 * install/index.php
 * Main entry point for the installation wizard.
 *
 * Handles step routing, session management, AJAX calls, and form processing.
 */

// ─── Bootstrap ────────────────────────────────────────────────────────────────
define('INSTALLER_SESSION', 'cfts_installer');
session_name(INSTALLER_SESSION);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID periodically for security
if (empty($_SESSION['__token'])) {
    session_regenerate_id(true);
    $_SESSION['__token'] = bin2hex(random_bytes(16));
}

require_once __DIR__ . '/functions.php';

// ─── Check if already installed ───────────────────────────────────────────────
if (isInstallationLocked() && ($_GET['force'] ?? '') !== '1') {
    renderLockedPage();
    exit;
}

// ─── Constants ────────────────────────────────────────────────────────────────
define('TOTAL_STEPS', 8);
$stepTitles = [
    1 => 'Welcome',
    2 => 'Database',
    3 => 'DB Setup',
    4 => 'Admin',
    5 => 'Email',
    6 => 'n8n',
    7 => 'Apollo',
    8 => 'Finish',
];

// ─── AJAX handler ────────────────────────────────────────────────────────────
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'test_db') {
        $result = testDatabaseConnection(
            trim($_POST['db_host'] ?? 'localhost'),
            trim($_POST['db_name'] ?? ''),
            trim($_POST['db_user'] ?? ''),
            trim($_POST['db_pass'] ?? ''),
            (int)($_POST['db_port'] ?? 3306)
        );
        echo json_encode($result);
        exit;
    }

    if ($action === 'test_email') {
        $result = testEmailConnection([
            'email_provider'  => $_POST['email_provider']  ?? '',
            'brevo_api_key'   => $_POST['brevo_api_key']   ?? '',
            'smtp_host'       => $_POST['smtp_host']        ?? '',
            'smtp_port'       => $_POST['smtp_port']        ?? 587,
            'smtp_secure'     => $_POST['smtp_secure']      ?? 'tls',
            'smtp_user'       => $_POST['smtp_user']        ?? '',
            'smtp_pass'       => $_POST['smtp_pass']        ?? '',
            'ms_client_id'    => $_POST['ms_client_id']     ?? '',
            'ms_client_secret'=> $_POST['ms_client_secret'] ?? '',
            'ms_tenant_id'    => $_POST['ms_tenant_id']     ?? 'common',
        ]);
        echo json_encode($result);
        exit;
    }

    if ($action === 'test_n8n') {
        $result = testN8nConnection(
            trim($_POST['n8n_url']     ?? ''),
            trim($_POST['n8n_api_key'] ?? '')
        );
        echo json_encode($result);
        exit;
    }

    if ($action === 'test_apollo') {
        $result = testApolloConnection(trim($_POST['apollo_api_key'] ?? ''));
        echo json_encode($result);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit;
}

// ─── Current step ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['install_step'])) {
    $_SESSION['install_step'] = 1;
    $_SESSION['install_data'] = [];
}

$currentStep = (int)$_SESSION['install_step'];

// ─── Handle form submission ───────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !isset($_POST['action'])) {
    $stepAction = $_POST['step_action'] ?? 'next';

    if ($stepAction === 'prev') {
        $_SESSION['install_step'] = max(1, $currentStep - 1);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Merge current POST data (excluding step_action) into session
    $postData = $_POST;
    unset($postData['step_action']);
    foreach ($postData as $key => $value) {
        $_SESSION['install_data'][$key] = trim((string)$value);
    }

    if ($stepAction === 'skip') {
        // Optional steps can be skipped
        $_SESSION['install_step'] = min(TOTAL_STEPS, $currentStep + 1);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($stepAction === 'finish') {
        handleFinish();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // 'next' — validate the current step
    $errors = validateStep($currentStep, $_SESSION['install_data']);
    if (empty($errors)) {
        $processed = processStep($currentStep, $_SESSION['install_data']);
        if ($processed === true) {
            $_SESSION['install_step'] = min(TOTAL_STEPS, $currentStep + 1);
        }
        // Else: processStep already wrote to $_SESSION['install_errors'] etc.
    } else {
        $_SESSION['install_errors'] = $errors;
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$currentStep = (int)$_SESSION['install_step'];

// ─── Step validators ──────────────────────────────────────────────────────────
function validateStep(int $step, array $data): array
{
    $errors = [];
    switch ($step) {
        case 1: // Requirements — no form fields to validate
            break;

        case 2: // Database config
            if (empty($data['db_host'])) $errors['db_host'] = 'Database host is required.';
            if (empty($data['db_name'])) $errors['db_name'] = 'Database name is required.';
            if (empty($data['db_user'])) $errors['db_user'] = 'Database username is required.';
            $port = (int)($data['db_port'] ?? 3306);
            if ($port < 1 || $port > 65535) $errors['db_port'] = 'Port must be between 1 and 65535.';
            break;

        case 4: // Admin user
            if (empty($data['admin_name'])) {
                $errors['admin_name'] = 'Full name is required.';
            }
            $username = $data['username'] ?? '';
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                $errors['username'] = 'Username must be 3–20 alphanumeric characters (or underscore).';
            }
            if (empty($data['admin_email']) || !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $errors['admin_email'] = 'A valid email address is required.';
            }
            $password = $data['password'] ?? '';
            if (strlen($password) < 8) {
                $errors['password'] = 'Password must be at least 8 characters.';
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $errors['password'] = 'Password must contain at least one uppercase letter.';
            } elseif (!preg_match('/[a-z]/', $password)) {
                $errors['password'] = 'Password must contain at least one lowercase letter.';
            } elseif (!preg_match('/[0-9]/', $password)) {
                $errors['password'] = 'Password must contain at least one number.';
            } elseif (!preg_match('/[\W_]/', $password)) {
                $errors['password'] = 'Password must contain at least one special character.';
            }
            if (($data['confirm_password'] ?? '') !== $password) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }
            break;
    }
    return $errors;
}

// ─── Step processors ─────────────────────────────────────────────────────────
function processStep(int $step, array $data): bool
{
    switch ($step) {
        case 1:
            return true; // Requirements checked on render

        case 2:
            // Test & optionally create the database
            $conn = testDatabaseConnection(
                $data['db_host'] ?? 'localhost',
                $data['db_name'] ?? '',
                $data['db_user'] ?? '',
                $data['db_pass'] ?? '',
                (int)($data['db_port'] ?? 3306)
            );
            if (!$conn['success']) {
                $_SESSION['install_errors'] = ['db_general' => $conn['error']];
                return false;
            }
            if (!$conn['db_exists']) {
                $create = createDatabase(
                    $data['db_host'] ?? 'localhost',
                    $data['db_name'] ?? '',
                    $data['db_user'] ?? '',
                    $data['db_pass'] ?? '',
                    (int)($data['db_port'] ?? 3306)
                );
                if (!$create['success']) {
                    $_SESSION['install_errors'] = ['db_general' => 'Could not create database: ' . $create['error']];
                    return false;
                }
            }
            return true;

        case 3:
            // Create tables
            $result = createTables(
                $data['db_host'] ?? 'localhost',
                $data['db_name'] ?? '',
                $data['db_user'] ?? '',
                $data['db_pass'] ?? '',
                (int)($data['db_port'] ?? 3306)
            );
            $_SESSION['install_db_result'] = $result;
            if (!$result['success']) {
                $_SESSION['install_db_error'] = $result['error'] ?? 'Unknown error';
                return false;
            }
            return true;

        case 4:
            // Create admin user
            $result = createAdminUser(
                $data['db_host']  ?? 'localhost',
                $data['db_name']  ?? '',
                $data['db_user']  ?? '',
                $data['db_pass']  ?? '',
                (int)($data['db_port'] ?? 3306),
                $data['admin_name']  ?? '',
                $data['username']    ?? '',
                $data['admin_email'] ?? '',
                $data['password']    ?? ''
            );
            if (!$result['success']) {
                $_SESSION['install_errors'] = ['admin_general' => $result['error']];
                return false;
            }
            return true;

        case 5: // Email — optional, just save to session
        case 6: // n8n  — optional
        case 7: // Apollo — optional
            return true;

        default:
            return true;
    }
}

// ─── Finalization handler ─────────────────────────────────────────────────────
function handleFinish(): void
{
    $data = $_SESSION['install_data'] ?? [];

    // 1. Generate .env
    $envResult = generateEnvFile($data);
    if (!$envResult['success']) {
        $_SESSION['install_env_error'] = $envResult['error'];
        return;
    }

    // 2. Optionally save n8n / Apollo settings to DB
    saveIntegrationSettings($data);

    // 3. Lock the installer
    lockInstallation();

    // 4. Mark success
    $_SESSION['install_success'] = true;
}

function saveIntegrationSettings(array $data): void
{
    // Only if we have DB credentials
    if (empty($data['db_host']) || empty($data['db_name'])) return;

    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;port=%d;charset=utf8mb4',
            $data['db_host'], $data['db_name'], (int)($data['db_port'] ?? 3306)
        );
        $pdo = new PDO($dsn, $data['db_user'] ?? '', $data['db_pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $settingsToSave = [
            'site_url' => ['value' => $data['app_url'] ?? '', 'group' => 'general'],
        ];

        if (!empty($data['n8n_url'])) {
            $settingsToSave['n8n_url']         = ['value' => $data['n8n_url'],         'group' => 'integrations'];
            $settingsToSave['n8n_api_key']      = ['value' => $data['n8n_api_key'] ?? '', 'group' => 'integrations'];
            $settingsToSave['n8n_webhook_url']  = ['value' => $data['n8n_webhook_url'] ?? '', 'group' => 'integrations'];
        }

        if (!empty($data['apollo_api_key'])) {
            $settingsToSave['apollo_api_key'] = ['value' => $data['apollo_api_key'], 'group' => 'integrations'];
        }

        if (!empty($data['brevo_api_key'])) {
            $settingsToSave['brevo_api_key'] = ['value' => $data['brevo_api_key'], 'group' => 'email'];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value, setting_group)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)"
        );
        foreach ($settingsToSave as $key => $entry) {
            $stmt->execute([$key, $entry['value'], $entry['group']]);
        }
    } catch (Exception $e) {
        // Best-effort; don't fail installation over this
    }
}

// ─── Render helpers ───────────────────────────────────────────────────────────
function renderLockedPage(): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Already Installed — Canada Fintech Symposium</title>
<link rel="stylesheet" href="installer.css">
</head>
<body>
<div class="installer-wrapper">
    <div class="installer-header">
        <?php renderLogo(); ?>
        <h1>Installation Wizard</h1>
    </div>
    <div class="installer-card">
        <div class="card-body locked-wrap">
            <div class="lock-icon">🔒</div>
            <h2>Already Installed</h2>
            <p>This application has already been installed. The installer is locked to prevent re-installation.</p>
            <a href="../login.php" class="btn btn-primary btn-lg">Go to Login →</a>
        </div>
    </div>
</div>
</body>
</html>
    <?php
}

function renderLogo(): void
{
    ?>
<div class="installer-logo">
    <svg class="installer-logo-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <path d="M50 5 L58 30 L75 20 L65 38 L85 35 L70 50 L80 70 L60 60 L55 85 L50 75 L45 85 L40 60 L20 70 L30 50 L15 35 L35 38 L25 20 L42 30 Z" fill="#CC0000"/>
        <text x="50" y="58" text-anchor="middle" fill="white" font-size="16" font-weight="900" font-family="Arial">CFTS</text>
    </svg>
    <div class="installer-logo-text">
        <div class="brand-canada">CANADA</div>
        <div class="brand-fintech">FINTECH</div>
        <div class="brand-sym">SYMPOSIUM</div>
    </div>
</div>
    <?php
}

function renderProgress(int $currentStep): void
{
    global $stepTitles;
    $pct = round((($currentStep - 1) / (TOTAL_STEPS - 1)) * 100);
    ?>
<div class="progress-wrap">
    <div class="progress-steps">
        <?php
        // Progress line fill width: distribute evenly between dots
        $fillPct = $currentStep > 1
            ? round((($currentStep - 1) / (TOTAL_STEPS - 1)) * 100)
            : 0;
        echo '<div class="progress-fill" style="width:' . $fillPct . '%"></div>';

        for ($i = 1; $i <= TOTAL_STEPS; $i++):
            $class = $i < $currentStep ? 'done' : ($i === $currentStep ? 'active' : '');
            $label = $stepTitles[$i] ?? $i;
            $icon  = $i < $currentStep ? '✓' : $i;
        ?>
        <div class="step-dot <?php echo $class; ?>">
            <div class="step-dot-circle"><?php echo $icon; ?></div>
            <div class="step-dot-label"><?php echo htmlspecialchars((string)$label); ?></div>
        </div>
        <?php endfor; ?>
    </div>
    <div class="progress-bar-outer">
        <div class="progress-bar-inner" style="width:<?php echo $pct; ?>%"></div>
    </div>
    <div class="progress-info">Step <?php echo $currentStep; ?> of <?php echo TOTAL_STEPS; ?></div>
</div>
    <?php
}

// ─── Main render ──────────────────────────────────────────────────────────────
$stepFile = __DIR__ . '/steps/' . [
    1 => 'requirements.php',
    2 => 'database.php',
    3 => 'setup_database.php',
    4 => 'admin.php',
    5 => 'email.php',
    6 => 'n8n.php',
    7 => 'apollo.php',
    8 => 'finish.php',
][$currentStep] ?? 'requirements.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installation Wizard — Canada Fintech Symposium</title>
<link rel="stylesheet" href="installer.css">
</head>
<body>

<div class="installer-wrapper">

    <!-- Header -->
    <div class="installer-header">
        <?php renderLogo(); ?>
        <h1>Installation Wizard</h1>
        <p>Set up your Canada Fintech Symposium platform in a few easy steps.</p>
    </div>

    <!-- Progress -->
    <?php renderProgress($currentStep); ?>

    <!-- Step content -->
    <div class="installer-card">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>

            <?php if (file_exists($stepFile)): ?>
                <?php include $stepFile; ?>
            <?php else: ?>
                <div class="card-body">
                    <div class="alert alert-error"><span class="alert-icon">❌</span>Step file not found.</div>
                </div>
            <?php endif; ?>

        </form>
    </div>

</div><!-- /.installer-wrapper -->

<script>
// Make the AJAX URL available to installer.js
window.installerAjaxUrl = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>';
</script>
<script src="installer.js"></script>
</body>
</html>
