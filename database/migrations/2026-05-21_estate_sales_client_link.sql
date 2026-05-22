-- Link estate sales to clients (estate owner / client share recipient)
-- Date: 2026-05-21

START TRANSACTION;

SET @schema := DATABASE();

-- Add estate_sales.client_id
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
              AND COLUMN_NAME = 'client_id'
        ),
        'SELECT 1',
        'ALTER TABLE estate_sales ADD COLUMN client_id BIGINT UNSIGNED NULL AFTER client_percentage'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- Index estate_sales.client_id
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
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'estate_sales'
              AND INDEX_NAME = 'idx_estate_sales_client'
        ),
        'SELECT 1',
        'ALTER TABLE estate_sales ADD KEY idx_estate_sales_client (business_id, client_id)'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- FK: estate_sales.client_id -> clients.id
SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sales'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'clients'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'estate_sales'
              AND CONSTRAINT_NAME = 'fk_estate_sales_client'
        ),
        'SELECT 1',
        'ALTER TABLE estate_sales
            ADD CONSTRAINT fk_estate_sales_client
            FOREIGN KEY (client_id) REFERENCES clients(id)
            ON UPDATE CASCADE
            ON DELETE SET NULL'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
