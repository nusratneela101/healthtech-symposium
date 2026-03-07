<?php
/**
 * Auto-run database migrations.
 * Reads SQL files from the migrations directory and executes them.
 * Restricted to SuperAdmin users only.
 *
 * Note: All migration SQL files should use CREATE TABLE IF NOT EXISTS and
 * similar idempotent statements, making it safe to re-run this script.
 * SQL statements must not contain semicolons inside string literals.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

Auth::requireSuperAdmin();

$migrationsDir = __DIR__ . '/migrations';
$results = [];
$ok = 0;
$fail = 0;

if (!is_dir($migrationsDir)) {
    echo json_encode(['success' => false, 'error' => 'Migrations directory not found']);
    exit;
}

$files = glob($migrationsDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    $sql  = file_get_contents($file);

    // Split on semicolons to run each statement separately.
    // SQL files must not contain semicolons inside string literals or procedure bodies.
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $fileOk = true;
    $fileError = null;
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        try {
            Database::query($statement);
        } catch (Exception $e) {
            $fileOk = false;
            $fileError = $e->getMessage();
            break;
        }
    }

    if ($fileOk) {
        $results[] = ['file' => $name, 'status' => 'ok'];
        $ok++;
    } else {
        $results[] = ['file' => $name, 'status' => 'fail', 'error' => $fileError];
        $fail++;
    }
}

echo json_encode([
    'success' => $fail === 0,
    'ok'      => $ok,
    'failed'  => $fail,
    'results' => $results,
]);
