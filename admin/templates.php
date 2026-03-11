<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireSuperAdmin();

// Handle template actions BEFORE loading layout (which outputs HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id           = (int)($_POST['tpl_id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $subject      = trim($_POST['subject'] ?? '');
        $body         = trim($_POST['html_body'] ?? '');
        $signatureHtml = trim($_POST['signature_html'] ?? '');
        $isDefault    = isset($_POST['is_default']) ? 1 : 0;

        // Handle attachment upload
        $attachmentPath = null;
        if (!empty($_FILES['attachment']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/attachments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext      = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $allowed  = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
            $allowedMime = ['application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/png', 'image/jpeg'];
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mime     = $finfo->file($_FILES['attachment']['tmp_name']);
            if (in_array($ext, $allowed, true) && in_array($mime, $allowedMime, true)
                && $_FILES['attachment']['size'] <= 5 * 1024 * 1024) {
                $filename       = bin2hex(random_bytes(16)) . '.' . $ext;
                $destPath       = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destPath)) {
                    $attachmentPath = 'uploads/attachments/' . $filename;
                }
            }
        }

        // Handle header image upload
        $headerImageUrl = null;
        if (!empty($_FILES['header_image']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/template_images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext     = strtolower(pathinfo($_FILES['header_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            $allowedMime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
            $finfo   = new finfo(FILEINFO_MIME_TYPE);
            $mime    = $finfo->file($_FILES['header_image']['tmp_name']);
            if (in_array($ext, $allowed, true) && in_array($mime, $allowedMime, true)
                && $_FILES['header_image']['size'] <= 5 * 1024 * 1024) {
                $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                $destPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['header_image']['tmp_name'], $destPath)) {
                    $headerImageUrl = APP_URL . '/uploads/template_images/' . $filename;
                }
            }
        }

        if ($isDefault) {
            Database::query("UPDATE email_templates SET is_default=0");
        }
        if ($id) {
            // Preserve existing upload paths if no new file was uploaded
            $existing = Database::fetchOne("SELECT attachment_path, header_image_url FROM email_templates WHERE id=?", [$id]);
            if ($attachmentPath === null && $existing) {
                $attachmentPath = $existing['attachment_path'];
            }
            if ($headerImageUrl === null && $existing) {
                $headerImageUrl = $existing['header_image_url'];
            }
            Database::query(
                "UPDATE email_templates SET name=?,subject=?,html_body=?,signature_html=?,attachment_path=?,header_image_url=?,is_default=?,updated_at=NOW() WHERE id=?",
                [$name, $subject, $body, $signatureHtml, $attachmentPath, $headerImageUrl, $isDefault, $id]
            );
            flash('success', 'Template updated.');
        } else {
            Database::query(
                "INSERT INTO email_templates (name,subject,html_body,signature_html,attachment_path,header_image_url,is_default,created_by) VALUES(?,?,?,?,?,?,?,?)",
                [$name, $subject, $body, $signatureHtml, $attachmentPath, $headerImageUrl, $isDefault, Auth::user()['id']]
            );
            flash('success', 'Template created.');
        }
        header('Location: ' . APP_URL . '/admin/templates.php');
        exit;
    }
    if ($action === 'delete') {
        $id = (int)$_POST['tpl_id'];
        Database::query("DELETE FROM email_templates WHERE id=?", [$id]);
        flash('success', 'Template deleted.');
        header('Location: ' . APP_URL . '/admin/templates.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/layout.php';

$templates = Database::fetchAll("SELECT * FROM email_templates ORDER BY is_default DESC, id DESC");
$editing   = null;
if (isset($_GET['edit'])) {
    $editing = Database::fetchOne("SELECT * FROM email_templates WHERE id=?", [(int)$_GET['edit']]);
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:20px">✉️ Email Templates</h2>
    <a href="?new=1" class="btn-launch" style="text-decoration:none;font-size:13px">+ New Template</a>
</div>

<div class="grid-2">
    <div class="gc">
        <div class="gc-title"><?php echo $editing ? 'Edit Template' : (isset($_GET['new']) ? 'New Template' : 'Templates'); ?></div>
        <?php if ($editing || isset($_GET['new'])): ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="tpl_id" value="<?php echo $editing['id'] ?? 0; ?>">
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Template Name</label>
                <input class="fi" name="name" value="<?php echo htmlspecialchars($editing['name'] ?? ''); ?>" required style="width:100%">
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">Email Subject</label>
                <input class="fi" name="subject" value="<?php echo htmlspecialchars($editing['subject'] ?? ''); ?>" required style="width:100%">
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">HTML Body</label>
                <textarea class="fi rt" name="html_body" style="width:100%;min-height:300px;font-family:monospace;font-size:12px;resize:vertical"><?php echo htmlspecialchars($editing['html_body'] ?? ''); ?></textarea>
            </div>
            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- 🎨  VISUAL SIGNATURE BUILDER                               -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <div style="margin-bottom:12px">
                <div id="sig-builder-toggle" onclick="toggleSigBuilder()" style="display:flex;align-items:center;justify-content:space-between;background:#0d1b2e;border:1px solid #1e3a5f;border-radius:8px;padding:12px 16px;cursor:pointer;user-select:none">
                    <span style="font-size:14px;font-weight:600;color:#e2e8f0">🎨 Signature Builder</span>
                    <span id="sig-builder-arrow" style="color:#8a9ab5;font-size:12px">▼ expand</span>
                </div>

                <div id="sig-builder-panel" style="display:none;border:1px solid #1e3a5f;border-top:none;border-radius:0 0 8px 8px;background:#0a1628;padding:16px">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

                        <!-- ── LEFT: Accordion fields ── -->
                        <div id="sig-fields">

                            <!-- Personal Data -->
                            <div class="sig-section">
                                <div class="sig-sec-hdr" onclick="toggleSec(this)"><span>👤 Personal Data</span><span class="sig-arr">▲</span></div>
                                <div class="sig-sec-body">
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
                                        <div>
                                            <label class="sig-lbl">First Name</label>
                                            <input class="fi sig-fi" id="sb_fname" value="Rachael" oninput="buildSignature()">
                                        </div>
                                        <div>
                                            <label class="sig-lbl">Last Name / Title</label>
                                            <input class="fi sig-fi" id="sb_lname" value="Head - Partnerships" oninput="buildSignature()">
                                        </div>
                                    </div>
                                    <div style="margin-bottom:8px">
                                        <label class="sig-lbl">Job Title / Company (line below name)</label>
                                        <input class="fi sig-fi" id="sb_jobtitle" value="Prolink Events Ltd" oninput="buildSignature()">
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
                                        <div>
                                            <label class="sig-lbl">Email Address</label>
                                            <input class="fi sig-fi" id="sb_email" value="info@canadafintechsymposium.com" oninput="buildSignature()">
                                        </div>
                                        <div>
                                            <label class="sig-lbl">Phone (Telephone)</label>
                                            <input class="fi sig-fi" id="sb_phone" value="+1 403 383 0513" oninput="buildSignature()">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="sig-lbl">Mobile</label>
                                        <input class="fi sig-fi" id="sb_mobile" value="(800) 555-0299" oninput="buildSignature()">
                                    </div>
                                </div>
                            </div>

                            <!-- Company Data -->
                            <div class="sig-section">
                                <div class="sig-sec-hdr" onclick="toggleSec(this)"><span>🏢 Company Data</span><span class="sig-arr">▲</span></div>
                                <div class="sig-sec-body">
                                    <div style="margin-bottom:8px">
                                        <label class="sig-lbl">Company Name</label>
                                        <input class="fi sig-fi" id="sb_company" value="Canada FinTech Symposium" oninput="buildSignature()">
                                    </div>
                                    <div style="margin-bottom:8px">
                                        <label class="sig-lbl">Website URL</label>
                                        <input class="fi sig-fi" id="sb_website" value="www.canadafintechsymposium.com" oninput="buildSignature()">
                                    </div>
                                    <div style="margin-bottom:8px">
                                        <label class="sig-lbl">Address Line 1</label>
                                        <input class="fi sig-fi" id="sb_addr1" value="30, Royal Oak Plaza, NW Calgary" oninput="buildSignature()">
                                    </div>
                                    <div>
                                        <label class="sig-lbl">Address Line 2</label>
                                        <input class="fi sig-fi" id="sb_addr2" value="Alberta T3G 0C1" oninput="buildSignature()">
                                    </div>
                                </div>
                            </div>

                            <!-- Graphics -->
                            <div class="sig-section">
                                <div class="sig-sec-hdr" onclick="toggleSec(this)"><span>🖼️ Graphics</span><span class="sig-arr">▲</span></div>
                                <div class="sig-sec-body">
                                    <div style="margin-bottom:8px">
                                        <label class="sig-lbl">Logo URL <span style="color:#8a9ab5">(88px wide)</span></label>
                                        <input class="fi sig-fi" id="sb_logo" placeholder="https://…/logo.png" oninput="buildSignature()">
                                    </div>
                                    <div style="margin-bottom:8px">
                                        <label class="sig-lbl">Logo Link URL</label>
                                        <input class="fi sig-fi" id="sb_logolink" placeholder="https://…" oninput="buildSignature()">
                                    </div>
                                    <div style="margin-bottom:8px">
                                        <label class="sig-lbl">Banner / Branding Image URL <span style="color:#8a9ab5">(400px wide)</span></label>
                                        <input class="fi sig-fi" id="sb_banner" placeholder="https://…/banner.jpg" oninput="buildSignature()">
                                    </div>
                                    <div>
                                        <label class="sig-lbl">Banner Link URL</label>
                                        <input class="fi sig-fi" id="sb_bannerlink" placeholder="https://…" oninput="buildSignature()">
                                    </div>
                                </div>
                            </div>

                            <!-- Disclaimer -->
                            <div class="sig-section">
                                <div class="sig-sec-hdr" onclick="toggleSec(this)"><span>📄 Disclaimer Text</span><span class="sig-arr">▲</span></div>
                                <div class="sig-sec-body">
                                    <textarea class="fi sig-fi" id="sb_disclaimer" rows="4" style="resize:vertical" oninput="buildSignature()">The content of this email is confidential and intended for the recipient specified in message only. It is strictly forbidden to share any part of this content with any third party, without a written consent of the sender. If you received this message by mistake, please reply to this message and follow with its deletion, so that we can ensure such a mistake does not occur in the future.</textarea>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px">
                                        <div>
                                            <label class="sig-lbl">Font Family</label>
                                            <select class="fi sig-fi" id="sb_disc_font" onchange="buildSignature()">
                                                <option value="Arial, sans-serif">Arial</option>
                                                <option value="Calibri, sans-serif">Calibri</option>
                                                <option value="Georgia, serif">Georgia</option>
                                                <option value="Verdana, sans-serif">Verdana</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="sig-lbl">Font Size (px)</label>
                                            <input class="fi sig-fi" type="number" id="sb_disc_size" value="11" min="8" max="18" oninput="buildSignature()">
                                        </div>
                                    </div>
                                    <div style="margin-top:8px">
                                        <label class="sig-lbl">Text Color</label>
                                        <div style="display:flex;gap:6px;align-items:center">
                                            <input type="color" id="sb_disc_color" value="#888888" oninput="buildSignature()" style="width:40px;height:32px;padding:2px;border:1px solid #1e3a5f;border-radius:4px;background:#0d1b2e;cursor:pointer">
                                            <input class="fi sig-fi" id="sb_disc_color_hex" value="#888888" oninput="syncColor('sb_disc_color','sb_disc_color_hex');buildSignature()" style="flex:1">
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:16px;margin-top:8px;align-items:center">
                                        <label class="sig-lbl" style="display:flex;align-items:center;gap:4px;margin-bottom:0">
                                            <input type="checkbox" id="sb_disc_bold" onchange="buildSignature()"> Bold
                                        </label>
                                        <label class="sig-lbl" style="display:flex;align-items:center;gap:4px;margin-bottom:0">
                                            <input type="checkbox" id="sb_disc_italic" checked onchange="buildSignature()"> Italic
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Style -->
                            <div class="sig-section">
                                <div class="sig-sec-hdr" onclick="toggleSec(this)"><span>🎨 Style</span><span class="sig-arr">▲</span></div>
                                <div class="sig-sec-body">
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
                                        <div>
                                            <label class="sig-lbl">Primary Color</label>
                                            <div style="display:flex;gap:6px;align-items:center">
                                                <input type="color" id="sb_primary_color" value="#1a3a6b" oninput="buildSignature()" style="width:40px;height:32px;padding:2px;border:1px solid #1e3a5f;border-radius:4px;background:#0d1b2e;cursor:pointer">
                                                <input class="fi sig-fi" id="sb_primary_hex" value="#1a3a6b" oninput="syncColor('sb_primary_color','sb_primary_hex');buildSignature()" style="flex:1">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="sig-lbl">Name Color</label>
                                            <div style="display:flex;gap:6px;align-items:center">
                                                <input type="color" id="sb_name_color" value="#1a6bbf" oninput="buildSignature()" style="width:40px;height:32px;padding:2px;border:1px solid #1e3a5f;border-radius:4px;background:#0d1b2e;cursor:pointer">
                                                <input class="fi sig-fi" id="sb_name_hex" value="#1a6bbf" oninput="syncColor('sb_name_color','sb_name_hex');buildSignature()" style="flex:1">
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="sig-lbl">Font Family</label>
                                        <select class="fi sig-fi" id="sb_font" onchange="buildSignature()">
                                            <option value="Arial, sans-serif">Arial</option>
                                            <option value="Calibri, sans-serif">Calibri</option>
                                            <option value="Georgia, serif">Georgia</option>
                                            <option value="Verdana, sans-serif">Verdana</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Social Media -->
                            <div class="sig-section">
                                <div class="sig-sec-hdr" onclick="toggleSec(this)"><span>🔗 Social Media Links</span><span class="sig-arr">▲</span></div>
                                <div class="sig-sec-body">
                                    <div style="margin-bottom:8px">
                                        <label class="sig-lbl">LinkedIn URL</label>
                                        <input class="fi sig-fi" id="sb_linkedin" value="https://www.linkedin.com/company/canada-fintech-symposium" oninput="buildSignature()">
                                    </div>
                                    <div>
                                        <label class="sig-lbl">Instagram URL</label>
                                        <input class="fi sig-fi" id="sb_instagram" value="https://www.instagram.com/canadafintechsymposium" oninput="buildSignature()">
                                    </div>
                                </div>
                            </div>

                            <button type="button" onclick="applySignature()" style="width:100%;margin-top:12px;padding:10px;background:linear-gradient(135deg,#1a6bbf,#1a3a6b);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;letter-spacing:0.3px">Apply Signature →</button>
                        </div><!-- /sig-fields -->

                        <!-- ── RIGHT: Live Preview ── -->
                        <div>
                            <div style="font-size:12px;color:#8a9ab5;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px">Live Preview</div>
                            <div id="sig-preview" style="background:#fff;border-radius:8px;padding:16px;min-height:200px;overflow:auto;font-size:13px"></div>
                        </div>

                    </div><!-- /grid -->
                </div><!-- /sig-builder-panel -->
            </div><!-- /sig-builder wrapper -->

            <!-- ── Email Signature (manual / advanced) ── -->
            <div style="margin-bottom:12px">
                <div onclick="toggleAdvSig()" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:6px">
                    <label style="font-size:13px;color:#8a9ab5;cursor:pointer">✍️ Email Signature</label>
                    <span style="font-size:11px;color:#8a9ab5">(optional — appended to bottom of email)</span>
                    <span id="adv-sig-arrow" style="font-size:11px;color:#8a9ab5">▼ show / edit manually</span>
                </div>
                <div id="adv-sig-wrap" style="display:none">
                    <textarea class="fi rt" name="signature_html" id="sig_html_ta" style="width:100%;min-height:120px;font-family:monospace;font-size:12px;resize:vertical"><?php echo htmlspecialchars($editing['signature_html'] ?? ''); ?></textarea>
                </div>
            </div>

            <style>
            .sig-section{margin-bottom:4px;border:1px solid #1e3a5f;border-radius:6px;overflow:hidden}
            .sig-sec-hdr{display:flex;justify-content:space-between;align-items:center;padding:9px 12px;background:#0d1b2e;cursor:pointer;font-size:13px;color:#e2e8f0;user-select:none}
            .sig-sec-hdr:hover{background:#112240}
            .sig-sec-body{padding:12px;background:#09182d}
            .sig-arr{font-size:10px;color:#8a9ab5}
            .sig-lbl{display:block;font-size:12px;color:#8a9ab5;margin-bottom:4px}
            .sig-fi{width:100%;font-size:12px !important}
            </style>

            <script>
            /* ── Accordion helpers ── */
            function toggleSec(hdr) {
                var body = hdr.nextElementSibling;
                var arr  = hdr.querySelector('.sig-arr');
                if (body.style.display === 'none') {
                    body.style.display = 'block';
                    arr.textContent = '▲';
                } else {
                    body.style.display = 'none';
                    arr.textContent = '▼';
                }
            }
            function toggleSigBuilder() {
                var panel = document.getElementById('sig-builder-panel');
                var arrow = document.getElementById('sig-builder-arrow');
                if (panel.style.display === 'none') {
                    panel.style.display = 'block';
                    arrow.textContent = '▲ collapse';
                    buildSignature();
                } else {
                    panel.style.display = 'none';
                    arrow.textContent = '▼ expand';
                }
            }
            function toggleAdvSig() {
                var wrap  = document.getElementById('adv-sig-wrap');
                var arrow = document.getElementById('adv-sig-arrow');
                if (wrap.style.display === 'none') {
                    wrap.style.display = 'block';
                    arrow.textContent = '▲ hide';
                } else {
                    wrap.style.display = 'none';
                    arrow.textContent = '▼ show / edit manually';
                }
            }

            /* Allowed color input IDs to prevent arbitrary element targeting */
            var SIG_COLOR_PAIRS = {
                'sb_primary_color': 'sb_primary_hex',
                'sb_name_color':    'sb_name_hex',
                'sb_disc_color':    'sb_disc_color_hex'
            };
            function syncColor(colorId, hexId) {
                if (!SIG_COLOR_PAIRS.hasOwnProperty(colorId) || SIG_COLOR_PAIRS[colorId] !== hexId) { return; }
                var hex = document.getElementById(hexId).value.trim();
                if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
                    document.getElementById(colorId).value = hex;
                }
            }

            /* ── Security helpers ── */
            /** Escape text for safe inline HTML content */
            function escHtml(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }
            /** Validate a URL — allow only http/https; return empty string for anything else */
            function safeUrl(url) {
                var s = String(url).trim();
                if (!s) { return ''; }
                if (/^https?:\/\//i.test(s)) { return s; }
                return '';
            }
            /** Convert a bare domain to an https:// URL; reject non-http(s) schemes */
            function normalizeWebsite(s) {
                if (!s) { return ''; }
                if (/^https?:\/\//i.test(s)) { return s; }
                /* bare domain like www.example.com */
                if (/^[a-z0-9]([a-z0-9\-\.]*[a-z0-9])?\.[a-z]{2,}/i.test(s)) { return 'https://' + s; }
                return '';
            }

            /* ── Main signature builder ── */
            function v(id) { return (document.getElementById(id) || {value:''}).value.trim(); }

            function buildSignature() {
                /* sync color pickers → hex inputs */
                document.getElementById('sb_primary_hex').value = document.getElementById('sb_primary_color').value;
                document.getElementById('sb_name_hex').value    = document.getElementById('sb_name_color').value;

                var fname    = escHtml(v('sb_fname'));
                var lname    = escHtml(v('sb_lname'));
                var jobtitle = escHtml(v('sb_jobtitle'));
                var email    = escHtml(v('sb_email'));
                var phone    = escHtml(v('sb_phone'));
                var mobile   = escHtml(v('sb_mobile'));
                var company  = escHtml(v('sb_company'));
                var website  = escHtml(v('sb_website'));
                var addr1    = escHtml(v('sb_addr1'));
                var addr2    = escHtml(v('sb_addr2'));
                var logo        = safeUrl(v('sb_logo'));
                var logoLink    = safeUrl(v('sb_logolink'));
                var banner      = safeUrl(v('sb_banner'));
                var bannerLink  = safeUrl(v('sb_bannerlink'));
                var disclaimer  = escHtml(v('sb_disclaimer'));
                var linkedin    = safeUrl(v('sb_linkedin'));
                var instagram   = safeUrl(v('sb_instagram'));
                var websiteHref = normalizeWebsite(v('sb_website'));

                var primaryColor = document.getElementById('sb_primary_color').value || '#1a3a6b';
                var nameColor    = document.getElementById('sb_name_color').value    || '#1a6bbf';
                var font         = v('sb_font') || 'Arial, sans-serif';

                /* Validate colors */
                if (!/^#[0-9a-fA-F]{6}$/.test(primaryColor)) { primaryColor = '#1a3a6b'; }
                if (!/^#[0-9a-fA-F]{6}$/.test(nameColor))    { nameColor    = '#1a6bbf'; }

                var fullName = (fname + ' ' + lname).trim();

                /* -- Logo cell -- */
                var logoCell = '';
                if (logo) {
                    var logoImg = '<img src="' + logo + '" width="88" alt="Logo" style="display:block;border:0">';
                    logoCell = logoLink ? '<a href="' + logoLink + '" target="_blank" style="text-decoration:none">' + logoImg + '</a>' : logoImg;
                    logoCell = '<td style="padding-right:16px;vertical-align:top;width:104px">' + logoCell + '</td>';
                }

                /* -- Name/job cell -- */
                var nameBlock  = fullName  ? '<div style="font-size:16px;font-weight:700;color:' + primaryColor + ';line-height:1.2">' + fullName + '</div>' : '';
                var titleBlock = jobtitle  ? '<div style="font-size:12px;color:' + nameColor + ';margin-top:2px">' + jobtitle + '</div>' : '';
                var nameCell   = '<td style="vertical-align:top">' + nameBlock + titleBlock + '</td>';

                /* -- Contact grid -- */
                var emailRow  = email  ? '<span style="color:#555;font-size:11px">&#9993; </span><a href="mailto:' + email + '" style="color:#333;text-decoration:none;font-size:12px">' + email + '</a>' : '';
                var mobileRow = mobile ? '<span style="color:#555;font-size:11px">&#128241; </span><span style="font-size:12px;color:#333">' + mobile + '</span>' : '';
                var addrRow   = (addr1 || addr2) ? '<span style="color:#555;font-size:11px">&#128205; </span><span style="font-size:12px;color:#333">' + [addr1, addr2].filter(Boolean).join(', ') + '</span>' : '';
                var phoneRow  = phone  ? '<span style="color:#555;font-size:11px">&#128222; </span><span style="font-size:12px;color:#333">' + phone + '</span>' : '';

                var contactGrid = '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:8px">'
                    + '<tr>'
                    + '<td style="width:50%;vertical-align:top;padding-right:8px">'
                    + (emailRow  ? '<div style="margin-bottom:4px">' + emailRow  + '</div>' : '')
                    + (mobileRow ? '<div style="margin-bottom:4px">' + mobileRow + '</div>' : '')
                    + '</td>'
                    + '<td style="width:50%;vertical-align:top">'
                    + (addrRow  ? '<div style="margin-bottom:4px">' + addrRow  + '</div>' : '')
                    + (phoneRow ? '<div style="margin-bottom:4px">' + phoneRow + '</div>' : '')
                    + '</td>'
                    + '</tr></table>';

                /* -- Website + social row -- */
                var websiteLink = websiteHref ? '<a href="' + escHtml(websiteHref) + '" target="_blank" style="font-size:12px;color:' + primaryColor + ';text-decoration:none">&#127760; ' + website + '</a>' : '';
                var socialIcons = '';
                if (linkedin) {
                    socialIcons += '<a href="' + escHtml(linkedin) + '" target="_blank" style="margin-left:6px"><img src="https://cdn-icons-png.flaticon.com/24/174/174857.png" width="20" height="20" alt="LinkedIn" style="border:0;vertical-align:middle"></a>';
                }
                if (instagram) {
                    socialIcons += '<a href="' + escHtml(instagram) + '" target="_blank" style="margin-left:6px"><img src="https://cdn-icons-png.flaticon.com/24/2111/2111463.png" width="20" height="20" alt="Instagram" style="border:0;vertical-align:middle"></a>';
                }
                var socialRow = (websiteLink || socialIcons)
                    ? '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:8px"><tr><td style="vertical-align:middle">' + websiteLink + '</td><td style="text-align:right;vertical-align:middle">' + socialIcons + '</td></tr></table>'
                    : '';

                /* -- Banner -- */
                var bannerBlock = '';
                if (banner) {
                    var bannerImg = '<img src="' + banner + '" width="400" alt="Banner" style="display:block;border:0;max-width:100%">';
                    bannerBlock = '<tr><td colspan="2" style="padding-top:12px">'
                        + (bannerLink ? '<a href="' + escHtml(bannerLink) + '" target="_blank" style="text-decoration:none">' + bannerImg + '</a>' : bannerImg)
                        + '</td></tr>';
                }

                /* -- Disclaimer -- */
                var discFontRaw = v('sb_disc_font');
                var discFontAllowed = ['Arial, sans-serif','Calibri, sans-serif','Georgia, serif','Verdana, sans-serif'];
                var discFont   = discFontAllowed.indexOf(discFontRaw) !== -1 ? discFontRaw : 'Arial, sans-serif';
                var discSize   = parseInt(v('sb_disc_size')) || 11;
                var discColor  = document.getElementById('sb_disc_color').value || '#888888';
                var discBold   = document.getElementById('sb_disc_bold').checked;
                var discItalic = document.getElementById('sb_disc_italic').checked;

                if (!/^#[0-9a-fA-F]{6}$/.test(discColor)) { discColor = '#888888'; }
                if (discSize < 8) discSize = 8;
                if (discSize > 18) discSize = 18;

                var discStyle = 'padding-top:10px;font-size:' + discSize + 'px;color:' + discColor
                    + ';font-style:' + (discItalic ? 'italic' : 'normal')
                    + ';font-weight:' + (discBold ? '700' : '400')
                    + ';font-family:' + discFont
                    + ';line-height:1.4';

                var disclaimerBlock = disclaimer
                    ? '<tr><td colspan="2" style="' + discStyle + '">' + disclaimer + '</td></tr>'
                    : '';

                /* -- Company name divider -- */
                var companyBlock = company
                    ? '<div style="font-size:12px;font-weight:600;color:' + primaryColor + ';margin-top:6px;padding-top:6px;border-top:1px solid #dde4ef">' + company + '</div>'
                    : '';

                /* Assemble full table */
                var cols = logo ? '2' : '1';
                var html = '<!-- Email Signature -->\n'
                    + '<table cellpadding="0" cellspacing="0" border="0" style="font-family:' + font + ';max-width:480px;border-top:3px solid ' + primaryColor + ';padding-top:12px">'
                    + '<tr>'
                    + (logoCell || '')
                    + nameCell
                    + '</tr>'
                    + (companyBlock ? '<tr><td colspan="' + cols + '" style="padding-top:2px">' + companyBlock + '</td></tr>' : '')
                    + '<tr><td colspan="' + cols + '">' + contactGrid + '</td></tr>'
                    + '<tr><td colspan="' + cols + '">' + socialRow   + '</td></tr>'
                    + bannerBlock
                    + disclaimerBlock
                    + '</table>';

                /* Update preview */
                document.getElementById('sig-preview').innerHTML = html;
                return html;
            }

            function applySignature() {
                /* Ensure advanced textarea is visible so the user can see it was updated */
                var wrap  = document.getElementById('adv-sig-wrap');
                var arrow = document.getElementById('adv-sig-arrow');
                if (wrap.style.display === 'none') {
                    wrap.style.display = 'block';
                    arrow.textContent = '▲ hide';
                }
                var html = buildSignature();
                document.getElementById('sig_html_ta').value = html;

                /* Toast */
                var toast = document.createElement('div');
                toast.textContent = '✅ Signature applied!';
                toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#10b981;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.4)';
                document.body.appendChild(toast);
                setTimeout(function(){ toast.remove(); }, 2800);
            }
            </script>
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">📎 Attachment <span style="color:#8a9ab5;font-size:11px">(optional — PDF, DOC, image — max 5MB)</span></label>
                <input type="file" name="attachment" class="fi" style="width:100%" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg">
                <?php if (!empty($editing['attachment_path'])): ?>
                <div style="font-size:12px;color:#10b981;margin-top:4px">📎 Current: <?php echo htmlspecialchars(basename($editing['attachment_path'])); ?></div>
                <?php endif; ?>
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5;display:block;margin-bottom:6px">🖼️ Header Image <span style="color:#8a9ab5;font-size:11px">(optional — shown at top of email)</span></label>
                <input type="file" name="header_image" class="fi" style="width:100%" accept=".png,.jpg,.jpeg,.gif,.webp">
                <?php if (!empty($editing['header_image_url'])): ?>
                <div style="margin-top:6px"><img src="<?php echo htmlspecialchars($editing['header_image_url']); ?>" style="max-height:80px;border-radius:6px"></div>
                <?php endif; ?>
            </div>
            <div style="margin-bottom:16px;display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="is_default" id="is_default" <?php echo ($editing['is_default'] ?? 0) ? 'checked' : ''; ?>>
                <label for="is_default" style="font-size:13px;color:#8a9ab5">Set as default template</label>
            </div>
            <button type="submit" class="btn-launch">💾 Save Template</button>
            <a href="<?php echo APP_URL; ?>/admin/templates.php" class="btn-sec" style="text-decoration:none;margin-left:8px">Cancel</a>
        </form>
        <?php else: ?>
        <div class="tbl-wrap">
            <table class="dt">
                <thead><tr><th>#</th><th>Name</th><th>Subject</th><th>Default</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($templates as $t): ?>
                <tr>
                    <td><?php echo $t['id']; ?></td>
                    <td><?php echo htmlspecialchars($t['name']); ?></td>
                    <td style="font-size:12px"><?php echo htmlspecialchars(substr($t['subject'], 0, 50)); ?>…</td>
                    <td><?php echo $t['is_default'] ? '<span class="pill p-sent">Default</span>' : ''; ?></td>
                    <td style="display:flex;gap:6px">
                        <a href="?edit=<?php echo $t['id']; ?>" style="background:#0d6efd;color:#fff;padding:4px 10px;border-radius:6px;text-decoration:none;font-size:12px">Edit</a>
                        <a href="?preview=<?php echo $t['id']; ?>" target="_blank" style="background:#8b5cf6;color:#fff;padding:4px 10px;border-radius:6px;text-decoration:none;font-size:12px">Preview</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete template?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="tpl_id" value="<?php echo $t['id']; ?>">
                            <button type="submit" style="background:#ef4444;color:#fff;padding:4px 10px;border-radius:6px;border:none;cursor:pointer;font-size:12px">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="gc">
        <div class="gc-title">🔖 Template Variables</div>
        <div class="gc-sub">Use these placeholders in your template</div>
        <div style="background:#0d1f38;border-radius:8px;padding:16px;font-size:13px;line-height:2">
            <code style="color:#10b981">{{first_name}}</code> — First name<br>
            <code style="color:#10b981">{{last_name}}</code> — Last name<br>
            <code style="color:#10b981">{{full_name}}</code> — Full name<br>
            <code style="color:#10b981">{{role}}</code> — Job role/title<br>
            <code style="color:#10b981">{{company}}</code> — Company name<br>
            <code style="color:#10b981">{{city}}</code> — City<br>
            <code style="color:#10b981">{{province}}</code> — Province<br>
            <code style="color:#10b981">{{email}}</code> — Email address<br>
            <code style="color:#10b981">{{unsubscribe_link}}</code> — Unsubscribe URL<br>
            <code style="color:#10b981">{{signature}}</code> — Your email signature (auto-appended)
        </div>
        <?php if (isset($_GET['preview'])): ?>
        <div style="margin-top:16px">
            <div class="gc-title" style="margin-bottom:8px">Preview</div>
            <?php $tpl = Database::fetchOne("SELECT html_body FROM email_templates WHERE id=?", [(int)$_GET['preview']]); ?>
            <?php if ($tpl): ?>
            <iframe srcdoc="<?php echo htmlspecialchars($tpl['html_body']); ?>" style="width:100%;height:400px;border:1px solid #1e3355;border-radius:8px;background:#fff"></iframe>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
