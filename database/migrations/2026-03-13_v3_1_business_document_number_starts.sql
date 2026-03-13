-- JunkTracker v3.1
-- Business-level estimate/invoice number start seeds
-- Date: 2026-03-13

SET NAMES utf8mb4;
SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_estimate_start := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'businesses'
      AND COLUMN_NAME = 'estimate_number_start'
);
SET @sql := IF(
    @has_estimate_start > 0,
    'SELECT 1',
    'ALTER TABLE businesses ADD COLUMN estimate_number_start VARCHAR(30) NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_invoice_start := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'businesses'
      AND COLUMN_NAME = 'invoice_number_start'
);
SET @sql := IF(
    @has_invoice_start > 0,
    'SELECT 1',
    'ALTER TABLE businesses ADD COLUMN invoice_number_start VARCHAR(30) NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-03-13_v3_1_business_document_number_starts', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
