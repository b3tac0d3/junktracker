-- Link sales to estate sales and estate sale customers
-- Date: 2026-05-21

START TRANSACTION;

SET @schema := DATABASE();

-- Add sales.estate_sale_id
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
              AND COLUMN_NAME = 'estate_sale_id'
        ),
        'SELECT 1',
        'ALTER TABLE sales ADD COLUMN estate_sale_id BIGINT UNSIGNED NULL AFTER purchase_id'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- Add sales.estate_sale_customer_id
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
              AND COLUMN_NAME = 'estate_sale_customer_id'
        ),
        'SELECT 1',
        'ALTER TABLE sales ADD COLUMN estate_sale_customer_id BIGINT UNSIGNED NULL AFTER estate_sale_id'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- Index: sales business + estate sale
SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
          AND COLUMN_NAME = 'business_id'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
          AND COLUMN_NAME = 'estate_sale_id'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'sales'
              AND INDEX_NAME = 'idx_sales_business_estate_sale'
        ),
        'SELECT 1',
        'ALTER TABLE sales ADD INDEX idx_sales_business_estate_sale (business_id, estate_sale_id)'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- FK: sales.estate_sale_id -> estate_sales.id
SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sales'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
          AND COLUMN_NAME = 'estate_sale_id'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = @schema
              AND TABLE_NAME = 'sales'
              AND CONSTRAINT_NAME = 'fk_sales_estate_sale'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ),
        'SELECT 1',
        'ALTER TABLE sales
           ADD CONSTRAINT fk_sales_estate_sale
           FOREIGN KEY (estate_sale_id)
           REFERENCES estate_sales(id)
           ON DELETE SET NULL
           ON UPDATE CASCADE'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- FK: sales.estate_sale_customer_id -> estate_sale_customers.id
SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
          AND COLUMN_NAME = 'estate_sale_customer_id'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = @schema
              AND TABLE_NAME = 'sales'
              AND CONSTRAINT_NAME = 'fk_sales_estate_sale_customer'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ),
        'SELECT 1',
        'ALTER TABLE sales
           ADD CONSTRAINT fk_sales_estate_sale_customer
           FOREIGN KEY (estate_sale_customer_id)
           REFERENCES estate_sale_customers(id)
           ON DELETE SET NULL
           ON UPDATE CASCADE'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
