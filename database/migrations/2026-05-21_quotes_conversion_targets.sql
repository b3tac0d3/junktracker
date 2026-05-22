-- Quote conversion targets: estate sale and purchase
-- Date: 2026-05-21

START TRANSACTION;

SET @schema := DATABASE();

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'quotes'
          AND COLUMN_NAME = 'converted_estate_sale_id'
    ),
    'SELECT 1',
    'ALTER TABLE quotes ADD COLUMN converted_estate_sale_id BIGINT UNSIGNED NULL AFTER converted_job_id'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'quotes'
          AND COLUMN_NAME = 'converted_purchase_id'
    ),
    'SELECT 1',
    'ALTER TABLE quotes ADD COLUMN converted_purchase_id BIGINT UNSIGNED NULL AFTER converted_estate_sale_id'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'quotes'
          AND INDEX_NAME = 'idx_quotes_converted_estate_sale'
    ),
    'SELECT 1',
    'ALTER TABLE quotes ADD INDEX idx_quotes_converted_estate_sale (converted_estate_sale_id)'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'quotes'
          AND INDEX_NAME = 'idx_quotes_converted_purchase'
    ),
    'SELECT 1',
    'ALTER TABLE quotes ADD INDEX idx_quotes_converted_purchase (converted_purchase_id)'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
