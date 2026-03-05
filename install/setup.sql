-- Canada HealthTech Symposium 2026
-- Database Setup SQL

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','admin') DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) DEFAULT '',
  `last_name` varchar(100) DEFAULT '',
  `full_name` varchar(200) DEFAULT '',
  `email` varchar(200) NOT NULL,
  `company` varchar(200) DEFAULT '',
  `job_title` varchar(200) DEFAULT '',
  `role` varchar(100) DEFAULT '',
  `segment` enum('Healthcare Providers','Health IT & Digital Health','Pharmaceutical & Biotech','Medical Devices','Venture Capital / Investors','HealthTech Startups','Other') DEFAULT 'Other',
  `country` varchar(100) DEFAULT 'Canada',
  `province` varchar(100) DEFAULT '',
  `city` varchar(100) DEFAULT '',
  `source` varchar(100) DEFAULT 'Manual',
  `status` enum('new','emailed','responded','converted','unsubscribed','bounced') DEFAULT 'new',
  `linkedin_url` varchar(500) DEFAULT '',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `html_body` longtext NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_key` varchar(100) NOT NULL,
  `name` varchar(300) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `filter_segment` varchar(100) DEFAULT '',
  `filter_role` varchar(100) DEFAULT '',
  `filter_province` varchar(100) DEFAULT '',
  `total_leads` int(11) DEFAULT 0,
  `sent_count` int(11) DEFAULT 0,
  `failed_count` int(11) DEFAULT 0,
  `status` enum('draft','scheduled','running','completed','paused') DEFAULT 'draft',
  `test_mode` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `scheduled_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_key` (`campaign_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) DEFAULT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(200) NOT NULL,
  `recipient_name` varchar(200) DEFAULT '',
  `subject` varchar(500) DEFAULT '',
  `status` enum('queued','sent','failed','bounced','opened','delivered') DEFAULT 'queued',
  `message_id` varchar(500) DEFAULT '',
  `error_message` text,
  `follow_up_sequence` tinyint(1) DEFAULT 1,
  `opened_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) DEFAULT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `from_email` varchar(200) NOT NULL,
  `from_name` varchar(200) DEFAULT '',
  `subject` varchar(500) DEFAULT '',
  `body_text` text,
  `body_html` longtext,
  `message_id` varchar(500) DEFAULT NULL,
  `response_type` enum('interested','not_interested','more_info','wrong_person','auto_reply','bounce','other') DEFAULT 'other',
  `is_read` tinyint(1) DEFAULT 0,
  `is_replied` tinyint(1) DEFAULT 0,
  `hot_alert_sent` tinyint(1) NOT NULL DEFAULT 0,
  `received_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `response_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `response_id` int(11) NOT NULL,
  `replied_by` int(11) DEFAULT NULL,
  `reply_subject` varchar(500) DEFAULT '',
  `reply_body` text,
  `message_id` varchar(500) DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_response` (`response_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `oauth_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) NOT NULL DEFAULT 'microsoft',
  `email` varchar(200) NOT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text NOT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `scopes` varchar(500) DEFAULT '',
  `connected_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_email` (`provider`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reply_threads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) DEFAULT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `subject` varchar(500) DEFAULT '',
  `conversation_id` varchar(500) DEFAULT '',
  `last_message_at` datetime DEFAULT NULL,
  `message_count` int(11) DEFAULT 1,
  `status` enum('active','closed','archived') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lead` (`lead_id`),
  KEY `idx_campaign` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT '',
  `entity_id` int(11) DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20 Sample Canadian Leads
INSERT IGNORE INTO `leads` (`first_name`,`last_name`,`full_name`,`email`,`company`,`job_title`,`role`,`segment`,`country`,`province`,`city`,`source`,`status`) VALUES
('James','Clarke','James Clarke','j.clarke@td.com','TD Bank','Chief Innovation Officer','Chief Innovation Officer','Health IT & Digital Health','Canada','Ontario','Toronto','Apollo','new'),
('Sarah','Chen','Sarah Chen','s.chen@rbcvc.com','RBC Ventures','Managing Partner','Managing Partner','Venture Capital / Investors','Canada','Ontario','Toronto','Apollo','new'),
('Liam','Murphy','Liam Murphy','l.murphy@waveapp.com','Wave Health','Founder','Founder','HealthTech Startups','Canada','Ontario','Toronto','Apollo','new'),
('Aisha','Patel','Aisha Patel','a.patel@bmo.com','BMO Financial','VP Digital Health','VP Digital Health','Health IT & Digital Health','Canada','Quebec','Montreal','Apollo','new'),
('Noah','Tremblay','Noah Tremblay','n.tremblay@desjardins.ca','Desjardins','Director HealthTech Strategy','Director HealthTech Strategy','Health IT & Digital Health','Canada','Quebec','Montreal','Apollo','new'),
('Emily','Watson','Emily Watson','e.watson@bdc.ca','BDC Capital','Head of Corporate Venture','Head of Corporate Venture','Venture Capital / Investors','Canada','Ontario','Ottawa','Apollo','new'),
('Marcus','Lee','Marcus Lee','m.lee@shopify.com','Shopify Health','Head of Product','Head of Product','HealthTech Startups','Canada','Ontario','Ottawa','Apollo','new'),
('Tom','Bedard','Tom Bedard','t.bedard@vanedge.ca','Vanedge Capital','General Partner','General Partner','Venture Capital / Investors','Canada','British Columbia','Vancouver','Apollo','new'),
('Julia','Kim','Julia Kim','j.kim@medtech.com','MedTech Inc.','COO','COO','Medical Devices','Canada','Ontario','Toronto','Apollo','new'),
('Chris','Walter','Chris Walter','c.walter@ibm.com','IBM Canada','Head of Healthcare Services','Head of Healthcare Services','Health IT & Digital Health','Canada','Ontario','Toronto','Apollo','new'),
('Jessica','Brown','Jessica Brown','j.brown@accenture.com','Accenture Canada','VP Strategic Partnerships','VP Strategic Partnerships','Health IT & Digital Health','Canada','Ontario','Toronto','Apollo','new'),
('Grace','Park','Grace Park','g.park@omers.com','OMERS Ventures','Investment Partner','Investment Partner','Venture Capital / Investors','Canada','Ontario','Toronto','Apollo','new'),
('Daniel','Roy','Daniel Roy','d.roy@pharmatech.com','PharmaTech','Growth Lead','Growth Lead','Pharmaceutical & Biotech','Canada','Quebec','Montreal','Apollo','new'),
('Sofia','Moreau','Sofia Moreau','s.moreau@rbc.com','RBC Royal Bank','Chief Digital Officer','Chief Digital Officer','Health IT & Digital Health','Canada','Ontario','Toronto','Apollo','new'),
('Ryan','OBrien','Ryan OBrien','r.obrien@cibc.com','CIBC','Head of Emerging Technology','Head of Emerging Technology','Health IT & Digital Health','Canada','Ontario','Toronto','Apollo','new'),
('Lisa','Okafor','Lisa Okafor','l.okafor@salesforce.ca','Salesforce Canada','Director Demand Generation','Director Demand Generation','Health IT & Digital Health','Canada','Alberta','Calgary','Apollo','new'),
('Mark','Fischer','Mark Fischer','m.fischer@oracle.ca','Oracle Canada','VP Enterprise Sales','VP Enterprise Sales','Health IT & Digital Health','Canada','Ontario','Mississauga','Apollo','new'),
('Ben','Sharma','Ben Sharma','b.sharma@koho.ca','KOHO Health','Product Manager','Product Manager','HealthTech Startups','Canada','British Columbia','Vancouver','Apollo','new'),
('Ella','Morrison','Ella Morrison','e.morrison@clearco.com','ClearHealth','Business Development Manager','Business Development Manager','Healthcare Providers','Canada','Ontario','Toronto','Apollo','new'),
('Kevin','Nguyen','Kevin Nguyen','k.nguyen@mastercard.ca','Mastercard Canada','VP Channel Sales','VP Channel Sales','Health IT & Digital Health','Canada','Ontario','Toronto','Apollo','new');

-- Lead collection history tables (included here for fresh installs; also added as migration below for existing installs)
CREATE TABLE IF NOT EXISTS `lead_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source` varchar(100) DEFAULT 'Apollo',
  `total_fetched` int(11) DEFAULT 0,
  `total_saved` int(11) DEFAULT 0,
  `total_skipped` int(11) DEFAULT 0,
  `total_duplicates` int(11) DEFAULT 0,
  `search_params` text DEFAULT NULL,
  `status` enum('running','completed','failed') DEFAULT 'completed',
  `error_message` text DEFAULT NULL,
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lead_collection_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `collection_id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `action` enum('created','skipped','duplicate') DEFAULT 'created',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_lead` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration: Add scheduling columns to campaigns
ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `scheduled_at` datetime DEFAULT NULL AFTER `completed_at`;
ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `scheduled_by` int(11) DEFAULT NULL AFTER `scheduled_at`;

-- Migration: Add 'scheduled' status to campaigns
ALTER TABLE `campaigns` MODIFY COLUMN `status` enum('draft','scheduled','running','completed','paused') DEFAULT 'draft';

-- Migration: Lead collection history tables
CREATE TABLE IF NOT EXISTS `lead_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source` varchar(100) DEFAULT 'Apollo',
  `total_fetched` int(11) DEFAULT 0,
  `total_saved` int(11) DEFAULT 0,
  `total_skipped` int(11) DEFAULT 0,
  `total_duplicates` int(11) DEFAULT 0,
  `search_params` text DEFAULT NULL,
  `status` enum('running','completed','failed') DEFAULT 'completed',
  `error_message` text DEFAULT NULL,
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lead_collection_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `collection_id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `action` enum('created','skipped','duplicate') DEFAULT 'created',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_lead` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Email Template
INSERT INTO `email_templates` (`name`,`subject`,`html_body`,`is_default`) VALUES (
'HealthTech Symposium 2026 Invitation',
'Exclusive Invitation: Canada HealthTech Symposium 2026',
'<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}.wrap{max-width:600px;margin:0 auto;background:#fff}.header{background:linear-gradient(135deg,#CC0000,#0a1628);padding:40px 30px;text-align:center}.header h1{color:#fff;font-size:24px;margin:0}.header p{color:rgba(255,255,255,.7);font-style:italic;margin:8px 0 0}.body{padding:30px}.body h2{color:#0d6efd}.body p{color:#555;line-height:1.7}.cta{text-align:center;margin:30px 0}.cta a{background:#0d6efd;color:#fff;padding:14px 32px;text-decoration:none;border-radius:8px;font-weight:bold;display:inline-block}.footer{background:#f9f9f9;padding:20px;text-align:center;font-size:12px;color:#999}</style></head><body><div class="wrap"><div class="header"><h1>🏥 Canada HealthTech Symposium</h1><p>Igniting the Future of Health</p><p style="color:rgba(255,255,255,.8);font-size:13px;font-style:normal;margin:4px 0 0">April 21-22, 2026 - Toronto, Canada</p></div><div class="body"><h2>Dear {{first_name}},</h2><p>As <strong>{{role}}</strong> at <strong>{{company}}</strong>, your expertise in driving health innovation in {{city}} makes you an ideal participant for Canada''s premier HealthTech gathering.</p><p>Join <strong>500+ C-Suite executives, investors, and technology leaders</strong> for two days of keynotes, workshops, and networking opportunities that will shape the future of healthcare in Canada.</p><div class="cta"><a href="https://yourdomain.com/healthtech/register">Register Your Seat Now</a></div><p>Spaces are extremely limited. Secure your spot today.</p><p>Best regards,<br><strong>Canada HealthTech Symposium Team</strong><br>sm@101bdtech.com</p></div><div class="footer"><p>You received this email because your profile matches our event criteria.<br><a href="{{unsubscribe_link}}">Unsubscribe</a></p></div></div></body></html>',
1
);

-- Migration: Add hot_alert_sent column to responses table
ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `hot_alert_sent` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_replied`;

-- Site Settings table (Feature A)
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_group` varchar(50) DEFAULT 'general',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lead Tags system (Feature B14)
CREATE TABLE IF NOT EXISTS `lead_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT '#0d6efd',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lead_tag_map` (
  `lead_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`lead_id`, `tag_id`),
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `lead_tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications table (Feature D8)
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate Limits table (Feature D24)
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(200) NOT NULL,
  `endpoint` varchar(200) NOT NULL,
  `requests` int DEFAULT 1,
  `window_start` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `identifier_endpoint` (`identifier`, `endpoint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration: Add score column to leads table (Feature D17)
ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `score` int DEFAULT 0 AFTER `notes`;
