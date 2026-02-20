-- LogicPanel Complete Database Schema
-- MySQL/MariaDB
-- Version: 3.0 (Unified - All tables, columns, indexes, and seed data)
-- Last Updated: 2026-02-12

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'reseller', 'user') DEFAULT 'user',
    status ENUM('active', 'suspended', 'terminated') DEFAULT 'active',
    
    -- Security fields
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    
    -- Login tracking
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45) DEFAULT NULL,
    
    -- Package assignment
    package_id INT UNSIGNED DEFAULT NULL,
    owner_id INT UNSIGNED DEFAULT NULL COMMENT 'For Reseller Sub-users',
    domain VARCHAR(255) DEFAULT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_package_id (package_id),
    INDEX idx_owner_id (owner_id),
    INDEX idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PACKAGES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS packages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type ENUM('user', 'reseller') DEFAULT 'user',
    
    -- Creator tracking (for reseller-created packages)
    created_by INT UNSIGNED DEFAULT NULL COMMENT 'Reseller ID who created this package',
    is_global TINYINT(1) DEFAULT 1 COMMENT '1=Global (admin), 0=Reseller-specific',
    
    -- Resource Limits
    cpu_limit DECIMAL(5,2) DEFAULT 1.00,
    memory_limit INT UNSIGNED DEFAULT 1024,
    storage_limit INT UNSIGNED DEFAULT 5120,
    disk_quota INT UNSIGNED DEFAULT 5120,
    bandwidth_limit INT UNSIGNED DEFAULT 51200,
    bandwidth INT UNSIGNED DEFAULT 51200,
    
    -- Account Limits
    max_apps INT UNSIGNED DEFAULT 1,
    max_services INT UNSIGNED DEFAULT 1,
    max_databases INT UNSIGNED DEFAULT 3,
    db_limit INT UNSIGNED DEFAULT 3,
    max_domains INT UNSIGNED DEFAULT 5,
    max_subdomains INT UNSIGNED DEFAULT 5,
    max_parked_domains INT UNSIGNED DEFAULT 0,
    max_addon_domains INT UNSIGNED DEFAULT 5,

    -- Reseller Limits
    limit_users INT UNSIGNED DEFAULT 0,
    limit_disk_total INT UNSIGNED DEFAULT 0,
    limit_bandwidth_total INT UNSIGNED DEFAULT 0,
    
    -- Pricing
    price DECIMAL(10,2) DEFAULT 0.00,
    billing_cycle ENUM('monthly', 'quarterly', 'yearly', 'lifetime') DEFAULT 'monthly',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_type (type),
    INDEX idx_created_by (created_by),
    INDEX idx_is_global (is_global)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SERVICES TABLE (User Applications)
