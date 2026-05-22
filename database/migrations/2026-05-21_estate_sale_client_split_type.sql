-- Estate sale client split basis (how client % is applied)
-- Date: 2026-05-21

START TRANSACTION;

SET @schema := DATABASE();

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sales'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'estate_sales'
              AND COLUMN_NAME = 'client_split_type'
        ),
        'SELECT 1',
        'ALTER TABLE estate_sales ADD COLUMN client_split_type VARCHAR(40) NOT NULL DEFAULT ''split_gross_total'' AFTER client_percentage'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
