-- Remove sales fee defaults feature (manual gross/net only)
-- Run after 2026-03-29_sales_fee_defaults.sql if that was applied.

SET NAMES utf8mb4;
SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_fees := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'business_sale_type_fees'
);

SET @sql := IF(
    @has_fees = 0,
    'SELECT 1',
    'DROP TABLE business_sale_type_fees'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_val := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'sale_fee_value'
);

SET @sql := IF(
    @has_val = 0,
    'SELECT 1',
    'ALTER TABLE sales DROP COLUMN sale_fee_value'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mode := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'sale_fee_mode'
);

SET @sql := IF(
    @has_mode = 0,
    'SELECT 1',
    'ALTER TABLE sales DROP COLUMN sale_fee_mode'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-03-30_drop_sales_fee_defaults', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
