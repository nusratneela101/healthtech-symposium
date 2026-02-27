<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $email) {
    Database::query("UPDATE leads SET status='unsubscribed' WHERE email=?", [$email]);
    $message = 'You have been unsubscribed successfully.';
} elseif ($email) {
    $message = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unsubscribe — <?php echo APP_NAME; ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',Arial,sans-serif;background:#0a1628;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#13213a;border:1px solid #1e3355;border-radius:16px;padding:48px 40px;max-width:480px;width:100%;text-align:center}
h1{font-size:28px;margin-bottom:8px}
p{color:#8a9ab5;margin-bottom:24px;font-size:14px}
.success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);border-radius:8px;padding:16px;color:#34d399;margin-bottom:16px}
input{width:100%;background:#0d1f38;border:1px solid #1e3355;border-radius:8px;padding:12px 16px;color:#e2e8f0;font-size:14px;outline:none;margin-bottom:16px}
button{background:#ef4444;color:#fff;border:none;border-radius:8px;padding:12px 32px;font-size:14px;cursor:pointer;width:100%}
button:hover{background:#dc2626}
</style>
</head>
<body>
<div class="card">
    <div style="font-size:48px;margin-bottom:16px">📧</div>
    <h1>Unsubscribe</h1>
    <p>We're sorry to see you go. Enter your email address to unsubscribe from our communications.</p>
    <?php if ($message): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (!$message): ?>
    <form method="POST">
        <input type="email" name="email" placeholder="your@email.com"
               value="<?php echo htmlspecialchars($email); ?>" required>
        <button type="submit">Unsubscribe Me</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
