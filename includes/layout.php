<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

Auth::check();

$user = Auth::user();
$unreadCount = 0;
try {
    $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM responses WHERE is_read = 0");
    $unreadCount = (int)($row['cnt'] ?? 0);
} catch (Exception $e) {}

$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Canada FinTech Symposium — <?php echo $pageTitle ?? APP_NAME; ?></title>
<link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body>
<div class="bg-grid"></div>
<div class="orb o1"></div>
<div class="orb o2"></div>
<div class="orb o3"></div>

<div class="sb">
    <div class="sb-brand">
        <div class="cfts-logo">
          <div class="cfts-leaf">
            <svg viewBox="0 0 100 100" width="52" height="52" xmlns="http://www.w3.org/2000/svg">
              <path d="M50 5 L58 30 L75 20 L65 38 L85 35 L70 50 L80 70 L60 60 L55 85 L50 75 L45 85 L40 60 L20 70 L30 50 L15 35 L35 38 L25 20 L42 30 Z" fill="#CC0000"/>
              <text x="50" y="58" text-anchor="middle" fill="white" font-size="16" font-weight="900" font-family="Arial">CFTS</text>
            </svg>
          </div>
          <div class="cfts-divider"></div>
          <div class="cfts-text">
            <div class="cfts-canada">CANADA</div>
            <div class="cfts-fintech">FINTECH</div>
            <div class="cfts-symposium">SYMPOSIUM</div>
          </div>
        </div>
        <div class="cfts-tagline">Igniting the Future of Finance</div>
    </div>
    <nav class="sb-nav">
        <a href="<?php echo APP_URL; ?>/admin/dashboard.php"
           class="nav-item<?php echo $currentPage==='dashboard.php'?' active':''; ?>">
            📊 Dashboard
        </a>
        <a href="<?php echo APP_URL; ?>/admin/leads.php"
           class="nav-item<?php echo $currentPage==='leads.php'?' active':''; ?>">
            👥 Lead Database
        </a>
        <a href="<?php echo APP_URL; ?>/admin/import_leads.php"
           class="nav-item<?php echo $currentPage==='import_leads.php'?' active':''; ?>">
            📥 Import Leads
        </a>
        <a href="<?php echo APP_URL; ?>/admin/auto_campaign.php"
           class="nav-item<?php echo $currentPage==='auto_campaign.php'?' active':''; ?>">
            🚀 Auto Campaign
        </a>
        <a href="<?php echo APP_URL; ?>/admin/campaign.php"
           class="nav-item<?php echo $currentPage==='campaign.php'?' active':''; ?>">
            📧 Campaign
        </a>
        <a href="<?php echo APP_URL; ?>/admin/audit.php"
           class="nav-item<?php echo $currentPage==='audit.php'?' active':''; ?>">
            📋 Audit Report
        </a>
        <a href="<?php echo APP_URL; ?>/admin/responses.php"
           class="nav-item<?php echo $currentPage==='responses.php'?' active':''; ?>">
            💬 Responses
            <?php if ($unreadCount > 0): ?>
                <span class="nbadge"><?php echo $unreadCount; ?></span>
            <?php endif; ?>
        </a>
        <?php if (Auth::isSuperAdmin()): ?>
        <a href="<?php echo APP_URL; ?>/admin/templates.php"
           class="nav-item<?php echo $currentPage==='templates.php'?' active':''; ?>">
            ✉️ Templates
        </a>
        <a href="<?php echo APP_URL; ?>/admin/users.php"
           class="nav-item<?php echo $currentPage==='users.php'?' active':''; ?>">
            👤 Users
        </a>
        <?php endif; ?>
    </nav>
    <div class="sb-user">
        <div style="font-size:12px;color:var(--text-muted)">Logged in as</div>
        <div style="font-size:13px;font-weight:600"><?php echo htmlspecialchars($user['name']); ?></div>
        <a href="<?php echo APP_URL; ?>/logout.php" style="font-size:11px;color:#ef4444;text-decoration:none;margin-top:4px;display:inline-block">Logout →</a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div>
            <div class="tb-title"><?php echo APP_NAME; ?></div>
            <div class="tb-sub">Campaign Management Platform v<?php echo APP_VERSION; ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:16px">
            <div class="tb-clock" id="clock"></div>
            <div class="live-pill"><span class="ld"></span>LIVE</div>
            <?php if (Auth::isSuperAdmin()): ?>
                <span class="badge-sa">SUPER ADMIN</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="flash-msg flash-<?php echo $flash['type']; ?>" id="flash-msg">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
    <?php endif; ?>

    <div class="page-content">
<div class="toast-wrap" id="toast-wrap"></div>
