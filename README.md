# 🏥 Canada HealthTech Innovation Symposium 2026

A complete, production-ready PHP web application for managing the **Canada HealthTech Innovation Symposium 2026** — including lead management, automated email campaigns, IMAP inbox polling, response tracking, and n8n workflow automation.

---

## 📋 Requirements

- PHP 8.0+ with PDO, imap extension
- MySQL 5.7+ or MariaDB 10.3+
- PHPMailer (via Composer or `phpmailer/src/` folder)
- Web server (Apache/Nginx) with `mod_rewrite`
- (Optional) n8n for workflow automation

---

## 🚀 5-Step Installation (বাংলা)

1. **ফাইল আপলোড করুন** — সমস্ত ফাইল আপনার সার্ভারের `public_html/healthtech/` ফোল্ডারে আপলোড করুন।
2. **PHPMailer ইন্সটল করুন** — `composer require phpmailer/phpmailer` রান করুন অথবা `phpmailer/src/` ফোল্ডারে PHPMailer ফাইলগুলো রাখুন।
3. **ইন্সটলার চালান** — ব্রাউজারে `https://yourdomain.com/healthtech/install/auto_installer.php` খুলুন।
4. **ডাটাবেজ তথ্য দিন** — DB নাম, ইউজার, পাসওয়ার্ড এবং App URL পূরণ করুন, তারপর "Install Now" ক্লিক করুন।
5. **ড্যাশবোর্ডে যান** — ইন্সটলেশন সম্পন্ন হলে "Go to Dashboard" বাটনে ক্লিক করুন এবং `install/auto_installer.php` ডিলিট করুন।

---

## 🤖 n8n Setup (৯ ধাপ বাংলায়)

1. n8n ইন্সটল করুন: `npm install -g n8n` বা Docker দিয়ে চালান।
2. n8n খুলুন: `http://localhost:5678`
3. **New Workflow** ক্লিক করুন।
4. `n8n_workflows/healthtech_master_workflow.json` ফাইলটি Import করুন।
5. সব `YOURSITE.com` URL গুলো আপনার ডোমেইন দিয়ে রিপ্লেস করুন।
6. Apollo.io credential সেট করুন (HTTP Header Auth)।
7. SMTP credential সেট করুন (Email Send নোডে)।
8. Workflow সেভ করুন এবং **Activate** করুন।
9. একইভাবে `response_tracker.json` ইম্পোর্ট করুন এবং অ্যাক্টিভেট করুন।

---

## 🔍 Apollo.io Setup

1. Apollo.io অ্যাকাউন্টে লগইন করুন।
2. **People Search** → Location: Canada, Industry: Financial / Technology ফিল্টার দিন।
3. Contacts সিলেক্ট করুন → **Export → CSV**।
4. CSV-তে `segment` কলাম যোগ করুন।
5. `admin/import_leads.php` এ CSV আপলোড করুন।

---

## 🔑 Default Credentials

| Role         | Email                | Password      |
|--------------|----------------------|---------------|
| Super Admin  | sm@101bdtech.com     | Nurnobi131221 |

---

## 📁 File Structure

```
healthtech-symposium/
├── index.php
├── login.php
├── logout.php
├── unsubscribe.php
├── config/
│   ├── config.php
│   └── database.php
├── includes/
│   ├── auth.php
│   ├── functions.php
│   ├── layout.php
│   ├── layout_end.php
│   ├── email.php
│   └── imap.php
├── admin/
│   ├── dashboard.php
│   ├── leads.php
│   ├── import_leads.php
│   ├── auto_campaign.php
│   ├── campaign.php
│   ├── audit.php
│   ├── responses.php
│   ├── templates.php
│   └── users.php
├── api/
│   ├── stats.php
│   ├── save_lead.php
│   ├── save_response.php
│   ├── log_email.php
│   ├── get_template.php
│   ├── get_leads_for_campaign.php
│   ├── send_one_email.php
│   ├── update_campaign.php
│   ├── poll_inbox.php
│   └── download_sample_csv.php
├── install/
│   ├── setup.sql
│   └── auto_installer.php
├── n8n_workflows/
│   ├── healthtech_master_workflow.json
│   └── response_tracker.json
├── assets/
│   ├── css/style.css
│   └── js/app.js
└── README.md
```

---

## 🌐 API Endpoints

All API endpoints require `api_key: HTS2026Key` (except `stats.php`).

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/stats.php` | GET | Returns leads/sent/responses counts |
| `/api/save_lead.php` | POST | Bulk-save leads from n8n/Apollo |
| `/api/save_response.php` | POST | Save an inbox response |
| `/api/log_email.php` | POST | Log a sent email |
| `/api/get_template.php` | GET | Fetch default or specific template |
| `/api/get_leads_for_campaign.php` | GET | Get filtered leads (max 5000) |
| `/api/send_one_email.php` | POST | Send one email in a campaign |
| `/api/update_campaign.php` | POST | Update campaign record |
| `/api/poll_inbox.php` | GET/POST | Poll IMAP and save responses |
| `/api/download_sample_csv.php` | GET | Download CSV template |

---

## 🛠️ Troubleshooting

1. **Login fails** — Run the auto_installer or check `config/database.php` credentials.
2. **Emails not sending** — Verify SMTP credentials in `config/config.php`; check port 465 is open.
3. **IMAP not working** — Ensure PHP `imap` extension is enabled (`php -m | grep imap`).
4. **CSV import error** — Ensure the CSV has an `email` column and is UTF-8 encoded.
5. **n8n workflow not triggering** — Check n8n is running and the workflow is Activated (not just saved).

---

## 📄 License

MIT License — Free to use and modify for your own events and campaigns.
