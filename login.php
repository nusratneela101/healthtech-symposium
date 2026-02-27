<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($email && $password) {
        $user = Database::fetchOne("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];
            Database::query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            header('Location: ' . APP_URL . '/admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?php echo APP_NAME; ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--navy:#0a1628;--blue:#0d6efd;--text:#e2e8f0;--muted:#8a9ab5;--card:#13213a;--border:#1e3355;--error:#ef4444}
body{font-family:'Segoe UI',Arial,sans-serif;background:var(--navy);color:var(--text);height:100vh;display:flex;overflow:hidden}
.bg-grid{position:fixed;inset:0;background-image:linear-gradient(rgba(13,110,253,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(13,110,253,.05) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;z-index:0}
.orb{position:fixed;border-radius:50%;filter:blur(80px);pointer-events:none;z-index:0}
.o1{width:400px;height:400px;background:rgba(13,110,253,.2);top:-100px;right:10%}
.o2{width:300px;height:300px;background:rgba(139,92,246,.15);bottom:-50px;left:5%}
.o3{width:200px;height:200px;background:rgba(16,185,129,.1);top:40%;left:30%}
.particle{position:fixed;width:4px;height:4px;background:rgba(13,110,253,.6);border-radius:50%;pointer-events:none;z-index:0;animation:floatParticle var(--dur,8s) ease-in-out infinite var(--delay,0s)}
@keyframes floatParticle{0%,100%{transform:translate(0,0);opacity:.3}50%{transform:translate(var(--tx,20px),var(--ty,-40px));opacity:.8}}
.left{flex:1;background:linear-gradient(135deg,var(--navy) 0%,#0d2347 50%,#102d5a 100%);display:flex;flex-direction:column;justify-content:center;align-items:center;padding:60px 40px;position:relative;z-index:1}
.logo{font-size:64px;margin-bottom:24px;animation:pulse 2s ease-in-out infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
.left h1{font-size:28px;font-weight:800;text-align:center;line-height:1.3;margin-bottom:12px;background:linear-gradient(135deg,#fff,#93c5fd);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.left p{color:var(--muted);text-align:center;font-size:14px;margin-bottom:40px}
.stats{display:flex;gap:32px;margin-bottom:40px}
.stat{text-align:center}.stat-val{font-size:28px;font-weight:800;color:var(--blue)}.stat-lbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.event-badge{background:rgba(13,110,253,.15);border:1px solid rgba(13,110,253,.3);border-radius:20px;padding:10px 24px;font-size:13px;color:#93c5fd;margin-bottom:24px}
.trust{display:flex;gap:16px;flex-wrap:wrap;justify-content:center}
.trust-item{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;padding:8px 16px;font-size:12px;color:var(--muted)}
.right{width:460px;display:flex;align-items:center;justify-content:center;position:relative;z-index:1;padding:40px}
.login-card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:40px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.login-card h2{font-size:22px;font-weight:700;margin-bottom:6px}
.login-card p{color:var(--muted);font-size:13px;margin-bottom:28px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:13px;color:var(--muted);margin-bottom:6px;font-weight:500}
.form-group input{width:100%;background:#0d1f38;border:1px solid var(--border);border-radius:10px;padding:12px 16px;color:var(--text);font-size:14px;outline:none;transition:.2s}
.form-group input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(13,110,253,.15)}
.pw-wrap{position:relative}
.pw-wrap input{padding-right:48px}
.pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px}
.btn-login{width:100%;background:var(--blue);color:#fff;border:none;border-radius:10px;padding:14px;font-size:15px;font-weight:600;cursor:pointer;transition:.2s;position:relative}
.btn-login:hover{background:#0b5ed7;transform:translateY(-1px)}
.btn-login:disabled{opacity:.6;cursor:not-allowed;transform:none}
.spin{display:none;width:18px;height:18px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:sp .6s linear infinite;margin:0 auto}
@keyframes sp{to{transform:rotate(360deg)}}
.error-box{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:12px 16px;color:var(--error);font-size:13px;margin-bottom:18px}
.quick-fill{width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;padding:10px;font-size:12px;color:var(--muted);cursor:pointer;margin-bottom:16px;transition:.2s}
.quick-fill:hover{background:rgba(13,110,253,.1);color:#93c5fd}
@media(max-width:900px){.left{display:none}.right{width:100%;padding:20px}}
</style>
</head>
<body>
<div class="bg-grid"></div>
<div class="orb o1"></div>
<div class="orb o2"></div>
<div class="orb o3"></div>
<?php for($i=0;$i<8;$i++): $dur=rand(6,12);$delay=rand(0,5);$tx=rand(-50,50);$ty=rand(-80,-20);?>
<div class="particle" style="--dur:<?php echo $dur;?>s;--delay:-<?php echo $delay;?>s;--tx:<?php echo $tx;?>px;--ty:<?php echo $ty;?>px;left:<?php echo rand(5,95);?>%;top:<?php echo rand(5,95);?>%"></div>
<?php endfor;?>

<div class="left">
    <div class="logo">🏥</div>
    <h1>Welcome to the Future of HealthTech</h1>
    <p>Canada HealthTech Innovation Symposium 2026</p>
    <div class="event-badge">📅 April 21–22, 2026 — Toronto, Canada</div>
    <div class="stats">
        <div class="stat"><div class="stat-val" id="c-leads">—</div><div class="stat-lbl">Total Leads</div></div>
        <div class="stat"><div class="stat-val" id="c-sent">—</div><div class="stat-lbl">Emails Sent</div></div>
        <div class="stat"><div class="stat-val" id="c-resp">—</div><div class="stat-lbl">Responses</div></div>
    </div>
    <div class="trust">
        <div class="trust-item">🔒 Secure Platform</div>
        <div class="trust-item">🚀 Real-time Analytics</div>
        <div class="trust-item">🤖 AI-Powered Outreach</div>
        <div class="trust-item">🇨🇦 Canada-Focused</div>
    </div>
</div>

<div class="right">
    <div class="login-card">
        <h2>Admin Login</h2>
        <p>Sign in to manage your campaigns</p>
        <?php if ($error): ?>
        <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" id="loginForm">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" id="email" placeholder="admin@example.com"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="pw-wrap">
                    <input type="password" name="password" id="password" placeholder="Enter your password" required>
                    <button type="button" class="pw-toggle" onclick="togglePw()">👁️</button>
                </div>
            </div>
            <button type="button" class="quick-fill" onclick="quickFill()">⚡ Quick Fill (Demo)</button>
            <button type="submit" class="btn-login" id="loginBtn">
                <span id="btnText">Sign In →</span>
                <div class="spin" id="spinner"></div>
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function() {
    document.getElementById('btnText').style.display = 'none';
    document.getElementById('spinner').style.display = 'block';
    document.getElementById('loginBtn').disabled = true;
});
function togglePw() {
    const pw = document.getElementById('password');
    pw.type = pw.type === 'password' ? 'text' : 'password';
}
function quickFill() {
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    // Fill your credentials from README or installer output
    alert('Please enter your administrator credentials.');
}
fetch('<?php echo APP_URL; ?>/api/stats.php').then(r=>r.json()).then(d=>{
    animateCount('c-leads', d.leads || 1250);
    animateCount('c-sent',  d.sent  || 8400);
    animateCount('c-resp',  d.responses || 342);
}).catch(()=>{
    document.getElementById('c-leads').textContent='1,250';
    document.getElementById('c-sent').textContent='8,400';
    document.getElementById('c-resp').textContent='342';
});
function animateCount(id, target) {
    let current = 0;
    const el = document.getElementById(id);
    const step = Math.ceil(target / 40);
    const t = setInterval(()=>{
        current = Math.min(current + step, target);
        el.textContent = current.toLocaleString();
        if (current >= target) clearInterval(t);
    }, 40);
}
</script>
</body>
</html>
