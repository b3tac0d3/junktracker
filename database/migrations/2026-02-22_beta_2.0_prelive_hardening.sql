-- JunkTracker Beta 2.0 pre-live hardening bundle
-- Adds performance indexes for support queue / bug notes / activity filters.
-- Safe to run multiple times in phpMyAdmin.

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(190) NOT NULL,
    checksum VARCHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_schema_migrations_key (migration_key),
    KEY idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @schema := DATABASE();

-- user_actions: user + action + date filters
SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'user_actions'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'user_actions'
          AND INDEX_NAME = 'idx_user_actions_user_action_created'
    ),
    'ALTER TABLE user_actions ADD INDEX idx_user_actions_user_action_created (user_id, action_key, created_at)',
    'SELECT 1'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- site_admin_tickets: admin unread counters and queue filtering
SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_tickets'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_tickets'
          AND INDEX_NAME = 'idx_site_admin_tickets_status_customer_admin'
    ),
    'ALTER TABLE site_admin_tickets ADD INDEX idx_site_admin_tickets_status_customer_admin (status, last_customer_note_at, last_admin_note_at)',
    'SELECT 1'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_tickets'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_tickets'
          AND INDEX_NAME = 'idx_site_admin_tickets_business_status_updated'
    ),
    'ALTER TABLE site_admin_tickets ADD INDEX idx_site_admin_tickets_business_status_updated (business_id, status, updated_at)',
    'SELECT 1'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- site_admin_ticket_notes: ticket thread reads
SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_ticket_notes'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_ticket_notes'
          AND INDEX_NAME = 'idx_site_admin_ticket_notes_ticket_visibility_created'
    ),
    'ALTER TABLE site_admin_ticket_notes ADD INDEX idx_site_admin_ticket_notes_ticket_visibility_created (ticket_id, visibility, created_at)',
    'SELECT 1'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- dev_bug_notes: bug detail timeline reads
SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'dev_bug_notes'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'dev_bug_notes'
          AND INDEX_NAME = 'idx_dev_bug_notes_bug_created'
    ),
    'ALTER TABLE dev_bug_notes ADD INDEX idx_dev_bug_notes_bug_created (bug_id, created_at)',
    'SELECT 1'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at) VALUES
('2026-02-22_beta_2.0_prelive_hardening', 'beta-2.0-prelive-hardening-v1', NOW());
