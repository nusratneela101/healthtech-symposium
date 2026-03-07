-- Lead Collections Tables
CREATE TABLE IF NOT EXISTS `lead_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source` varchar(100) DEFAULT 'Apollo',
  `total_fetched` int(11) DEFAULT 0,
  `total_saved` int(11) DEFAULT 0,
  `total_skipped` int(11) DEFAULT 0,
  `total_duplicates` int(11) DEFAULT 0,
  `status` varchar(50) DEFAULT 'pending',
  `search_params` text,
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lead_collection_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `collection_id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `action` varchar(50) DEFAULT 'added',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `collection_id` (`collection_id`),
  KEY `lead_id` (`lead_id`),
  CONSTRAINT `fk_collection` FOREIGN KEY (`collection_id`) REFERENCES `lead_collections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
