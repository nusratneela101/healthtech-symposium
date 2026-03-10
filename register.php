<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$errors  = [];
$success = false;
$alreadyRegistered = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot anti-spam check
    if (!empty($_POST['website'])) {
        // Bot detected — silently reject
        $success = true;
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $company   = trim($_POST['company']    ?? '');
        $jobTitle  = trim($_POST['job_title']  ?? '');
        $province  = trim($_POST['province']   ?? '');
        $heardFrom = trim($_POST['heard_from'] ?? '');
        $consent   = !empty($_POST['consent']);

        // Validation
        if ($firstName === '') $errors[] = 'First Name is required.';
        if ($lastName  === '') $errors[] = 'Last Name is required.';
        if ($email     === '') {
            $errors[] = 'Email Address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($company === '') $errors[] = 'Company Name is required.';
        if (!$consent)       $errors[] = 'You must agree to receive marketing emails to register.';

        if (empty($errors)) {
            $fullName = $firstName . ' ' . $lastName;
            Database::query(
                "INSERT IGNORE INTO leads
                 (first_name, last_name, full_name, email, company, job_title, role, segment, country, province, city, source, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $firstName,
                    $lastName,
                    $fullName,
                    $email,
                    $company,
                    $jobTitle,
                    $jobTitle,
                    'Other',
                    'Canada',
                    $province,
                    '',
                    'Web Registration Form',
                    'active',
                ]
            );
            // INSERT IGNORE silently skips duplicate emails; check if a row was actually inserted
            $inserted = (int)Database::fetchOne("SELECT ROW_COUNT() AS n")['n'];
            if ($inserted === 0) {
                $alreadyRegistered = true;
            } else {
                $success = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Your Interest — Canada FinTech Symposium</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',Arial,sans-serif;background:#0a1628;color:#e2e8f0;min-height:100vh;padding:40px 16px}
.container{max-width:600px;margin:0 auto}
.header{text-align:center;margin-bottom:32px}
.logo-text{font-size:22px;font-weight:700;color:#d4a843;letter-spacing:1px}
.event-sub{font-size:14px;color:#8a9ab5;margin-top:6px}
.card{background:#13213a;border:1px solid #1e3355;border-radius:16px;padding:40px 36px}
.card-title{font-size:24px;font-weight:700;margin-bottom:8px}
.card-desc{font-size:14px;color:#8a9ab5;margin-bottom:28px;line-height:1.6}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{margin-bottom:18px}
label{display:block;font-size:13px;font-weight:600;color:#a8b8d0;margin-bottom:6px}
.required-star{color:#d4a843}
input[type="text"],input[type="email"],select{
    width:100%;background:#0d1f38;border:1px solid #1e3355;border-radius:8px;
    padding:11px 14px;color:#e2e8f0;font-size:14px;outline:none;transition:border-color .2s}
input[type="text"]:focus,input[type="email"]:focus,select:focus{border-color:#d4a843}
select option{background:#0d1f38}
.consent-block{background:#0d1f38;border:1px solid #1e3355;border-radius:8px;padding:16px;margin-bottom:18px}
.consent-label{display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:13px;color:#c8d8ec;line-height:1.6}
.consent-label input[type="checkbox"]{margin-top:3px;width:16px;height:16px;flex-shrink:0;accent-color:#d4a843;cursor:pointer}
.consent-label a{color:#d4a843;text-decoration:underline}
.brevo-notice{font-size:12px;color:#6a7d96;line-height:1.6;margin-bottom:24px;padding:12px 14px;border-left:3px solid #1e3355;background:#0d1f38;border-radius:0 6px 6px 0}
.btn-submit{width:100%;background:#d4a843;color:#0a1628;border:none;border-radius:8px;padding:14px;font-size:15px;font-weight:700;cursor:pointer;letter-spacing:.5px;transition:background .2s}
.btn-submit:hover{background:#c4983a}
.errors{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.35);border-radius:8px;padding:14px 16px;margin-bottom:20px}
.errors ul{list-style:none;padding:0}
.errors ul li{color:#f87171;font-size:13px;margin-bottom:4px}
.errors ul li:last-child{margin-bottom:0}
.success-box{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);border-radius:12px;padding:32px;text-align:center}
.success-icon{font-size:48px;margin-bottom:16px}
.success-title{font-size:20px;font-weight:700;color:#34d399;margin-bottom:10px}
.success-msg{font-size:14px;color:#8a9ab5;line-height:1.6}
.already-box{background:rgba(212,168,67,.08);border:1px solid rgba(212,168,67,.3);border-radius:12px;padding:28px;text-align:center}
.already-title{font-size:18px;font-weight:700;color:#d4a843;margin-bottom:8px}
.already-msg{font-size:14px;color:#8a9ab5}
/* honeypot */
.hp-field{display:none!important;visibility:hidden!important}
@media(max-width:520px){
    .form-row{grid-template-columns:1fr}
    .card{padding:28px 20px}
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo-text">🍁 Canada FinTech Symposium</div>
        <div class="event-sub">Toronto Edition (3rd) &mdash; May 20&ndash;21, 2026</div>
    </div>

    <div class="card">
        <?php if ($success): ?>
        <div class="success-box">
            <div class="success-icon">✅</div>
            <div class="success-title">Thank you! You have been registered successfully.</div>
            <div class="success-msg">We will be in touch with event updates, speaker announcements and agenda information for Canada FinTech Symposium 2026.</div>
        </div>

        <?php elseif ($alreadyRegistered): ?>
        <div class="already-box">
            <div class="already-title">You are already registered!</div>
            <div class="already-msg">Your email address is already in our list. We will keep you updated about Canada FinTech Symposium 2026.</div>
        </div>

        <?php else: ?>
        <div class="card-title">Register Your Interest</div>
        <div class="card-desc">Join 500+ fintech professionals at Canada&rsquo;s premier fintech event. Enter your details below and we will keep you informed about speakers, agenda updates and early-bird opportunities.</div>

        <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $err): ?>
                <li>&#x26A0; <?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- Honeypot anti-spam field -->
            <div class="hp-field" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name <span class="required-star">*</span></label>
                    <input type="text" id="first_name" name="first_name" required
                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                           placeholder="Jane">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span class="required-star">*</span></label>
                    <input type="text" id="last_name" name="last_name" required
                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                           placeholder="Smith">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required-star">*</span></label>
                <input type="email" id="email" name="email" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       placeholder="jane@example.com">
            </div>

            <div class="form-group">
                <label for="company">Company Name <span class="required-star">*</span></label>
                <input type="text" id="company" name="company" required
                       value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>"
                       placeholder="Acme Financial Inc.">
            </div>

            <div class="form-group">
                <label for="job_title">Job Title</label>
                <input type="text" id="job_title" name="job_title"
                       value="<?php echo htmlspecialchars($_POST['job_title'] ?? ''); ?>"
                       placeholder="VP of Fintech Innovation">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="province">Province</label>
                    <select id="province" name="province">
                        <option value="">— Select Province —</option>
                        <?php
                        $provinces = ['Ontario','British Columbia','Alberta','Quebec','Other'];
                        foreach ($provinces as $p):
                            $sel = (($_POST['province'] ?? '') === $p) ? ' selected' : '';
                        ?>
                        <option value="<?php echo htmlspecialchars($p); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($p); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="heard_from">How did you hear about us?</label>
                    <select id="heard_from" name="heard_from">
                        <option value="">— Select —</option>
                        <?php
                        $sources = ['LinkedIn','Google','Email Invitation','Colleague','Other'];
                        foreach ($sources as $s):
                            $sel = (($_POST['heard_from'] ?? '') === $s) ? ' selected' : '';
                        ?>
                        <option value="<?php echo htmlspecialchars($s); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="consent-block">
                <label class="consent-label">
                    <input type="checkbox" name="consent" value="1"
                           <?php echo !empty($_POST['consent']) ? 'checked' : ''; ?> required>
                    <span>
                        I agree to receive marketing emails, newsletters and event updates from
                        Canada FinTech Symposium. I understand I can unsubscribe at any time.
                        (<a href="https://canadafintechsymposium.com/legal/privacy-policy/" target="_blank" rel="noopener">Privacy Policy</a>)
                        <span class="required-star"> *</span>
                    </span>
                </label>
            </div>

            <div class="brevo-notice">
                We use <strong>Brevo</strong> as our marketing platform. By submitting this form you agree that the personal data you provided will be transferred to Brevo for processing in accordance with
                <a href="https://www.brevo.com/legal/privacypolicy/" target="_blank" rel="noopener" style="color:#d4a843">Brevo&rsquo;s Privacy Policy</a>.
            </div>

            <button type="submit" class="btn-submit">Register Now &rarr;</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
