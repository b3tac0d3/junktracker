-- Business logo for estimates/invoices (path relative to public/)
-- Date: 2026-03-23

SET NAMES utf8mb4;
SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_logo := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'businesses'
      AND COLUMN_NAME = 'logo_path'
);
SET @sql := IF(
    @has_logo > 0,
    'SELECT 1',
    'ALTER TABLE businesses ADD COLUMN logo_path VARCHAR(512) NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-03-23_v3_business_logo_path', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
