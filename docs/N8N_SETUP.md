# n8n Setup Guide — Canada Fintech Symposium 2026

This guide explains how to import and configure the n8n workflow files located in `n8n_workflows/`.

---

## 1. Prerequisites

- n8n running locally (`npm install -g n8n` then `n8n start`) or via Docker
- n8n UI accessible at `http://localhost:5678`
- The PHP backend deployed at `https://YOURSITE.com` with a valid `.env` file (SMTP configured via Outlook)
- An Apollo.io account with an API key

---

## 2. How the New Scheduling System Works

Campaign sending is now **fully controlled by the Super Admin**:

1. **Super Admin creates a campaign** in [Auto Campaign](/admin/auto_campaign.php)
2. **Super Admin schedules it** in [Schedule Campaign](/admin/schedule_campaign.php) — picks any date & time
3. **n8n polls every 5 minutes** via `fintech_master_workflow.json` — calls `api/get_scheduled_campaigns.php`
4. **When the scheduled time arrives**, n8n loops through `send_one_email.php` until all emails are sent
5. **Campaign is marked complete** automatically

Everything else runs on autopilot:

| Automation | Schedule | Description |
|---|---|---|
| **Lead Collection** | Daily 8 AM | n8n calls Apollo API → saves new leads via `api/save_lead.php` |
| **Response Tracking** | Every 10 min | n8n polls IMAP inbox → saves replies via `api/poll_inbox.php` |
| **Follow-ups** | Daily 10 AM | n8n sends sequence 2 emails to eligible leads |
| **Campaign Sending** | Super Admin sets date | n8n checks every 5 min and fires when it's time |

---

## 3. API Key

All workflow JSON files use the placeholder `YOUR_N8N_API_KEY`. Before importing (or immediately after), replace every occurrence with the actual value set in your server's `.env`:

```
N8N_API_KEY=<your_secure_key>   # set a strong unique value for production
```

> **Note:** The `.env.example` ships with `HTS2026Key` as the default dev value. **Always change this to a strong, unique key before going live.**
>
> **Tip:** Use n8n's global "Find & Replace" or do a bulk search-replace in your text editor before importing.

---

## 4. Importing Workflows

1. Open n8n (`http://localhost:5678`).
2. Click **Workflows → New** (or the **+** button).
3. Click the ⋮ menu → **Import from File**.
4. Select the JSON file from `n8n_workflows/`.
5. Repeat for each of the files below.

### Workflow Files

| File | Purpose | Active By Default |
|---|---|---|
| `fintech_master_workflow.json` | Polls every 5 min for due scheduled campaigns → sends emails | No |
| `lead_collector.json` | Daily 8 AM — imports leads from Apollo | No |
| `followup_sender.json` | Daily 10 AM — sends follow-up sequence 2 | No |
| `response_tracker.json` | Every 10 min — polls IMAP inbox for replies | No |
| `thursday_campaign.json` | **DEPRECATED** — superseded by master workflow + scheduling system | No |

---

## 5. Credentials to Set Up in n8n

### 5.1 Apollo.io — HTTP Header Auth

Used by `lead_collector.json`.

| Field | Value |
|-------|-------|
| **Name** | `Apollo API Key` |
| **Header Name** | `X-Api-Key` |
| **Header Value** | `<your Apollo.io API key>` |

1. In n8n go to **Credentials → New Credential → HTTP Header Auth**.
2. Fill in the values above and save.
3. Open the Apollo Search node in the lead collector workflow and select this credential.

### 5.2 SMTP — for alert emails (response_tracker)

The `response_tracker.json` uses n8n's built-in `emailSend` node for **admin notifications only**.

| Field | Value |
|-------|-------|
| **Name** | `Fintech SMTP` |
| **Host** | `smtp.office365.com` |
| **Port** | `587` |
| **User** | `info@canadafintechsymposium.com` |
| **Password** | `<Outlook app password>` |
| **SSL/TLS** | STARTTLS |

1. In n8n go to **Credentials → New Credential → SMTP**.
2. Fill in the values above and save.
3. Open the `Send Alert Email` node in `response_tracker.json` and select this credential.

> **Note:** Outbound campaign emails are sent via `send_one_email.php` — no SMTP credential is needed in n8n for those.

---

## 6. Step-by-Step: Scheduling Your First Campaign

1. **Log in as Super Admin** to the dashboard
2. Go to **🚀 Auto Campaign** — create a new campaign (select template + lead filters)
3. Go to **📅 Schedule** in the sidebar
4. Select your draft campaign from the dropdown
5. Pick a send date & time using the datetime picker
6. Click **📅 Schedule Campaign**
7. The campaign status changes to `scheduled`
8. n8n will pick it up automatically within 5 minutes of the scheduled time

To cancel a scheduled campaign, click **✕ Cancel** next to it in the scheduled list.

---

## 7. Workflow Schedule Summary

| Workflow File | Schedule | Purpose |
|---|---|---|
| `fintech_master_workflow.json` | Every 5 min | Poll for due campaigns → send emails via PHP API |
| `lead_collector.json` | Daily 8 AM | Import leads from Apollo into the database |
| `followup_sender.json` | Daily 10 AM | Send follow-up sequence 2 to eligible leads |
| `response_tracker.json` | Every 10 min | Poll IMAP inbox, save responses, alert admin if new replies |
| `thursday_campaign.json` | *(DEPRECATED)* | Superseded by master workflow + scheduling system; keep inactive |

---

## 8. Activating Workflows

All workflows are imported with `"active": false` so they don't fire immediately.

1. Open each workflow in the n8n editor.
2. Replace `YOUR_N8N_API_KEY` with the real key (if not done already).
3. Assign the required credentials to their nodes.
4. Click **Save**.
5. Toggle the **Active** switch (top-right) to enable the schedule trigger.

---

## 9. Troubleshooting

| Problem | Solution |
|---------|----------|
| `401 Unauthorized` from PHP API | `YOUR_N8N_API_KEY` doesn't match the value in server `.env` |
| No campaigns picked up | Confirm campaign status is `scheduled` and `scheduled_at` is in the past |
| Apollo search returns 0 people | Check Apollo credential; verify the API key has People Search scope |
| Emails not sent | Verify `.env` SMTP settings on the server; check `api/send_one_email.php` logs |
| IMAP polling fails | Ensure PHP `imap` extension is enabled; check IMAP credentials in `.env` |
| Workflow triggers but nodes error | Open the execution log in n8n and inspect the failing node output |
| Workflow doesn't trigger on schedule | Confirm the workflow is **Active** (not just saved) in the n8n UI |

---

For further help, consult the [n8n documentation](https://docs.n8n.io/) or open an issue in this repository.
