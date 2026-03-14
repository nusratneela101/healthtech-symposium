CREATE TABLE IF NOT EXISTS segments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT '',
    color VARCHAR(10) DEFAULT '#0d6efd',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default FinTech segments
INSERT IGNORE INTO segments (name, color, sort_order) VALUES
('Financial Services',                '#0d6efd', 1),
('Banking',                           '#8b5cf6', 2),
('Venture Capital / Investors',       '#10b981', 3),
('Information Technology & Services', '#06b6d4', 4),
('Fintech Startups',                  '#f59e0b', 5),
('Other',                             '#6b7280', 99);

-- NOTE: Run this to remove old healthcare segments if upgrading from v1:
-- DELETE FROM segments WHERE name IN ('Healthcare Providers','Health IT & Digital Health','Pharmaceutical & Biotech','Medical Devices & Equipment');
