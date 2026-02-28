-- Canada FinTech Symposium 2026
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
  `segment` enum('Financial Institutions','Technology & Solution Providers','Venture Capital / Investors','FinTech Startups','Other') DEFAULT 'Other',
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
  `status` enum('draft','running','completed','paused') DEFAULT 'draft',
  `test_mode` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
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
  `status` enum('queued','sent','failed','bounced','opened') DEFAULT 'queued',
  `message_id` varchar(500) DEFAULT '',
  `error_message` text,
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
  `response_type` enum('interested','not_interested','more_info','auto_reply','bounce','other') DEFAULT 'other',
  `is_read` tinyint(1) DEFAULT 0,
  `is_replied` tinyint(1) DEFAULT 0,
  `received_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `response_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `response_id` int(11) NOT NULL,
  `replied_by` int(11) DEFAULT NULL,
  `reply_subject` varchar(500) DEFAULT '',
  `reply_body` longtext,
  `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20 Sample Canadian Leads
INSERT IGNORE INTO `leads` (`first_name`,`last_name`,`full_name`,`email`,`company`,`job_title`,`role`,`segment`,`country`,`province`,`city`,`source`,`status`) VALUES
('James','Clarke','James Clarke','j.clarke@td.com','TD Bank','Chief Innovation Officer','Chief Innovation Officer','Financial Institutions','Canada','Ontario','Toronto','Apollo','new'),
('Sarah','Chen','Sarah Chen','s.chen@rbcvc.com','RBC Ventures','Managing Partner','Managing Partner','Venture Capital / Investors','Canada','Ontario','Toronto','Apollo','new'),
('Liam','Murphy','Liam Murphy','l.murphy@waveapp.com','Wave Financial','Founder','Founder','FinTech Startups','Canada','Ontario','Toronto','Apollo','new'),
('Aisha','Patel','Aisha Patel','a.patel@bmo.com','BMO Financial','VP Digital Banking','VP Digital Banking','Financial Institutions','Canada','Quebec','Montreal','Apollo','new'),
('Noah','Tremblay','Noah Tremblay','n.tremblay@desjardins.ca','Desjardins','Director FinTech Strategy','Director FinTech Strategy','Financial Institutions','Canada','Quebec','Montreal','Apollo','new'),
('Emily','Watson','Emily Watson','e.watson@bdc.ca','BDC Capital','Head of Corporate Venture','Head of Corporate Venture','Venture Capital / Investors','Canada','Ontario','Ottawa','Apollo','new'),
('Marcus','Lee','Marcus Lee','m.lee@shopify.com','Shopify','Head of Product','Head of Product','FinTech Startups','Canada','Ontario','Ottawa','Apollo','new'),
('Tom','Bedard','Tom Bedard','t.bedard@vanedge.ca','Vanedge Capital','General Partner','General Partner','Venture Capital / Investors','Canada','British Columbia','Vancouver','Apollo','new'),
('Julia','Kim','Julia Kim','j.kim@coinsquare.com','Coinsquare','COO','COO','FinTech Startups','Canada','Ontario','Toronto','Apollo','new'),
('Chris','Walter','Chris Walter','c.walter@ibm.com','IBM Canada','Head of Financial Services','Head of Financial Services','Technology & Solution Providers','Canada','Ontario','Toronto','Apollo','new'),
('Jessica','Brown','Jessica Brown','j.brown@accenture.com','Accenture Canada','VP Strategic Partnerships','VP Strategic Partnerships','Technology & Solution Providers','Canada','Ontario','Toronto','Apollo','new'),
('Grace','Park','Grace Park','g.park@omers.com','OMERS Ventures','Investment Partner','Investment Partner','Venture Capital / Investors','Canada','Ontario','Toronto','Apollo','new'),
('Daniel','Roy','Daniel Roy','d.roy@nuvei.com','Nuvei','Growth Lead','Growth Lead','FinTech Startups','Canada','Quebec','Montreal','Apollo','new'),
('Sofia','Moreau','Sofia Moreau','s.moreau@rbc.com','RBC Royal Bank','Chief Digital Officer','Chief Digital Officer','Financial Institutions','Canada','Ontario','Toronto','Apollo','new'),
('Ryan','OBrien','Ryan OBrien','r.obrien@cibc.com','CIBC','Head of Emerging Technology','Head of Emerging Technology','Financial Institutions','Canada','Ontario','Toronto','Apollo','new'),
('Lisa','Okafor','Lisa Okafor','l.okafor@salesforce.ca','Salesforce Canada','Director Demand Generation','Director Demand Generation','Technology & Solution Providers','Canada','Alberta','Calgary','Apollo','new'),
('Mark','Fischer','Mark Fischer','m.fischer@oracle.ca','Oracle Canada','VP Enterprise Sales','VP Enterprise Sales','Technology & Solution Providers','Canada','Ontario','Mississauga','Apollo','new'),
('Ben','Sharma','Ben Sharma','b.sharma@koho.ca','KOHO Financial','Product Manager','Product Manager','FinTech Startups','Canada','British Columbia','Vancouver','Apollo','new'),
('Ella','Morrison','Ella Morrison','e.morrison@clearco.com','Clearco','Business Development Manager','Business Development Manager','FinTech Startups','Canada','Ontario','Toronto','Apollo','new'),
('Kevin','Nguyen','Kevin Nguyen','k.nguyen@mastercard.ca','Mastercard Canada','VP Channel Sales','VP Channel Sales','Technology & Solution Providers','Canada','Ontario','Toronto','Apollo','new');

-- Default Email Template
INSERT INTO `email_templates` (`name`,`subject`,`html_body`,`is_default`) VALUES (
'Canada FinTech Symposium 2026 Invitation',
'Exclusive Invitation: Canada FinTech Symposium 2026',
'<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}.wrap{max-width:600px;margin:0 auto;background:#fff}.header{background:linear-gradient(135deg,#0a1628,#1a237e);padding:40px 30px 30px;text-align:center}.header p{color:rgba(255,255,255,.7);margin:0;font-size:13px}.body{padding:30px}.body h2{color:#0d6efd}.body p{color:#555;line-height:1.7}.cta{text-align:center;margin:30px 0}.cta a{background:#0d6efd;color:#fff;padding:14px 32px;text-decoration:none;border-radius:8px;font-weight:bold;display:inline-block}.footer{background:#f9f9f9;padding:20px;text-align:center;font-size:12px;color:#999}</style></head><body><div class="wrap"><div class="header"><img src="https://YOURSITE.com/healthtech/assets/images/cfts-logo.png" alt="Canada FinTech Symposium" style="width:260px;background:white;padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block;margin-left:auto;margin-right:auto;"><p>April 21–22, 2026 · Toronto, Canada</p></div><div class="body"><h2>Dear {{first_name}},</h2><p>As <strong>{{role}}</strong> at <strong>{{company}}</strong>, your expertise in driving financial innovation in {{city}} makes you an ideal participant for Canada''s premier FinTech gathering.</p><p>Join <strong>500+ C-Suite executives, investors, and technology leaders</strong> for two days of keynotes, workshops, and networking opportunities that will shape the future of finance in Canada.</p><div class="cta"><a href="https://yourdomain.com/healthtech/register">Register Your Seat Now</a></div><p>Spaces are extremely limited. Secure your spot today.</p><p>Best regards,<br><strong>Canada FinTech Symposium Team</strong><br>sm@101bdtech.com</p></div><div class="footer"><p>You received this email because your profile matches our event criteria.<br><a href="{{unsubscribe_link}}">Unsubscribe</a></p></div></div></body></html>',
1
);