-- =====================================================
CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    domain VARCHAR(255),
    type ENUM('nodejs', 'python', 'static', 'php') NOT NULL DEFAULT 'nodejs',
    status ENUM('creating', 'deploying', 'running', 'stopped', 'error', 'suspended') DEFAULT 'creating',
    
    -- Container info
    container_id VARCHAR(64),
    port INT UNSIGNED,
    
    -- Git integration
    git_repo VARCHAR(255) DEFAULT NULL,
    git_branch VARCHAR(100) DEFAULT 'main',
    auto_deploy TINYINT(1) DEFAULT 0,
    
    -- Runtime settings
    node_version VARCHAR(20) DEFAULT '20',
    python_version VARCHAR(20) DEFAULT '3.11',
    runtime_version VARCHAR(50) DEFAULT '',
    
    -- Commands
    install_command VARCHAR(500) DEFAULT 'npm install',
    build_command VARCHAR(500) DEFAULT '',
    start_command VARCHAR(500) DEFAULT 'npm start',
    
    -- Environment variables (Encrypted JSON)
    env_vars TEXT,
    
    -- Resource Limits
    cpu_limit DECIMAL(3,2) DEFAULT 0.50,
    memory_limit VARCHAR(10) DEFAULT '512M',
    disk_limit VARCHAR(10) DEFAULT '1G',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_deployed_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_container_id (container_id),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DOMAINS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS domains (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('primary', 'addon', 'subdomain', 'alias', 'parked') DEFAULT 'primary',
    path VARCHAR(255) DEFAULT '/public_html',
    parent_id INT UNSIGNED DEFAULT NULL,
    status ENUM('active', 'suspended', 'pending', 'dns_error') DEFAULT 'active',
    ssl_enabled TINYINT(1) DEFAULT 0,
    ssl_expires_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES domains(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_service_id (service_id),
    INDEX idx_name (name),
    INDEX idx_type (type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DATABASES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `databases` (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED DEFAULT NULL,
    
    -- Database info
    db_type ENUM('mysql', 'postgresql', 'mongodb') NOT NULL DEFAULT 'mysql',
    db_name VARCHAR(64) NOT NULL,
    db_user VARCHAR(64) NOT NULL,
    db_password TEXT NOT NULL,  -- Encrypted
    db_host VARCHAR(255) NOT NULL DEFAULT 'localhost',
    db_port INT UNSIGNED NOT NULL DEFAULT 3306,
    
    -- Status
    status ENUM('pending', 'creating', 'active', 'suspended', 'error', 'deleting') DEFAULT 'pending',
    size_mb INT UNSIGNED DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    UNIQUE KEY unique_db_name (db_type, db_name),
    INDEX idx_service_id (service_id),
    INDEX idx_user_id (user_id),
    INDEX idx_db_type (db_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- USER PACKAGES TABLE (Package Assignments)
-- =====================================================
CREATE TABLE IF NOT EXISTS user_packages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    package_id INT UNSIGNED NOT NULL,
    status ENUM('active', 'expired', 'cancelled', 'suspended') DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    
    -- Usage tracking
    storage_used INT UNSIGNED DEFAULT 0,
    bandwidth_used INT UNSIGNED DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_package_id (package_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- API KEYS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS api_keys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    api_key VARCHAR(255) NOT NULL UNIQUE,
    key_hash VARCHAR(255) DEFAULT NULL,
    permissions TEXT,  -- JSON array of permissions
    status ENUM('active', 'revoked', 'expired') DEFAULT 'active',
    
    -- Usage tracking
    last_used_at TIMESTAMP NULL,
    last_used_ip VARCHAR(45) DEFAULT NULL,
    usage_count INT UNSIGNED DEFAULT 0,
    
    -- Expiration
    expires_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_api_key (api_key),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- AUDIT LOGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id INT UNSIGNED,
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata JSON,
    old_values JSON,
    new_values JSON,
    
    -- Reseller tracking
    performed_by_reseller INT UNSIGNED DEFAULT NULL COMMENT 'If action was done by reseller on behalf of user',
    target_user_id INT UNSIGNED DEFAULT NULL COMMENT 'User affected by this action',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at),
    INDEX idx_reseller (performed_by_reseller),
    INDEX idx_target_user (target_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SESSIONS TABLE (JWT Refresh Tokens)
-- =====================================================
CREATE TABLE IF NOT EXISTS sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_info VARCHAR(255) DEFAULT NULL,
    expires_at TIMESTAMP NOT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_token_hash (token_hash),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    data JSON,
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SYSTEM SETTINGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT,
    `type` ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    `group` VARCHAR(50) DEFAULT 'general',
    description VARCHAR(255) DEFAULT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_key (`key`),
    INDEX idx_group (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DEPLOYMENTS TABLE (Deployment History)
-- =====================================================
CREATE TABLE IF NOT EXISTS deployments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'building', 'deploying', 'success', 'failed', 'cancelled') DEFAULT 'pending',
    commit_hash VARCHAR(40) DEFAULT NULL,
    commit_message VARCHAR(255) DEFAULT NULL,
    build_logs TEXT,
    deploy_logs TEXT,
    duration_seconds INT UNSIGNED DEFAULT 0,
    
    -- Timestamps
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_service_id (service_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CRON JOBS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS cron_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id INT UNSIGNED NOT NULL,
    schedule VARCHAR(100) NOT NULL COMMENT 'Cron expression (e.g., * * * * *)',
    command VARCHAR(500) NOT NULL COMMENT 'Command to execute inside container',
    is_active TINYINT(1) DEFAULT 1,
    last_run DATETIME NULL,
    last_result TEXT NULL COMMENT 'Output from last execution',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service_id (service_id),
    INDEX idx_is_active (is_active),
    INDEX idx_last_run (last_run)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- RESELLER STATS TABLE (Resource Usage Tracking)
-- =====================================================
CREATE TABLE IF NOT EXISTS reseller_stats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT UNSIGNED NOT NULL,
    
    -- User counts
    total_users INT UNSIGNED DEFAULT 0,
    active_users INT UNSIGNED DEFAULT 0,
    suspended_users INT UNSIGNED DEFAULT 0,
    
    -- Resource usage (aggregated from all users)
    total_disk_used INT UNSIGNED DEFAULT 0 COMMENT 'MB',
    total_bandwidth_used INT UNSIGNED DEFAULT 0 COMMENT 'MB',
    total_databases INT UNSIGNED DEFAULT 0,
    total_domains INT UNSIGNED DEFAULT 0,
    total_services INT UNSIGNED DEFAULT 0,
    
    -- Package counts
    total_packages INT UNSIGNED DEFAULT 0,
    
    -- Last calculated
    last_calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (reseller_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reseller (reseller_id),
    INDEX idx_reseller_id (reseller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- RESELLER PERMISSIONS TABLE (Custom Permissions)
-- =====================================================
CREATE TABLE IF NOT EXISTS reseller_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT UNSIGNED NOT NULL,
    
    -- Permission flags (0=denied, 1=allowed)
    can_create_users TINYINT(1) DEFAULT 1,
    can_suspend_users TINYINT(1) DEFAULT 1,
    can_terminate_users TINYINT(1) DEFAULT 0,
    can_create_packages TINYINT(1) DEFAULT 1,
    can_manage_dns TINYINT(1) DEFAULT 1,
    can_manage_databases TINYINT(1) DEFAULT 1,
    can_view_logs TINYINT(1) DEFAULT 1,
    can_create_backups TINYINT(1) DEFAULT 1,
    
    -- Resource limits override (NULL = use package limits)
    max_users_override INT UNSIGNED DEFAULT NULL,
    max_disk_override INT UNSIGNED DEFAULT NULL,
    max_bandwidth_override INT UNSIGNED DEFAULT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (reseller_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reseller_perm (reseller_id),
    INDEX idx_reseller_id (reseller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FOREIGN KEY: users.owner_id -> users.id
-- =====================================================
-- Added after table creation to avoid circular reference issues
ALTER TABLE users ADD CONSTRAINT fk_owner_id 
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL;

-- =====================================================
-- DEFAULT DATA
-- =====================================================

-- Default Packages
INSERT INTO packages (id, name, description, type, cpu_limit, memory_limit, storage_limit, bandwidth_limit, max_services, db_limit, max_domains, max_subdomains, max_addon_domains, price, is_global) VALUES
(1, 'Starter', 'Perfect for small applications and testing', 'user', 1.00, 512, 2048, 20480, 1, 2, 3, 5, 1, 0.00, 1),
(2, 'Developer', 'Ideal for developers and small projects', 'user', 2.00, 1024, 5120, 51200, 3, 5, 5, 10, 3, 9.99, 1),
(3, 'Professional', 'For growing applications and businesses', 'user', 4.00, 2048, 10240, 102400, 10, 15, 15, 25, 10, 24.99, 1),
(4, 'Business', 'For production workloads and enterprises', 'user', 8.00, 4096, 25600, 256000, 25, 50, 50, 100, 25, 49.99, 1),
(5, 'Reseller Starter', 'Start your own hosting business', 'reseller', 4.00, 8192, 102400, 1048576, 50, 100, 100, 200, 50, 99.99, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- Default Settings
INSERT INTO settings (`key`, `value`, `type`, `group`, description) VALUES
('app_name', 'LogicPanel', 'string', 'general', 'Application name'),
('maintenance_mode', '0', 'boolean', 'general', 'Enable maintenance mode'),
('allow_registration', '1', 'boolean', 'users', 'Allow new user registration'),
('default_package_id', '1', 'integer', 'users', 'Default package for new users'),
('smtp_host', '', 'string', 'email', 'SMTP server host'),
('smtp_port', '587', 'integer', 'email', 'SMTP server port'),
('smtp_username', '', 'string', 'email', 'SMTP username'),
('smtp_password', '', 'string', 'email', 'SMTP password (encrypted)'),
('max_login_attempts', '5', 'integer', 'security', 'Maximum failed login attempts before lockout'),
('lockout_duration', '900', 'integer', 'security', 'Account lockout duration in seconds')
ON DUPLICATE KEY UPDATE `key` = VALUES(`key`);
