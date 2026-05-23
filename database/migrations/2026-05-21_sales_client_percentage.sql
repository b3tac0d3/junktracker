-- Per-transaction client percentage override for estate sale sales
-- Date: 2026-05-21

START TRANSACTION;

SET @schema := DATABASE();

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'sales'
              AND COLUMN_NAME = 'client_percentage'
        ),
        'SELECT 1',
        'ALTER TABLE sales ADD COLUMN client_percentage DECIMAL(5,2) NULL AFTER estate_sale_customer_id'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
