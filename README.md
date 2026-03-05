# 💼 Canada Fintech Symposium 2026 — Campaign Management Platform

A complete, production-ready PHP web application for managing the **Canada Fintech Symposium 2026** — including lead management, automated email campaigns, IMAP inbox polling, response tracking, and n8n workflow automation.

---

## 📋 Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8+ with PDO |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Workflow Automation | n8n |
| Lead Enrichment | Apollo.io API |
| Email Delivery | Brevo SMTP |
| Email OAuth | Microsoft Graph API (Microsoft 365) |
| Frontend | Vanilla JS, ApexCharts |

---

## ✨ Features

- 📊 **Dashboard** — Real-time stats: total leads, emails sent, response rate, conversion funnel
- 👥 **Lead Management** — Browse, search, filter, bulk-update, and export leads by segment, status, and province
- 📥 **CSV Import** — Bulk-import leads from Apollo.io exports; manual single-lead entry
- 🚀 **Auto Campaign** — Create and launch email campaigns with segment/province/role filters
- 📅 **Scheduled Campaigns** — Schedule campaigns for a future date/time; n8n fires them automatically
- 💬 **Response Tracking** — IMAP inbox polling captures replies and classifies them (interested, bounce, etc.)
- 📋 **Audit Reports** — Full audit log of all admin actions with IP address and timestamps
- 👤 **User Management** — Super Admin and regular admin roles; per-user access control
- 🔗 **OAuth Integration** — Microsoft 365 OAuth2 / Graph API for modern authenticated email sending
- ✉️ **Email Templates** — Reusable HTML email templates with variable substitution
- 🔔 **Notifications** — In-app notification bell with unread badge

---

## 🚀 Quick Start

### 1. Upload Files
Upload all files to your server's `public_html/` or a subdomain directory.

### 2. Run the Auto-Installer
Open `https://yourdomain.com/install/auto_installer.php` in your browser, enter your database credentials and app URL, then click **Install Now**.

### 3. Configure Environment
Copy `.env.example` to `.env` and fill in all values (database, SMTP, API keys).

### 4. Secure the Installation
Delete or rename the `install/` folder after setup is complete.

### 5. Log In
Visit `https://yourdomain.com/login.php` and use the default Super Admin credentials created during installation.

> 📖 See **[DEPLOYMENT.md](DEPLOYMENT.md)** for full step-by-step deployment instructions including Brevo, Apollo.io, n8n, and Microsoft 365 OAuth setup.

---

## 🤖 n8n Workflow Automation

Five n8n workflow files are included in `n8n_workflows/`. See **[docs/N8N_SETUP.md](docs/N8N_SETUP.md)** for the complete setup guide, including:

- How to import each workflow
- Which credentials to create (Apollo API key, SMTP)
- Replacing `YOUR_N8N_API_KEY` with your actual key
- Workflow schedule summary
- How to activate workflows
- Troubleshooting tips

---

## 📁 Project Structure

```
fintech-symposium/
├── index.php                   # Redirects to dashboard
├── login.php                   # Login page
├── logout.php                  # Session logout
├── unsubscribe.php             # Public unsubscribe handler
├── .env.example                # Environment variable template
├── config/
│   ├── config.php              # Loads .env, defines constants
│   └── database.php            # PDO database wrapper
├── includes/
│   ├── auth.php                # Session-based authentication
│   ├── functions.php           # Helpers: pill(), timeAgo(), paginate(), audit_log()
│   ├── layout.php              # Shared sidebar + topbar (included at top of each page)
│   ├── layout_end.php          # Closing HTML tags + JS init
│   ├── email.php               # PHPMailer / Brevo email sending
│   └── imap.php                # IMAP inbox polling
├── admin/
│   ├── dashboard.php           # Stats overview
│   ├── leads.php               # Lead database with filters and bulk actions
│   ├── import_leads.php        # CSV import and manual lead entry
│   ├── auto_campaign.php       # Create and launch campaigns
│   ├── schedule_campaign.php   # Schedule campaigns for future sending
│   ├── campaign.php            # Campaign list and details
│   ├── audit.php               # Audit log report
│   ├── responses.php           # Inbox responses
│   ├── templates.php           # Email template editor
│   ├── users.php               # User management
│   ├── oauth_connect.php       # Microsoft 365 OAuth connection
│   ├── export.php              # Data export
│   └── settings.php            # App settings
├── api/
│   ├── stats.php               # GET — lead/send/response counts
│   ├── save_lead.php           # POST — bulk-save leads from n8n/Apollo
│   ├── save_response.php       # POST — save an inbox reply
│   ├── log_email.php           # POST — log a sent email
│   ├── get_template.php        # GET — fetch email template
│   ├── get_leads_for_campaign.php  # GET — filtered leads (max 5000)
│   ├── send_one_email.php      # POST — send one email in a campaign
│   ├── update_campaign.php     # POST — update campaign record
│   ├── poll_inbox.php          # GET/POST — poll IMAP inbox
│   ├── bulk_leads.php          # POST — bulk lead actions
│   └── download_sample_csv.php # GET — download sample CSV template
├── install/
│   ├── setup.sql               # Database schema and seed data
│   └── auto_installer.php      # Web-based installer
├── n8n_workflows/
│   ├── fintech_master_workflow.json  # Polls every 5 min → sends scheduled campaigns
│   ├── lead_collector.json             # Daily 8 AM — imports leads from Apollo
│   ├── followup_sender.json            # Daily 10 AM — sends follow-up sequence 2
│   ├── response_tracker.json           # Every 10 min — polls IMAP inbox
│   └── thursday_campaign.json          # (DEPRECATED) superseded by master workflow
├── docs/
│   └── N8N_SETUP.md            # n8n workflow setup guide
├── assets/
│   ├── css/style.css           # Application stylesheet
│   └── js/app.js               # Shared JavaScript utilities
├── README.md
└── DEPLOYMENT.md               # Full deployment instructions
```

---

## 🌐 API Endpoints

All API endpoints require the `api_key` header/param matching `N8N_API_KEY` from `.env` (except `stats.php`).

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

1. **Login fails** — Run the auto-installer or check `config/database.php` credentials.
2. **Emails not sending** — Verify SMTP credentials in `.env`; check that port 465/587 is open on your host.
3. **IMAP not working** — Ensure PHP `imap` extension is enabled (`php -m | grep imap`).
4. **CSV import error** — Ensure the CSV has an `email` column and is UTF-8 encoded.
5. **n8n workflow not triggering** — Check n8n is running and the workflow is **Activated** (not just saved).

---

## 📄 License

MIT License — Free to use and modify for your own events and campaigns.

