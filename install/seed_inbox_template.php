<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$name    = 'Canada FinTech Symposium 2026 — Personal Outreach (Inbox-Friendly)';
$subject = 'Quick question about FinTech, {{first_name}}';
$body    = '<p>Hi {{first_name}},</p>

<p>Hope you\'re doing well.</p>

<p>I wanted to reach out personally — we\'re organizing the <strong>Canada FinTech Symposium 2026</strong> (May 20–21, Metro Toronto Convention Centre), and based on your work at {{company}}, I thought this would be genuinely valuable for you.</p>

<p>CFTS brings together banks, fintech leaders, regulators, and investors for focused conversations around payments infrastructure, open banking, digital assets, and embedded finance.</p>

<p><strong>Ways to get involved:</strong></p>
<p>• <strong>Exhibition Partner</strong> — Early Bird CAD $6,000 (Regular $7,500) — includes a panel slot<br>
• <strong>Premium Delegate Pass</strong> — CAD $799 — full conference + SBC Summit access<br>
• <strong>Speaking/Sponsorship</strong> — See page 16 of the attached deck</p>

<p>Would love to explore if there\'s a fit. Happy to send over the full sponsorship deck and agenda if helpful.</p>

<p>Best,</p>

{{signature}}

<p style="font-size:11px;color:#888888;margin-top:32px;">To opt out of future emails: <a href="{{unsubscribe_link}}" style="color:#888888;">unsubscribe</a></p>';

$existing = Database::fetchOne("SELECT id FROM email_templates WHERE name = ?", [$name]);
if (!$existing) {
    Database::query(
        "INSERT INTO email_templates (name, subject, html_body, is_default, created_by) VALUES (?, ?, ?, 0, 1)",
        [$name, $subject, $body]
    );
    echo "Template inserted.\n";
} else {
    echo "Template already exists.\n";
}
