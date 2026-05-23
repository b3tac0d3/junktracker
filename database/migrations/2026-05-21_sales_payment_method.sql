-- Estate sale transaction payment method (cash, venmo, etc.)
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
              AND COLUMN_NAME = 'payment_method'
        ),
        'SELECT 1',
        'ALTER TABLE sales ADD COLUMN payment_method VARCHAR(40) NOT NULL DEFAULT ''cash'' AFTER notes'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
