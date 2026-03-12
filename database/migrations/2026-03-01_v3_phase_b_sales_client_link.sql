-- v3 Phase B: Link sales to optional client
-- Date: 2026-03-01

START TRANSACTION;

SET @sales_table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sales'
);

SET @add_client_column_sql := IF(
    @sales_table_exists > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sales'
          AND COLUMN_NAME = 'client_id'
    ) = 0,
    'ALTER TABLE sales ADD COLUMN client_id BIGINT UNSIGNED NULL AFTER business_id',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_client_column_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @add_client_index_sql := IF(
    @sales_table_exists > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sales'
          AND INDEX_NAME = 'idx_sales_business_client'
    ) = 0,
    'ALTER TABLE sales ADD INDEX idx_sales_business_client (business_id, client_id)',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_client_index_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @add_client_fk_sql := IF(
    @sales_table_exists > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sales'
          AND CONSTRAINT_NAME = 'fk_sales_client'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) = 0,
    'ALTER TABLE sales ADD CONSTRAINT fk_sales_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_client_fk_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
