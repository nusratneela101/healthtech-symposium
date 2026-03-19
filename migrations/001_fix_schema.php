<?php
/**
 * Migration 001 — Fix schema for existing installations.
 *
 * Adds all columns and tables that may be missing from databases created
 * before these columns/tables were added to the canonical schema.
 *
 * This file is executed automatically by includes/migrate.php when the
 * stored schema_version in site_settings is less than 1.
 *
 * All statements are idempotent (IF NOT EXISTS / IF NOT EXISTS checks).
 */

$migrations = [

    // ------------------------------------------------------------------
    // campaigns table — columns required by auto_campaign.php INSERT
    // ------------------------------------------------------------------
    [
        'name' => 'campaigns.campaign_key',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `campaign_key` VARCHAR(100) DEFAULT NULL",
    ],
    [
        'name' => 'campaigns.filter_segment',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `filter_segment` VARCHAR(100) DEFAULT NULL",
    ],
    [
        'name' => 'campaigns.filter_role',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `filter_role` VARCHAR(100) DEFAULT NULL",
    ],
    [
        'name' => 'campaigns.filter_province',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `filter_province` VARCHAR(100) DEFAULT NULL",
    ],
    [
        'name' => 'campaigns.total_leads',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `total_leads` INT DEFAULT 0",
    ],
    [
        'name' => 'campaigns.sent_count',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `sent_count` INT DEFAULT 0",
    ],
    [
        'name' => 'campaigns.failed_count',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `failed_count` INT DEFAULT 0",
    ],
    [
        'name' => 'campaigns.test_mode',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `test_mode` TINYINT DEFAULT 0",
    ],
    [
        'name' => 'campaigns.target_mode',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `target_mode` VARCHAR(10) DEFAULT 'all'",
    ],
    [
        'name' => 'campaigns.target_count',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `target_count` INT DEFAULT 0",
    ],
    [
        'name' => 'campaigns.completed_at',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `completed_at` DATETIME DEFAULT NULL",
    ],
    [
        'name' => 'campaigns.created_by',
        'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `created_by` INT DEFAULT 0",
    ],

    // ------------------------------------------------------------------
    // email_logs table — columns used by campaign_sender.php INSERT
    // ------------------------------------------------------------------
    [
        'name' => 'email_logs.recipient_email',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `recipient_email` VARCHAR(255) DEFAULT NULL",
    ],
    [
        'name' => 'email_logs.recipient_name',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `recipient_name` VARCHAR(255) DEFAULT NULL",
    ],
    [
        'name' => 'email_logs.follow_up_sequence',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `follow_up_sequence` INT DEFAULT 1",
    ],
    [
        'name' => 'email_logs.opened_at',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `opened_at` DATETIME DEFAULT NULL",
    ],
    [
        'name' => 'email_logs.message_id',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `message_id` VARCHAR(255) DEFAULT NULL",
    ],
    [
        'name' => 'email_logs.error_message',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `error_message` TEXT DEFAULT NULL",
    ],
    [
        'name' => 'email_logs.sent_at',
        'sql'  => "ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `sent_at` DATETIME DEFAULT NULL",
    ],

    // ------------------------------------------------------------------
    // Fix email_logs rows with empty/NULL status that were actually sent
    // ------------------------------------------------------------------
    [
        'name' => 'email_logs.fix_blank_status_by_message_id',
        'sql'  => "UPDATE `email_logs` SET `status` = 'sent'
                   WHERE (status = '' OR status IS NULL)
                     AND message_id IS NOT NULL AND message_id != ''",
    ],
    [
        'name' => 'email_logs.fix_blank_status_by_sent_at',
        'sql'  => "UPDATE `email_logs` SET `status` = 'sent'
                   WHERE (status = '' OR status IS NULL)
                     AND sent_at IS NOT NULL",
    ],

    // ------------------------------------------------------------------
    // Create missing tables
    // ------------------------------------------------------------------
    [
        'name' => 'lead_collections',
        'sql'  => "CREATE TABLE IF NOT EXISTS `lead_collections` (
          `id`               INT AUTO_INCREMENT PRIMARY KEY,
          `source`           VARCHAR(100) DEFAULT 'Apollo',
          `total_fetched`    INT DEFAULT 0,
          `total_saved`      INT DEFAULT 0,
          `total_skipped`    INT DEFAULT 0,
          `total_duplicates` INT DEFAULT 0,
          `status`           VARCHAR(50) DEFAULT 'pending',
          `search_params`    TEXT,
          `started_at`       DATETIME DEFAULT NULL,
          `completed_at`     DATETIME DEFAULT NULL,
          `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
          KEY `status` (`status`),
          KEY `started_at` (`started_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'lead_collection_items',
        'sql'  => "CREATE TABLE IF NOT EXISTS `lead_collection_items` (
          `id`            INT AUTO_INCREMENT PRIMARY KEY,
          `collection_id` INT NOT NULL,
          `lead_id`       INT NOT NULL,
          `action`        VARCHAR(20) DEFAULT 'created',
          `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
          KEY `idx_collection` (`collection_id`),
          KEY `idx_lead` (`lead_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'site_settings',
        'sql'  => "CREATE TABLE IF NOT EXISTS `site_settings` (
          `id`            INT AUTO_INCREMENT PRIMARY KEY,
          `setting_key`   VARCHAR(100) NOT NULL,
          `setting_value` TEXT,
          `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'notifications',
        'sql'  => "CREATE TABLE IF NOT EXISTS `notifications` (
          `id`         INT AUTO_INCREMENT PRIMARY KEY,
          `user_id`    INT DEFAULT 0,
          `title`      VARCHAR(255) NOT NULL DEFAULT '',
          `message`    TEXT,
          `type`       VARCHAR(20) DEFAULT 'info',
          `is_read`    TINYINT DEFAULT 0,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          KEY `user_id` (`user_id`),
          KEY `is_read` (`is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'responses',
        'sql'  => "CREATE TABLE IF NOT EXISTS `responses` (
          `id`          INT AUTO_INCREMENT PRIMARY KEY,
          `lead_id`     INT DEFAULT NULL,
          `campaign_id` INT DEFAULT NULL,
          `email`       VARCHAR(255) NOT NULL,
          `subject`     VARCHAR(500) DEFAULT NULL,
          `body`        TEXT,
          `sentiment`   VARCHAR(20) DEFAULT 'neutral',
          `is_read`     TINYINT DEFAULT 0,
          `received_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
          KEY `idx_responses_lead`     (`lead_id`),
          KEY `idx_responses_campaign` (`campaign_id`),
          KEY `idx_responses_email`    (`email`),
          KEY `idx_responses_is_read`  (`is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    [
        'name' => 'cron_log',
        'sql'  => "CREATE TABLE IF NOT EXISTS `cron_log` (
          `id`          INT AUTO_INCREMENT PRIMARY KEY,
          `job_name`    VARCHAR(100) NOT NULL,
          `status`      VARCHAR(20) DEFAULT 'ok',
          `message`     TEXT,
          `duration_ms` INT DEFAULT 0,
          `last_run`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY `job_name` (`job_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],

    // ------------------------------------------------------------------
    // Add missing columns to responses table (for databases created before
    // these columns were added to the canonical schema)
    // ------------------------------------------------------------------
    [
        'name' => 'responses.hot_alert_sent',
        'sql'  => "ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `hot_alert_sent` TINYINT NOT NULL DEFAULT 0",
    ],
    [
        'name' => 'responses.is_replied',
        'sql'  => "ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `is_replied` TINYINT NOT NULL DEFAULT 0",
    ],

    // ------------------------------------------------------------------
    // Default site_settings entries
    // ------------------------------------------------------------------
    [
        'name' => 'site_settings.defaults',
        'sql'  => "INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES
          ('send_delay',            '5'),
          ('automation_mode',       'browser'),
          ('email_daily_limit',     '500'),
          ('auto_campaign_enabled', '1'),
          ('pipeline_batch_size',   '100'),
          ('schema_version',        '0')",
    ],
];

return $migrations;
