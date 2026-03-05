# n8n Setup Guide — Canada FinTech Symposium 2026

This guide explains how to import and configure the five n8n workflow files located in `n8n_workflows/`.

---

## 1. Prerequisites

- n8n running locally (`npm install -g n8n` then `n8n start`) or via Docker
- n8n UI accessible at `http://localhost:5678`
- The PHP backend deployed at `https://fintech.softandpix.com` with a valid `.env` file (SMTP configured via Outlook)
- An Apollo.io account with an API key

---

## 2. API Key

All workflow JSON files use the placeholder `YOUR_N8N_API_KEY`. Before importing (or immediately after), replace every occurrence with the actual value set in your server's `.env`:

```
N8N_API_KEY=<your_secure_key>   # set a strong unique value for production
```

> **Note:** The `.env.example` ships with `HTS2026Key` as the default dev value. **Always change this to a strong, unique key before going live.**
>
> **Tip:** Use n8n's global "Find & Replace" or do a bulk search-replace in your text editor before importing.

---

## 3. Importing Workflows

1. Open n8n (`http://localhost:5678`).
2. Click **Workflows → New** (or the **+** button).
3. Click the ⋮ menu → **Import from File**.
4. Select the JSON file from `n8n_workflows/`.
5. Repeat for each of the five files below.

---

## 4. Credentials to Set Up in n8n

### 4.1 Apollo.io — HTTP Header Auth

Used by `healthtech_master_workflow.json` and `lead_collector.json`.

| Field | Value |
|-------|-------|
| **Name** | `Apollo API Key` |
| **Header Name** | `X-Api-Key` |
| **Header Value** | `<your Apollo.io API key>` |

1. In n8n go to **Credentials → New Credential → HTTP Header Auth**.
2. Fill in the values above and save.
3. Open the Apollo Search node in each workflow and select this credential.

### 4.2 SMTP — for alert emails (response_tracker & thursday_campaign)

The `response_tracker.json` and `thursday_campaign.json` still use n8n's built-in `emailSend` node for **admin notifications only**. You need to configure an SMTP credential in n8n for these:

| Field | Value |
|-------|-------|
| **Name** | `FinTech SMTP` |
| **Host** | `smtp.office365.com` |
| **Port** | `587` |
| **User** | `info@canadafintechsymposium.com` |
| **Password** | `<Outlook app password>` |
| **SSL/TLS** | STARTTLS |

1. In n8n go to **Credentials → New Credential → SMTP**.
2. Fill in the values above and save.
3. Open the `Send Alert Email` / `Email — Notify Admin` node in each workflow and select this credential.

> **Note:** Outbound campaign emails (master workflow & follow-up sender) are sent via `send_one_email.php` — no SMTP credential is needed in n8n for those.

---

## 5. Workflow Schedule Summary

| Workflow File | Schedule | Purpose |
|---|---|---|
| `healthtech_master_workflow.json` | Tue & Thu 9 AM | Fetch leads from Apollo, save, then send campaign emails via PHP API |
| `lead_collector.json` | Daily 8 AM | Import leads from Apollo into the database |
| `followup_sender.json` | Daily 10 AM | Send follow-up sequence 2 to eligible leads |
| `thursday_campaign.json` | Thu 9 AM | Loop-send campaign batch, update campaign record, notify admin |
| `response_tracker.json` | Every 10 min | Poll IMAP inbox, save responses, alert admin if new replies |

---

## 6. Activating Workflows

All workflows are imported with `"active": false` so they don't fire immediately.

1. Open each workflow in the n8n editor.
2. Replace `YOUR_N8N_API_KEY` with the real key (if not done already).
3. Assign the required credentials to their nodes.
4. Click **Save**.
5. Toggle the **Active** switch (top-right) to enable the schedule trigger.

---

## 7. Troubleshooting

| Problem | Solution |
|---------|----------|
| `401 Unauthorized` from PHP API | `YOUR_N8N_API_KEY` doesn't match the value in server `.env` |
| Apollo search returns 0 people | Check Apollo credential; verify the API key has People Search scope |
| Emails not sent | Verify `.env` SMTP settings on the server; check `api/send_one_email.php` logs |
| IMAP polling fails | Ensure PHP `imap` extension is enabled; check IMAP credentials in `.env` |
| Workflow triggers but nodes error | Open the execution log in n8n and inspect the failing node output |
| Workflow doesn't trigger on schedule | Confirm the workflow is **Active** (not just saved) in the n8n UI |

---

For further help, consult the [n8n documentation](https://docs.n8n.io/) or open an issue in this repository.
