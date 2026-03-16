-- One-time fix: set blank/NULL status to 'sent' for rows that have a message_id,
-- meaning the email was dispatched but the status was never saved correctly.
UPDATE email_logs
SET status = 'sent'
WHERE (status = '' OR status IS NULL)
  AND message_id IS NOT NULL
  AND message_id != '';
