-- v3 Phase D: Link sales to jobs/purchases and add purchase price support
-- Date: 2026-03-11

START TRANSACTION;

SET @schema := DATABASE();

-- Add sales.job_id
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
          AND COLUMN_NAME = 'job_id'
    ),
    'SELECT 1',
    'ALTER TABLE sales ADD COLUMN job_id BIGINT UNSIGNED NULL AFTER client_id'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- Add sales.purchase_id
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
          AND COLUMN_NAME = 'purchase_id'
    ),
    'SELECT 1',
    'ALTER TABLE sales ADD COLUMN purchase_id BIGINT UNSIGNED NULL AFTER job_id'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- Add purchases.purchase_price
SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'purchases'
    ) = 0,
    'SELECT 1',
    IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'purchases'
          AND COLUMN_NAME = 'purchase_price'
    ),
    'SELECT 1',
    'ALTER TABLE purchases ADD COLUMN purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER purchase_date'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- Add index for sales business+job
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
          AND COLUMN_NAME = 'job_id'
    ) = 0,
    'SELECT 1',
    IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
          AND INDEX_NAME = 'idx_sales_business_job'
    ),
    'SELECT 1',
    'ALTER TABLE sales ADD INDEX idx_sales_business_job (business_id, job_id)'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- Add index for sales business+purchase
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
          AND COLUMN_NAME = 'purchase_id'
    ) = 0,
    'SELECT 1',
    IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
          AND INDEX_NAME = 'idx_sales_business_purchase'
    ),
    'SELECT 1',
    'ALTER TABLE sales ADD INDEX idx_sales_business_purchase (business_id, purchase_id)'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- FK: sales.job_id -> jobs.id
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
          AND TABLE_NAME = 'jobs'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
          AND COLUMN_NAME = 'job_id'
    ) = 0,
    'SELECT 1',
    IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
          AND CONSTRAINT_NAME = 'fk_sales_job'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'SELECT 1',
    'ALTER TABLE sales
       ADD CONSTRAINT fk_sales_job
       FOREIGN KEY (job_id)
       REFERENCES jobs(id)
       ON DELETE SET NULL
       ON UPDATE CASCADE'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- FK: sales.purchase_id -> purchases.id
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
          AND TABLE_NAME = 'purchases'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
          AND COLUMN_NAME = 'purchase_id'
    ) = 0,
    'SELECT 1',
    IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @schema
          AND TABLE_NAME = 'sales'
          AND CONSTRAINT_NAME = 'fk_sales_purchase'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'SELECT 1',
    'ALTER TABLE sales
       ADD CONSTRAINT fk_sales_purchase
       FOREIGN KEY (purchase_id)
       REFERENCES purchases(id)
       ON DELETE SET NULL
       ON UPDATE CASCADE'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
