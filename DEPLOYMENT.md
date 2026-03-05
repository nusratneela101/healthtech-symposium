# 🚀 Deployment Guide — Canada HealthTech Symposium 2026

This guide walks you through deploying the Canada HealthTech Symposium Campaign Management Platform on a cPanel shared hosting environment.

---

## Prerequisites

Before you begin, ensure you have:

- **PHP 8.0+** with the following extensions enabled: `pdo_mysql`, `imap`, `openssl`, `mbstring`
- **MySQL 5.7+** or MariaDB 10.3+
- **cPanel access** to your hosting account
- **A domain with SSL** (HTTPS required for OAuth and secure cookie sessions)
- (Optional) **n8n Cloud** or self-hosted n8n instance for workflow automation

---

## Step 1 — Upload Files to cPanel

1. Zip the entire project directory on your local machine.
2. Log in to **cPanel → File Manager**.
3. Navigate to `public_html/` (or create a subdomain and navigate to its document root).
4. Click **Upload** and upload the zip file.
5. Right-click the zip file and select **Extract**.
6. Ensure the extracted files are directly inside `public_html/` (or the subdomain root), not inside a nested subfolder.

---

## Step 2 — Create MySQL Database and User

1. In cPanel, go to **MySQL Databases**.
2. Create a new database (e.g., `youruser_hts2026`).
3. Create a new database user with a strong password.
4. Add the user to the database with **All Privileges**.
5. Note down the database name, username, and password for the next step.

---

## Step 3 — Configure Environment Variables

1. In File Manager, locate `.env.example` in the project root.
2. Make a copy and rename it to `.env`.
3. Open `.env` and fill in all values:

```env
APP_NAME="Canada HealthTech Symposium"
APP_URL="https://yourdomain.com"          # No trailing slash
APP_VERSION="2.0.0"

DB_HOST=localhost
DB_NAME=youruser_hts2026
DB_USER=youruser_dbuser
DB_PASS=your_strong_db_password

SMTP_HOST=smtp-mail.outlook.com           # Or smtp.brevo.com for Brevo
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=info@yourdomain.com
SMTP_PASS=your_smtp_password
SMTP_FROM_EMAIL=info@yourdomain.com
SMTP_FROM_NAME="Canada HealthTech Symposium"

IMAP_HOST="{outlook.office365.com:993/imap/ssl}INBOX"
IMAP_USER=info@yourdomain.com
IMAP_PASS=your_imap_password

BREVO_API_KEY=your_brevo_api_key          # Optional

MS_OAUTH_CLIENT_ID=your_azure_client_id   # Optional — for Graph API
MS_OAUTH_CLIENT_SECRET=your_azure_secret
MS_OAUTH_TENANT_ID=common
MS_OAUTH_REDIRECT_URI=https://yourdomain.com/api/msgraph/callback.php

N8N_API_KEY=your_secure_random_api_key    # Must match what you set in n8n workflows
SESSION_NAME=hts_session
```

> **Tip:** Generate a strong `N8N_API_KEY` with a password manager or `openssl rand -hex 32`.

---

## Step 4 — Run the Auto-Installer

1. Open your browser and navigate to:
   ```
   https://yourdomain.com/install/auto_installer.php
   ```
2. The installer will:
   - Test the database connection
   - Create all required tables (from `install/setup.sql`)
   - Seed default data (email templates, admin user)
3. When complete, click **Go to Dashboard** to verify the installation.

---

## Step 5 — Delete the Install Folder

**Important:** Remove the `install/` folder immediately after setup to prevent unauthorized re-installation.

In cPanel File Manager, right-click the `install/` folder and select **Delete**.

Alternatively, rename it:
```
install/ → _install_done/
```

---

## Step 6 — Configure Brevo SMTP (Recommended)

