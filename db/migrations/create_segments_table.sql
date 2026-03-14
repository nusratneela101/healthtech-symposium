CREATE TABLE IF NOT EXISTS segments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT '',
    color VARCHAR(10) DEFAULT '#0d6efd',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert existing default segments
INSERT IGNORE INTO segments (name, sort_order) VALUES
('Healthcare Providers', 1),
('Health IT & Digital Health', 2),
('Pharmaceutical & Biotech', 3),
('Medical Devices & Equipment', 4),
('Venture Capital / Investors', 5),
('Fintech Startups', 6),
('Other', 99);
