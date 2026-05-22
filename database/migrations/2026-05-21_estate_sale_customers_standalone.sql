-- Estate sale customers: standalone contact records (not linked to clients)
-- Safe to run multiple times.

START TRANSACTION;

SET @schema := DATABASE();

SET @has_table := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'estate_sale_customers'
);

SET @drop_fk_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND CONSTRAINT_NAME = 'fk_estate_sale_customers_client'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) > 0,
    'ALTER TABLE estate_sale_customers DROP FOREIGN KEY fk_estate_sale_customers_client',
    'SELECT 1'
);
PREPARE jt_stmt FROM @drop_fk_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @drop_client_idx_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND INDEX_NAME = 'idx_estate_sale_customers_client'
    ) > 0,
    'ALTER TABLE estate_sale_customers DROP INDEX idx_estate_sale_customers_client',
    'SELECT 1'
);
PREPARE jt_stmt FROM @drop_client_idx_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @drop_uniq_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND INDEX_NAME = 'uniq_estate_sale_customer'
    ) > 0,
    'ALTER TABLE estate_sale_customers DROP INDEX uniq_estate_sale_customer',
    'SELECT 1'
);
PREPARE jt_stmt FROM @drop_uniq_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @drop_client_col_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'client_id'
    ) > 0,
    'ALTER TABLE estate_sale_customers DROP COLUMN client_id',
    'SELECT 1'
);
PREPARE jt_stmt FROM @drop_client_col_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @drop_checked_in_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'checked_in_at'
    ) > 0,
    'ALTER TABLE estate_sale_customers DROP COLUMN checked_in_at',
    'SELECT 1'
);
PREPARE jt_stmt FROM @drop_checked_in_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @add_first_name_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'first_name'
    ) = 0,
    'ALTER TABLE estate_sale_customers ADD COLUMN first_name VARCHAR(90) NULL AFTER estate_sale_id',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_first_name_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @add_last_name_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'last_name'
    ) = 0,
    'ALTER TABLE estate_sale_customers ADD COLUMN last_name VARCHAR(90) NULL AFTER first_name',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_last_name_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @add_email_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'email'
    ) = 0,
    'ALTER TABLE estate_sale_customers ADD COLUMN email VARCHAR(190) NULL AFTER last_name',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_email_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @add_phone_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'phone'
    ) = 0,
    'ALTER TABLE estate_sale_customers ADD COLUMN phone VARCHAR(40) NULL AFTER email',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_phone_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @add_city_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'city'
    ) = 0,
    'ALTER TABLE estate_sale_customers ADD COLUMN city VARCHAR(120) NULL AFTER phone',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_city_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @add_state_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'state'
    ) = 0,
    'ALTER TABLE estate_sale_customers ADD COLUMN state VARCHAR(60) NULL AFTER city',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_state_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @add_sale_idx_sql := IF(
    @has_table > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND INDEX_NAME = 'idx_estate_sale_customers_name'
    ) = 0,
    'ALTER TABLE estate_sale_customers ADD INDEX idx_estate_sale_customers_name (business_id, last_name, first_name)',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_sale_idx_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