[Brevo](https://www.brevo.com/) provides reliable transactional email delivery with open/click tracking.

1. Sign up at [brevo.com](https://www.brevo.com/) (free tier available).
2. Go to **SMTP & API → API Keys** and create a new API key.
3. Go to **SMTP & API → SMTP** and note your SMTP credentials.
4. Update your `.env`:
   ```env
   SMTP_HOST=smtp-relay.brevo.com
   SMTP_PORT=587
   SMTP_SECURE=tls
   SMTP_USER=your_brevo_login_email
   SMTP_PASS=your_brevo_smtp_password
   BREVO_API_KEY=your_brevo_api_key
   ```
5. In Brevo, verify your sending domain (add the provided DNS records to your domain).

---

## Step 7 — Configure Apollo.io API

[Apollo.io](https://www.apollo.io/) is used for automated lead collection via the n8n `lead_collector.json` workflow.

1. Sign up at [apollo.io](https://www.apollo.io/).
2. Go to **Settings → Integrations → API** and generate an API key.
3. Add the API key to n8n credentials (see Step 8 below).
4. In Apollo, configure your search filters (Location: Canada, Industry: Health / Healthcare Technology).

---

## Step 8 — Set Up n8n Cloud Workflow

1. Sign up at [n8n.io](https://n8n.io/) (Cloud) or deploy self-hosted n8n.
2. Import each JSON file from `n8n_workflows/`:
   - Go to **Workflows → New → Import from File**
   - Repeat for each `.json` file
3. In each workflow, replace `YOUR_N8N_API_KEY` with the value of `N8N_API_KEY` from your `.env`.
4. Replace `YOURSITE.com` in all HTTP Request node URLs with your actual domain.
5. Create the required credentials in n8n:
   - **Apollo API Key** — HTTP Header Auth (`X-Api-Key: <your apollo key>`)
   - **HealthTech SMTP** — SMTP credential for `response_tracker.json` admin alerts
6. Activate the workflows you want to use (toggle the **Active** switch).

See **[docs/N8N_SETUP.md](docs/N8N_SETUP.md)** for the full n8n guide.

---

## Step 9 — Connect Microsoft 365 OAuth (Optional)

For sending emails via Microsoft Graph API (modern authentication without storing passwords):

1. Go to [Azure Portal](https://portal.azure.com/) → **Azure Active Directory → App registrations → New registration**.
2. Set the redirect URI to:
   ```
   https://yourdomain.com/api/msgraph/callback.php
   ```
3. Under **Certificates & secrets**, create a new client secret.
4. Under **API permissions**, add:
   - `Mail.Send` (Delegated)
   - `Mail.ReadWrite` (Delegated, for IMAP alternative)
5. Update your `.env` with the Client ID, Client Secret, and Tenant ID.
6. In the admin panel, go to **🔗 Microsoft 365** and click **Connect** to complete the OAuth flow.

---

## 🔒 Security Checklist

Before going live, verify each item:

- [ ] `install/` folder deleted or renamed
- [ ] `.env` file is not publicly accessible (add `deny from all` in `.htaccess` if needed)
- [ ] `N8N_API_KEY` is a strong, unique random string (not `HTS2026Key` or any default value)
- [ ] HTTPS (SSL) is active on your domain
- [ ] Database user has only the minimum required privileges (no `SUPER` or `FILE`)
- [ ] Default admin password has been changed after first login
- [ ] File permissions set to `644` for files, `755` for directories
- [ ] Error display disabled in production (`display_errors = Off` in `php.ini`)
- [ ] Brevo domain is verified (to avoid emails landing in spam)

---

## 🛠️ Troubleshooting

| Problem | Solution |
|---------|----------|
| Installer fails to connect to DB | Double-check DB host, name, user, and password in `.env` |
| 500 Internal Server Error | Check PHP error log in cPanel → **Errors**; ensure PHP 8.0+ is selected |
| Blank page after install | Ensure `APP_URL` in `.env` has no trailing slash and matches the actual URL |
| Emails not sending | Verify SMTP credentials; check that outbound port 587 is not blocked by your host |
| IMAP polling returns no results | Enable PHP `imap` extension in cPanel → **MultiPHP INI Editor** |
| n8n `401 Unauthorized` | `N8N_API_KEY` in n8n workflow doesn't match the value in server `.env` |
| Campaign stuck in `running` | Manually reset via database: `UPDATE campaigns SET status='draft' WHERE status='running'` |
| OAuth redirect mismatch | The redirect URI in Azure App Registration must exactly match `MS_OAUTH_REDIRECT_URI` in `.env` |
| CSS/JS not loading | Confirm `APP_URL` is correct and files are accessible at `assets/css/style.css` |

---

For further assistance, consult the [n8n documentation](https://docs.n8n.io/), the [Brevo documentation](https://developers.brevo.com/), or open an issue in this repository.
