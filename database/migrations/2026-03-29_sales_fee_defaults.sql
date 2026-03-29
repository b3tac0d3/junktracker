-- Default sale-type fees (business admin) + per-sale fee mode
-- Date: 2026-03-29

SET NAMES utf8mb4;
SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_fees_table := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'business_sale_type_fees'
);

SET @sql := IF(
    @has_fees_table > 0,
    'SELECT 1',
    'CREATE TABLE business_sale_type_fees (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        business_id BIGINT UNSIGNED NOT NULL,
        sale_type VARCHAR(60) NOT NULL,
        fee_kind ENUM(''percent'', ''amount'') NOT NULL,
        fee_value DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_bstf_business_type (business_id, sale_type),
        KEY idx_bstf_business (business_id),
        CONSTRAINT fk_bstf_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_sales := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'sales'
);

SET @has_mode := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'sale_fee_mode'
);

SET @sql := IF(
    @has_sales = 0 OR @has_mode > 0,
    'SELECT 1',
    'ALTER TABLE sales
        ADD COLUMN sale_fee_mode VARCHAR(16) NOT NULL DEFAULT ''default'' AFTER net_amount,
        ADD COLUMN sale_fee_value DECIMAL(12,4) NULL AFTER sale_fee_mode'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @has_sales = 0 OR @has_mode > 0,
    'SELECT 1',
    'UPDATE sales SET
        sale_fee_mode = CASE
            WHEN gross_amount > net_amount + 0.0001 THEN ''amount''
            ELSE ''none''
        END,
        sale_fee_value = CASE
            WHEN gross_amount > net_amount + 0.0001 THEN gross_amount - net_amount
            ELSE NULL
        END
     WHERE deleted_at IS NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-03-29_sales_fee_defaults', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
