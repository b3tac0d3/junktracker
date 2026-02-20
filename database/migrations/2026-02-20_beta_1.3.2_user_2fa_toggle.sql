-- JunkTracker Beta 1.3.2
-- Personal user-level 2FA toggle support.

SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(190) NOT NULL,
    checksum VARCHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_schema_migrations_key (migration_key),
    KEY idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE BINARY TABLE_SCHEMA = BINARY @schema
      AND BINARY TABLE_NAME = BINARY 'users'
      AND BINARY COLUMN_NAME = BINARY 'two_factor_enabled'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER last_login_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill in case column existed with NULL values in any environment.
UPDATE users
SET two_factor_enabled = 1
WHERE two_factor_enabled IS NULL;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at) VALUES
('2026-02-20_beta_1.3.2_user_2fa_toggle', 'beta-1.3.2-user-2fa-toggle-v1', NOW());
