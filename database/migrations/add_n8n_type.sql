-- Migration: Add n8n to services.type ENUM
-- Run this on existing databases to add n8n support

ALTER TABLE services
    MODIFY COLUMN type ENUM('nodejs', 'python', 'static', 'php', 'n8n') NOT NULL DEFAULT 'nodejs';
