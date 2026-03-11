-- Migration: Add attachments_json column to email_templates
-- Run once on the server: mysql -u user -p dbname < db/migrate_attachments.sql

ALTER TABLE email_templates
    ADD COLUMN IF NOT EXISTS attachments_json LONGTEXT NULL AFTER attachment_path;
