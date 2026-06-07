-- =====================================================
-- DNS MANAGER TABLES
-- =====================================================

CREATE TABLE IF NOT EXISTS dns_domains (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    domain_name VARCHAR(255) NOT NULL,
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_domain_name (domain_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dns_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain_id INT UNSIGNED NOT NULL,
    type ENUM('A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'NS', 'CAA') NOT NULL,
    name VARCHAR(255) NOT NULL COMMENT 'e.g., @ or www',
    content TEXT NOT NULL,
    ttl INT UNSIGNED DEFAULT 3600,
    prio INT UNSIGNED DEFAULT 0 COMMENT 'Only for MX and SRV',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (domain_id) REFERENCES dns_domains(id) ON DELETE CASCADE,
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
