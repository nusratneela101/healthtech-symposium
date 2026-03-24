-- ============================================================
-- Canada Fintech Symposium - Complete Database Schema
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100) NOT NULL,
  `username`     VARCHAR(50)  NOT NULL,
  `email`        VARCHAR(255) NOT NULL,
  `password`     VARCHAR(255) NOT NULL,
  `role`         ENUM('superadmin','admin','user') NOT NULL DEFAULT 'user',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login`   DATETIME     DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email`    (`email`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role`      (`role`),
  KEY `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: leads
-- ============================================================
CREATE TABLE IF NOT EXISTS `leads` (
  `id`              INT(11)      NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(200) DEFAULT NULL,
  `full_name`       VARCHAR(200) DEFAULT NULL,
  `email`           VARCHAR(255) NOT NULL,
  `company`         VARCHAR(200) DEFAULT NULL,
  `job_title`       VARCHAR(200) DEFAULT NULL,
  `role`            VARCHAR(100) DEFAULT NULL,
  `phone`           VARCHAR(50)  DEFAULT NULL,
  `linkedin_url`    VARCHAR(500) DEFAULT NULL,
  `website`         VARCHAR(500) DEFAULT NULL,
  `city`            VARCHAR(100) DEFAULT NULL,
  `state`           VARCHAR(100) DEFAULT NULL,
  `province`        VARCHAR(100) DEFAULT NULL,
  `country`         VARCHAR(100) DEFAULT 'Canada',
  `segment`         VARCHAR(100) DEFAULT NULL,
  `status`          ENUM('new','emailed','responded','converted','unsubscribed','bounced') NOT NULL DEFAULT 'new',
  `notes`           TEXT         DEFAULT NULL,
  `score`           INT(11)      NOT NULL DEFAULT 0,
  `is_hot`          TINYINT(1)   NOT NULL DEFAULT 0,
  `source`          VARCHAR(100) DEFAULT 'manual',
  `apollo_id`       VARCHAR(100) DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leads_email`  (`email`),
  KEY `idx_leads_status`   (`status`),
  KEY `idx_leads_segment`  (`segment`),
  KEY `idx_leads_is_hot`   (`is_hot`),
  KEY `idx_leads_score`    (`score`),
  KEY `idx_leads_source`   (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: campaigns
-- ============================================================
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id`              INT(11)      NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(200) NOT NULL,
  `subject`         VARCHAR(500) NOT NULL,
  `body`            LONGTEXT     NOT NULL,
  `status`          ENUM('draft','scheduled','running','paused','completed','failed') NOT NULL DEFAULT 'draft',
  `segment`         VARCHAR(100) DEFAULT NULL,
  `template_id`     INT(11)      DEFAULT NULL,
  `scheduled_at`    DATETIME     DEFAULT NULL,
  `scheduled_by`    INT(11)      DEFAULT NULL,
  `started_at`      DATETIME     DEFAULT NULL,
  `completed_at`    DATETIME     DEFAULT NULL,
  `total_sent`      INT(11)      NOT NULL DEFAULT 0,
  `total_failed`    INT(11)      NOT NULL DEFAULT 0,
  `total_opened`    INT(11)      NOT NULL DEFAULT 0,
  `total_clicked`   INT(11)      NOT NULL DEFAULT 0,
  `sent_count`      INT(11)      NOT NULL DEFAULT 0,
  `failed_count`    INT(11)      NOT NULL DEFAULT 0,
  `total_leads`     INT(11)      NOT NULL DEFAULT 0,
  `target_mode`     VARCHAR(10)  DEFAULT 'all',
  `target_count`    INT(11)      NOT NULL DEFAULT 0,
  `test_mode`       TINYINT(1)   NOT NULL DEFAULT 0,
  `campaign_key`    VARCHAR(100) DEFAULT NULL,
  `filter_segment`  VARCHAR(100) DEFAULT NULL,
  `filter_role`     VARCHAR(100) DEFAULT NULL,
  `filter_province` VARCHAR(100) DEFAULT NULL,
  `created_by`      INT(11)      DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaigns_status`      (`status`),
  KEY `idx_campaigns_scheduled`   (`scheduled_at`),
  KEY `idx_campaigns_segment`     (`segment`),
  KEY `fk_campaigns_created_by`   (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: email_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id`                  INT(11)      NOT NULL AUTO_INCREMENT,
  `campaign_id`         INT(11)      DEFAULT NULL,
  `lead_id`             INT(11)      DEFAULT NULL,
  `email`               VARCHAR(255) NOT NULL,
  `recipient_email`     VARCHAR(255) DEFAULT NULL,
  `recipient_name`      VARCHAR(255) DEFAULT NULL,
  `subject`             VARCHAR(500) DEFAULT NULL,
  `status`              ENUM('queued','sent','delivered','failed','bounced','opened','clicked','unsubscribed') NOT NULL DEFAULT 'queued',
  `provider`            VARCHAR(50)  DEFAULT NULL,
  `follow_up_sequence`  INT          DEFAULT 1,
  `message_id`          VARCHAR(255) DEFAULT NULL,
  `error_message`       TEXT         DEFAULT NULL,
  `opened`              TINYINT(1)   NOT NULL DEFAULT 0,
  `opened_at`           DATETIME     DEFAULT NULL,
  `clicked`             TINYINT(1)   NOT NULL DEFAULT 0,
  `clicked_at`          DATETIME     DEFAULT NULL,
  `sent_at`             DATETIME     DEFAULT NULL,
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_campaign`      (`campaign_id`),
  KEY `idx_email_logs_lead`          (`lead_id`),
  KEY `idx_email_logs_status`        (`status`),
  KEY `idx_email_logs_email`         (`email`),
  KEY `idx_email_logs_recipient`     (`recipient_email`),
  KEY `idx_email_logs_sent_at`       (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: responses
-- ============================================================
CREATE TABLE IF NOT EXISTS `responses` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `lead_id`       INT(11)      DEFAULT NULL,
  `campaign_id`   INT(11)      DEFAULT NULL,
  `email`         VARCHAR(255) NOT NULL,
  `subject`       VARCHAR(500) DEFAULT NULL,
  `body`          LONGTEXT     DEFAULT NULL,
  `category`      VARCHAR(100) DEFAULT NULL,
  `sentiment`     ENUM('positive','neutral','negative') DEFAULT 'neutral',
  `is_read`       TINYINT(1)   NOT NULL DEFAULT 0,
  `received_at`   DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_responses_lead`       (`lead_id`),
  KEY `idx_responses_campaign`   (`campaign_id`),
  KEY `idx_responses_email`      (`email`),
  KEY `idx_responses_is_read`    (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: templates
-- ============================================================
CREATE TABLE IF NOT EXISTS `templates` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(200) NOT NULL,
  `subject`     VARCHAR(500) NOT NULL,
  `body`        LONGTEXT     NOT NULL,
  `category`    VARCHAR(100) DEFAULT 'general',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`  INT(11)      DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_templates_is_active` (`is_active`),
  KEY `idx_templates_category`  (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: site_settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`    VARCHAR(100) NOT NULL,
  `setting_value`  TEXT         DEFAULT NULL,
  `setting_group`  VARCHAR(50)  NOT NULL DEFAULT 'general',
  `updated_by`     INT(11)      DEFAULT NULL,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`),
  KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: lead_collections
-- ============================================================
CREATE TABLE IF NOT EXISTS `lead_collections` (
  `id`                INT(11)      NOT NULL AUTO_INCREMENT,
  `source`            VARCHAR(100) DEFAULT 'Apollo',
  `total_fetched`     INT(11)      NOT NULL DEFAULT 0,
  `total_saved`       INT(11)      NOT NULL DEFAULT 0,
  `total_skipped`     INT(11)      NOT NULL DEFAULT 0,
  `total_duplicates`  INT(11)      NOT NULL DEFAULT 0,
  `status`            VARCHAR(50)  NOT NULL DEFAULT 'pending',
  `search_params`     TEXT         DEFAULT NULL,
  `started_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at`      DATETIME     DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lc_status`     (`status`),
  KEY `idx_lc_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: lead_collection_history  (alias: lead_collection_items)
-- ============================================================
CREATE TABLE IF NOT EXISTS `lead_collection_history` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `collection_id`  INT(11)      NOT NULL,
  `lead_id`        INT(11)      NOT NULL,
  `action`         VARCHAR(50)  NOT NULL DEFAULT 'added',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_lch_collection` (`collection_id`),
  KEY `fk_lch_lead`       (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: audit_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`      INT(11)      DEFAULT NULL,
  `action`       VARCHAR(200) NOT NULL,
  `entity_type`  VARCHAR(100) DEFAULT NULL,
  `entity_id`    INT(11)      DEFAULT NULL,
  `details`      TEXT         DEFAULT NULL,
  `ip_address`   VARCHAR(50)  DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user`        (`user_id`),
  KEY `idx_audit_action`      (`action`),
  KEY `idx_audit_entity`      (`entity_type`, `entity_id`),
  KEY `idx_audit_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: tags
-- ============================================================
CREATE TABLE IF NOT EXISTS `tags` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `color`       VARCHAR(20)  NOT NULL DEFAULT '#4F46E5',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: lead_tags  (pivot)
-- ============================================================
CREATE TABLE IF NOT EXISTS `lead_tags` (
  `lead_id`    INT(11) NOT NULL,
  `tag_id`     INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`lead_id`, `tag_id`),
  KEY `fk_lt_tag` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Foreign keys
-- ============================================================
ALTER TABLE `campaigns`
  ADD CONSTRAINT `fk_campaigns_created_by`
    FOREIGN KEY IF NOT EXISTS (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `email_logs`
  ADD CONSTRAINT `fk_email_logs_campaign`
    FOREIGN KEY IF NOT EXISTS (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_email_logs_lead`
    FOREIGN KEY IF NOT EXISTS (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL;

ALTER TABLE `responses`
  ADD CONSTRAINT `fk_responses_lead`
    FOREIGN KEY IF NOT EXISTS (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_responses_campaign`
    FOREIGN KEY IF NOT EXISTS (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL;

ALTER TABLE `templates`
  ADD CONSTRAINT `fk_templates_created_by`
    FOREIGN KEY IF NOT EXISTS (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `site_settings`
  ADD CONSTRAINT `fk_site_settings_updated_by`
    FOREIGN KEY IF NOT EXISTS (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `lead_collection_history`
  ADD CONSTRAINT `fk_lch_collection`
    FOREIGN KEY IF NOT EXISTS (`collection_id`) REFERENCES `lead_collections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lch_lead`
    FOREIGN KEY IF NOT EXISTS (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_user`
    FOREIGN KEY IF NOT EXISTS (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `lead_tags`
  ADD CONSTRAINT `fk_lt_lead`
    FOREIGN KEY IF NOT EXISTS (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lt_tag`
    FOREIGN KEY IF NOT EXISTS (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

-- ============================================================
-- Default site settings
-- ============================================================
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
  ('site_name',            'Canada Fintech Symposium', 'general'),
  ('site_url',             '',                         'general'),
  ('timezone',             'America/Toronto',          'general'),
  ('email_daily_limit',    '0',                        'email'),
  ('email_weekly_limit',   '0',                        'email'),
  ('email_monthly_limit',  '0',                        'email'),
  ('followup_daily_limit', '0',                        'email'),
  ('installed_at',         NOW(),                      'system'),
  ('app_version',          '2.0.0',                   'system');

SET FOREIGN_KEY_CHECKS = 1;
