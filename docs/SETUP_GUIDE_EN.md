# HealthTech Symposium — All-in-One Setup Guide (English)

> **This is the complete, all-in-one setup guide for the HealthTech Symposium Campaign Management Platform.**
> It covers every step from uploading files to cPanel through to your first login and running automated workflows.
> Follow the sections in order for the smoothest experience.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Upload Files to cPanel](#2-upload-files-to-cpanel)
3. [Create MySQL Database in cPanel](#3-create-mysql-database-in-cpanel)
4. [Run the Auto-Installer](#4-run-the-auto-installer)
5. [Configure the `.env` File](#5-configure-the-env-file)
6. [Set Up Brevo (Email Delivery)](#6-set-up-brevo-email-delivery)
7. [Set Up Apollo.io (Lead Collection)](#7-set-up-apolloio-lead-collection)
8. [Set Up n8n (Workflow Automation)](#8-set-up-n8n-workflow-automation)
9. [Admin Panel Walkthrough](#9-admin-panel-walkthrough)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Prerequisites

Before you start, make sure you have the following:

| Requirement | Details |
|-------------|---------|
| **PHP 8.0+** | Extensions required: `pdo_mysql`, `imap`, `openssl`, `mbstring` |
| **MySQL 5.7+** or MariaDB 10.3+ | Provided by most cPanel hosts |
| **cPanel hosting account** | With File Manager and MySQL Databases access |
| **A domain with SSL (HTTPS)** | Required for secure cookies and OAuth |
| **Brevo account** | [brevo.com](https://www.brevo.com/) — free tier is sufficient |
| **Apollo.io account** | [apollo.io](https://www.apollo.io/) — for automated lead collection |
| **n8n account** | [n8n.io](https://n8n.io/) Cloud or a self-hosted instance |

> **Tip:** You can enable PHP extensions in cPanel → **MultiPHP INI Editor** → select your PHP version → check the required extensions.

---

## 2. Upload Files to cPanel

1. On your local machine, create a `.zip` archive of the entire project folder.
2. Log in to **cPanel**.
3. Open **File Manager** and navigate to `public_html/` (or the root folder of your subdomain if you are deploying to a subdomain).
4. Click **Upload** and upload the `.zip` file.
5. Once the upload is complete, right-click the `.zip` file and select **Extract**.
6. Verify that all project files (e.g., `index.php`, `login.php`, `admin/`, `api/`, `n8n_workflows/`, etc.) are **directly** inside `public_html/` — not inside a nested subfolder.

> **Important:** If the files end up inside `public_html/healthtech-symposium/` rather than `public_html/`, move them up one level using File Manager's drag-and-drop or the **Move** option.

---

## 3. Create MySQL Database in cPanel

1. In cPanel, go to **MySQL Databases**.
2. Under **Create New Database**, enter a name (e.g., `youruser_hts2026`) and click **Create Database**.
3. Under **MySQL Users → Add New User**, create a database user with a strong password. Note down the username and password.
4. Under **Add User to Database**, select the user and database you just created and click **Add**.
5. In the privileges dialog, select **All Privileges** and click **Make Changes**.
6. Note down all three values — you will need them in the next steps:
   - Database name (e.g., `youruser_hts2026`)
   - Database username (e.g., `youruser_dbuser`)
   - Database password

---

## 4. Run the Auto-Installer

The project includes a browser-based installer that creates all database tables and seeds default data automatically.

1. Open your browser and navigate to:
   ```
   https://yourdomain.com/install/auto_installer.php
   ```
2. Fill in the form fields:
   - **DB Host** — usually `localhost`
   - **DB Name** — the database name from Step 3
   - **DB User** — the database username from Step 3
   - **DB Password** — the database password from Step 3
   - **App URL** — your full domain URL, e.g. `https://yourdomain.com` (no trailing slash)
   - **Super Admin Email / Password** — the credentials you will use to log in
   - **Admin Email / Password** — a secondary admin account (can be the same as Super Admin during testing)
3. Click **Install Now**.
4. The installer will:
   - Test the database connection
   - Create all required tables (from `install/setup.sql`)
   - Seed default data including email templates and the admin user
5. When complete, click **Go to Dashboard**.

> **After installation:** Delete or rename the `install/` folder immediately to prevent unauthorized re-installation.
> In File Manager, right-click the `install/` folder and choose **Delete**, or rename it to `_install_done/`.

---

## 5. Configure the `.env` File

The `.env` file holds all sensitive configuration values (database credentials, API keys, SMTP settings). It is never committed to version control.

### Steps

1. In cPanel File Manager, navigate to your project root.
2. Find the file `.env.example`, right-click it, and select **Copy**.
3. Name the copy `.env`.
4. Right-click `.env` and select **Edit**.
5. Fill in all values as described below.

### Full `.env` Template

```env
DB_HOST=localhost
DB_NAME=your_db_name
DB_USER=your_db_user
DB_PASS=your_db_password

APP_URL=https://yourdomain.com/fintech

SMTP_HOST=smtp-relay.brevo.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=your_brevo_login_email
SMTP_PASS=your_brevo_smtp_key
SMTP_FROM_EMAIL=info@yourdomain.com
SMTP_FROM_NAME=HealthTech Symposium

IMAP_HOST=imap.yourdomain.com
IMAP_PORT=993
IMAP_USER=info@yourdomain.com
IMAP_PASS=your_imap_password

BREVO_API_KEY=your_brevo_api_key

N8N_API_KEY=your_secure_random_key

SESSION_NAME=hts_session
```

### Field Descriptions

| Variable | Description |
|----------|-------------|
| `DB_HOST` | Usually `localhost` on cPanel hosting |
| `DB_NAME` | Database name created in Step 3 |
| `DB_USER` | Database username created in Step 3 |
| `DB_PASS` | Database password created in Step 3 |
| `APP_URL` | Full URL where the app is deployed (no trailing slash) |
| `SMTP_HOST` | Brevo SMTP host: `smtp-relay.brevo.com` |
| `SMTP_PORT` | `587` (STARTTLS) |
| `SMTP_SECURE` | `tls` |
| `SMTP_USER` | Your Brevo account login email |
| `SMTP_PASS` | Your Brevo SMTP key (not your Brevo account password) |
| `SMTP_FROM_EMAIL` | The "From" email address for outgoing emails |
| `SMTP_FROM_NAME` | The "From" display name |
| `IMAP_HOST` | IMAP server hostname for your inbox (e.g., `imap.yourdomain.com`) |
| `IMAP_PORT` | Usually `993` (IMAP over SSL) |
| `IMAP_USER` | Email address used for IMAP polling |
| `IMAP_PASS` | Password for the IMAP account |
| `BREVO_API_KEY` | Your Brevo v3 API key (for transactional email tracking) |
| `N8N_API_KEY` | A strong, unique secret key — **must match** the value used in all n8n workflows |
| `SESSION_NAME` | PHP session cookie name — leave as `hts_session` unless you have a reason to change it |

> **Security tip:** Generate a strong `N8N_API_KEY` using:
> ```
> openssl rand -hex 32
> ```

---

## 6. Set Up Brevo (Email Delivery)

[Brevo](https://www.brevo.com/) (formerly Sendinblue) provides reliable transactional email delivery with open and click tracking.

### Steps

1. Sign up at [brevo.com](https://www.brevo.com/) (free tier available — no credit card required).
2. After logging in, go to **SMTP & API → API Keys** and create a new API key. Copy and save it.
3. Go to **SMTP & API → SMTP** and note your login (email address) and the SMTP key.
4. Update your `.env` with these values:
   ```env
   SMTP_HOST=smtp-relay.brevo.com
   SMTP_PORT=587
   SMTP_SECURE=tls
   SMTP_USER=your_brevo_login_email
   SMTP_PASS=your_brevo_smtp_key
   BREVO_API_KEY=your_brevo_api_key
   ```
5. In Brevo, go to **Senders & Domains → Domains** and verify your sending domain by adding the DNS records Brevo provides. Verifying your domain significantly reduces spam filtering.

> **Note:** The `SMTP_PASS` is the Brevo **SMTP key**, not your Brevo account login password. You can find the SMTP key under **SMTP & API → SMTP**.

---

## 7. Set Up Apollo.io (Lead Collection)

[Apollo.io](https://www.apollo.io/) is used to automatically import leads into the platform via the `lead_collector.json` n8n workflow.

### Steps

1. Sign up at [apollo.io](https://www.apollo.io/).
2. Go to **Settings → Integrations → API** and generate an API key. Copy and save it.
3. In Apollo, set up your prospecting filters:
   - **Location:** Canada
   - **Industry:** Health / Healthcare Technology
   - Adjust title and company size filters as needed.
4. You will add the Apollo API key to n8n credentials in the next section.

---

## 8. Set Up n8n (Workflow Automation)

n8n automates lead collection, campaign sending, follow-up emails, and inbox polling. Five workflow JSON files are provided in the `n8n_workflows/` folder.

### Workflow Files Overview

| File | Schedule | Purpose |
|------|----------|---------|
| `fintech_master_workflow.json` | Every 5 min | Polls for due scheduled campaigns → sends emails via `api/send_one_email.php` |
| `lead_collector.json` | Daily 8 AM | Imports leads from Apollo.io via `api/save_lead.php` |
| `followup_sender.json` | Daily 10 AM | Sends follow-up sequence 2 emails to eligible leads |
| `response_tracker.json` | Every 10 min | Polls IMAP inbox for replies via `api/poll_inbox.php` |
| `thursday_campaign.json` | *(DEPRECATED)* | Superseded by the master workflow + scheduling system — keep inactive |

---

### 8.1 Install n8n

**Option A — n8n Cloud (recommended for beginners)**

1. Sign up at [n8n.io](https://n8n.io/) and create a Cloud workspace.
2. Your n8n instance URL will look like: `https://yourname.app.n8n.cloud`

**Option B — Self-hosted with npm**

```bash
npm install -g n8n
n8n start
```

The UI will be available at `http://localhost:5678`.

**Option C — Docker**

```bash
docker run -it --rm \
  --name n8n \
  -p 5678:5678 \
  -v ~/.n8n:/home/node/.n8n \
  n8nio/n8n
```

---

### 8.2 Import Workflow JSON Files

1. Log in to your n8n instance.
2. Click **Workflows** in the left sidebar.
3. Click **+ New Workflow** (or the **+** button).
4. Click the **⋮** (three-dot) menu in the top-right corner and select **Import from File**.
5. Browse to the `n8n_workflows/` folder in your project and select `fintech_master_workflow.json`.
6. Repeat steps 2–5 for each of the remaining `.json` files:
   - `lead_collector.json`
   - `followup_sender.json`
   - `response_tracker.json`
   - `thursday_campaign.json` (import but **leave inactive**)

> **All workflows are imported with `"active": false`** — they will not run until you manually activate them in Step 8.5.

---

### 8.3 Configure Credentials

You need to create two credentials in n8n before activating the workflows.

#### Apollo.io — HTTP Header Auth

Used by `lead_collector.json` to authenticate calls to the Apollo API.

1. In n8n, go to **Credentials** → **+ Add Credential**.
2. Search for and select **HTTP Header Auth**.
3. Fill in the fields:

   | Field | Value |
   |-------|-------|
   | **Name** | `Apollo API Key` |
   | **Header Name** | `X-Api-Key` |
   | **Header Value** | `<your Apollo.io API key>` |

4. Click **Save**.
5. Open the `lead_collector` workflow, click the Apollo Search node, and assign this credential.

#### Brevo SMTP — for admin alert emails

Used by `response_tracker.json` to send admin notification emails when new replies arrive.

1. In n8n, go to **Credentials** → **+ Add Credential**.
2. Search for and select **SMTP**.
3. Fill in the fields:

   | Field | Value |
   |-------|-------|
   | **Name** | `Fintech SMTP` |
   | **Host** | `smtp-relay.brevo.com` |
   | **Port** | `587` |
   | **User** | Your Brevo login email |
   | **Password** | Your Brevo SMTP key |
   | **SSL/TLS** | STARTTLS |

4. Click **Save**.
5. Open the `response_tracker` workflow, click the **Send Alert Email** node, and assign this credential.

> **Note:** Outbound campaign emails are sent directly by the PHP backend via `api/send_one_email.php` — no SMTP credential is needed in n8n for those.

---

### 8.4 Replace Placeholders in Workflows

Each workflow JSON uses two placeholders that you must replace with real values:

| Placeholder | Replace With |
|-------------|-------------|
| `YOUR_N8N_API_KEY` | The value of `N8N_API_KEY` from your `.env` file |
| `YOURSITE.com` | Your actual domain, e.g. `yourdomain.com` |

**How to replace:**

1. Open each workflow in the n8n editor.
2. Click on each **HTTP Request** node that calls your PHP API.
3. In the node's URL field, replace `YOURSITE.com` with your actual domain.
4. In the **Headers** section (or query parameters), replace `YOUR_N8N_API_KEY` with the real key from your `.env`.

> **Tip:** Use your text editor's **Find & Replace** feature on the raw JSON files before importing them to save time. Replace all occurrences of `YOUR_N8N_API_KEY` and `YOURSITE.com` in the file, then import the modified file into n8n.

---

### 8.5 Activate Workflows

1. Open each workflow in the n8n editor.
2. Confirm all placeholders have been replaced and credentials are assigned.
3. Click **Save**.
4. Toggle the **Active** switch in the top-right corner of the editor to **On**.
5. The workflow status indicator will turn green, confirming it is active.

**Recommended activation order:**

1. `lead_collector.json` — start collecting leads
2. `fintech_master_workflow.json` — enable campaign sending
3. `followup_sender.json` — enable follow-up automation
4. `response_tracker.json` — enable inbox polling
5. `thursday_campaign.json` — **do not activate** (deprecated)

---

## 9. Admin Panel Walkthrough

After completing setup, log in at `https://yourdomain.com/login.php` with your Super Admin credentials.

### Dashboard

**URL:** `admin/dashboard.php`

The dashboard displays real-time statistics:
- **Total Leads** — number of leads in the database
- **Emails Sent** — total emails sent across all campaigns
- **Response Rate** — percentage of leads who replied
- **Conversion Funnel** — visual breakdown of lead stages

Charts update automatically using the `/api/stats.php` endpoint.

---

### Lead Management

**URL:** `admin/leads.php`

- Browse, search, and filter leads by segment, status, and province
- Bulk-update lead status (e.g., mark as interested, do-not-contact)
- Export leads to CSV
- Import leads manually via `admin/import_leads.php` or via CSV bulk upload

---

### Auto Campaign

**URL:** `admin/auto_campaign.php`

- Create a new email campaign by selecting:
  - An email template
  - Lead segment and province filters
  - Campaign name and subject line
- Preview the audience size before launching
- Save as draft or immediately trigger sending

---

### Schedule Campaign

**URL:** `admin/schedule_campaign.php`

**How the scheduling system works:**

1. Super Admin creates a campaign in Auto Campaign (saved as `draft`).
2. Super Admin opens Schedule Campaign and selects the draft campaign.
3. Super Admin picks a send date and time using the datetime picker.
4. Click **📅 Schedule Campaign** — the campaign status changes to `scheduled`.
5. n8n's `fintech_master_workflow.json` polls `api/get_scheduled_campaigns.php` every 5 minutes.
6. When the scheduled time arrives, n8n loops through `api/send_one_email.php` to send all emails.
7. The campaign is automatically marked `complete` when all emails are sent.

To cancel a scheduled campaign, click **✕ Cancel** next to it in the scheduled list.

---

### Response Tracking

**URL:** `admin/responses.php`

- Displays all inbox replies captured by the `response_tracker.json` workflow
- Each response is classified (e.g., interested, bounce, unsubscribe, general reply)
- Use this to identify hot leads and prioritize follow-up

---

### Settings

**URL:** `admin/settings.php`

Configure the following from the admin panel:

| Setting | Description |
|---------|-------------|
| **SMTP Settings** | Update SMTP host, port, credentials |
| **IMAP Settings** | Update IMAP host, port, credentials for inbox polling |
| **API Keys** | View/update Brevo API key, Apollo API key |
| **N8N API Key** | The key that must match `N8N_API_KEY` in `.env` |
| **Email Templates** | Manage reusable HTML email templates |

> **Important:** If you update `N8N_API_KEY` in `.env`, you must also update the same value in every n8n workflow that calls your API endpoints.

---

## 10. Troubleshooting

| Problem | Solution |
|---------|----------|
| **Installer fails to connect to DB** | Double-check `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in `.env` |
| **500 Internal Server Error** | Check PHP error log in cPanel → **Errors**; ensure PHP 8.0+ is selected in MultiPHP |
| **Blank page after install** | Ensure `APP_URL` in `.env` has no trailing slash and matches the actual URL |
| **Emails not sending** | Verify SMTP credentials in `.env`; confirm port 587 is not blocked by your host |
| **IMAP polling returns no results** | Enable PHP `imap` extension in cPanel → **MultiPHP INI Editor** |
| **n8n `401 Unauthorized`** | `N8N_API_KEY` in n8n workflow doesn't match the value in server `.env` |
| **No campaigns picked up by n8n** | Confirm campaign status is `scheduled` and `scheduled_at` is in the past |
| **Campaign stuck in `running`** | Reset manually: `UPDATE campaigns SET status='draft' WHERE status='running'` |
| **Apollo search returns 0 people** | Check Apollo credential in n8n; verify the API key has People Search scope |
| **CSS/JS not loading** | Confirm `APP_URL` is correct and files are accessible at `assets/css/style.css` |
| **Workflow triggers but nodes error** | Open the execution log in n8n and inspect the failing node output |
| **Workflow doesn't trigger on schedule** | Confirm the workflow is **Active** (toggle is green) — being saved is not enough |

---

For further assistance, consult the [n8n documentation](https://docs.n8n.io/), the [Brevo documentation](https://developers.brevo.com/), or open an issue in this repository.
