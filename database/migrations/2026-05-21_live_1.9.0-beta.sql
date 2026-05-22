-- Live 1.9.0-beta: Estate Sales module + quote conversion targets
-- Idempotent bundle — safe to run once on production upgrading from 1.8.x.
-- Run this single file OR the individual 2026-05-21_*.sql files in the order listed
-- in docs/releases/live-1.9.0-beta.md.

-- =============================================================================
-- 1. Core estate sales tables + status form values
-- =============================================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS estate_sales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    status VARCHAR(60) NOT NULL DEFAULT 'scheduled',
    start_at DATETIME NULL,
    end_at DATETIME NULL,
    address_line1 VARCHAR(190) NULL,
    address_line2 VARCHAR(190) NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(60) NULL,
    postal_code VARCHAR(30) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_estate_sales_business (business_id),
    KEY idx_estate_sales_business_status (business_id, status),
    KEY idx_estate_sales_business_start (business_id, start_at),
    KEY idx_estate_sales_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_estate_sales_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estate_sale_customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    estate_sale_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(90) NULL,
    last_name VARCHAR(90) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(60) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_estate_sale_customers_sale (business_id, estate_sale_id),
    KEY idx_estate_sale_customers_name (business_id, last_name, first_name),
    KEY idx_estate_sale_customers_deleted (business_id, deleted_at),
    CONSTRAINT fk_estate_sale_customers_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_estate_sale_customers_sale FOREIGN KEY (estate_sale_id) REFERENCES estate_sales(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO form_select_values (
    business_id,
    form_key,
    section_key,
    option_value,
    sort_order,
    is_active,
    created_at,
    updated_at
)
SELECT
    b.id,
    d.form_key,
    d.section_key,
    d.option_value,
    d.sort_order,
    1,
    NOW(),
    NOW()
FROM businesses b
JOIN (
    SELECT 'estate_sales' AS form_key, 'estate_sale_status' AS section_key, 'scheduled' AS option_value, 10 AS sort_order
    UNION ALL SELECT 'estate_sales', 'estate_sale_status', 'active', 20
    UNION ALL SELECT 'estate_sales', 'estate_sale_status', 'complete', 30
    UNION ALL SELECT 'estate_sales', 'estate_sale_status', 'cancelled', 40
) d
WHERE NOT EXISTS (
    SELECT 1
    FROM form_select_values v
    WHERE v.business_id = b.id
      AND v.form_key = d.form_key
      AND v.section_key = d.section_key
      AND v.option_value = d.option_value
      AND v.deleted_at IS NULL
);

COMMIT;

-- =============================================================================
-- 2. Financials: client percentage, expense linkage, expense categories
-- =============================================================================

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
              AND COLUMN_NAME = 'client_percentage'
        ),
        'SELECT 1',
        'ALTER TABLE estate_sales ADD COLUMN client_percentage DECIMAL(5,2) NULL AFTER notes'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'expenses'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'expenses'
              AND COLUMN_NAME = 'estate_sale_id'
        ),
        'SELECT 1',
        'ALTER TABLE expenses ADD COLUMN estate_sale_id BIGINT UNSIGNED NULL AFTER job_id'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'expenses'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'expenses'
          AND COLUMN_NAME = 'business_id'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'expenses'
          AND COLUMN_NAME = 'estate_sale_id'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'expenses'
              AND INDEX_NAME = 'idx_expenses_business_estate_sale'
        ),
        'SELECT 1',
        'ALTER TABLE expenses ADD INDEX idx_expenses_business_estate_sale (business_id, estate_sale_id)'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'expenses'
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
          AND TABLE_NAME = 'expenses'
          AND COLUMN_NAME = 'estate_sale_id'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = @schema
              AND TABLE_NAME = 'expenses'
              AND CONSTRAINT_NAME = 'fk_expenses_estate_sale'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ),
        'SELECT 1',
        'ALTER TABLE expenses
           ADD CONSTRAINT fk_expenses_estate_sale
           FOREIGN KEY (estate_sale_id)
           REFERENCES estate_sales(id)
           ON DELETE SET NULL
           ON UPDATE CASCADE'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

INSERT INTO form_select_values (
    business_id,
    form_key,
    section_key,
    option_value,
    sort_order,
    is_active,
    created_at,
    updated_at
)
SELECT
    b.id,
    d.form_key,
    d.section_key,
    d.option_value,
    d.sort_order,
    1,
    NOW(),
    NOW()
FROM businesses b
JOIN (
    SELECT 'estate_sales' AS form_key, 'estate_sale_expense_category' AS section_key, 'Advertising' AS option_value, 10 AS sort_order
    UNION ALL SELECT 'estate_sales', 'estate_sale_expense_category', 'Setup', 20
    UNION ALL SELECT 'estate_sales', 'estate_sale_expense_category', 'Supplies', 30
    UNION ALL SELECT 'estate_sales', 'estate_sale_expense_category', 'Labor', 40
    UNION ALL SELECT 'estate_sales', 'estate_sale_expense_category', 'Utilities', 50
    UNION ALL SELECT 'estate_sales', 'estate_sale_expense_category', 'Other', 60
) d
WHERE NOT EXISTS (
    SELECT 1
    FROM form_select_values v
    WHERE v.business_id = b.id
      AND v.form_key = d.form_key
      AND v.section_key = d.section_key
      AND v.option_value = d.option_value
      AND v.deleted_at IS NULL
);

COMMIT;

-- =============================================================================
-- 3. Client split basis
-- =============================================================================

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

-- =============================================================================
-- 4. Link estate sales to clients (owner / share recipient)
-- =============================================================================

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
              AND COLUMN_NAME = 'client_id'
        ),
        'SELECT 1',
        'ALTER TABLE estate_sales ADD COLUMN client_id BIGINT UNSIGNED NULL AFTER client_percentage'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

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

-- =============================================================================
-- 5. Standalone estate sale customers (drop legacy client_id link if present)
-- =============================================================================

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

-- =============================================================================
-- 6. Labor: employee assignments + time entry linkage
-- =============================================================================

START TRANSACTION;

SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS estate_sale_employee_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    estate_sale_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_estate_sale_employee (business_id, estate_sale_id, employee_id),
    KEY idx_estate_sale_employee_assignments_sale (business_id, estate_sale_id),
    KEY idx_estate_sale_employee_assignments_employee (business_id, employee_id),
    KEY idx_estate_sale_employee_assignments_deleted (business_id, deleted_at),
    CONSTRAINT fk_estate_sale_employee_assignments_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_estate_sale_employee_assignments_sale FOREIGN KEY (estate_sale_id) REFERENCES estate_sales(id) ON UPDATE CASCADE,
    CONSTRAINT fk_estate_sale_employee_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'employee_time_entries'
              AND COLUMN_NAME = 'estate_sale_id'
        ),
        'SELECT 1',
        'ALTER TABLE employee_time_entries ADD COLUMN estate_sale_id BIGINT UNSIGNED NULL AFTER job_id'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'employee_time_entries'
              AND INDEX_NAME = 'idx_time_business_estate_sale'
        ),
        'SELECT 1',
        'ALTER TABLE employee_time_entries ADD INDEX idx_time_business_estate_sale (business_id, estate_sale_id)'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sales'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'employee_time_entries'
              AND CONSTRAINT_NAME = 'fk_time_estate_sale'
        ),
        'SELECT 1',
        'ALTER TABLE employee_time_entries
            ADD CONSTRAINT fk_time_estate_sale
            FOREIGN KEY (estate_sale_id) REFERENCES estate_sales(id)
            ON DELETE SET NULL
            ON UPDATE CASCADE'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;

-- =============================================================================
-- 7. Link sales to estate sales and customers
-- =============================================================================

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
              AND COLUMN_NAME = 'estate_sale_id'
        ),
        'SELECT 1',
        'ALTER TABLE sales ADD COLUMN estate_sale_id BIGINT UNSIGNED NULL AFTER purchase_id'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

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

-- =============================================================================
-- 8. Customer queue, check-in/out, visit log
-- =============================================================================

START TRANSACTION;

SET @schema := DATABASE();

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'queue_number'
    ),
    'SELECT 1',
    'ALTER TABLE estate_sale_customers ADD COLUMN queue_number INT UNSIGNED NOT NULL DEFAULT 0 AFTER estate_sale_id'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'checked_in_at'
    ),
    'SELECT 1',
    'ALTER TABLE estate_sale_customers ADD COLUMN checked_in_at DATETIME NULL AFTER notes'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'checked_out_at'
    ),
    'SELECT 1',
    'ALTER TABLE estate_sale_customers ADD COLUMN checked_out_at DATETIME NULL AFTER checked_in_at'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND INDEX_NAME = 'idx_estate_sale_customers_queue'
    ),
    'SELECT 1',
    'ALTER TABLE estate_sale_customers ADD INDEX idx_estate_sale_customers_queue (business_id, estate_sale_id, queue_number)'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

UPDATE estate_sale_customers esc
INNER JOIN (
    SELECT
        id,
        ROW_NUMBER() OVER (PARTITION BY estate_sale_id ORDER BY created_at ASC, id ASC) AS rn
    FROM estate_sale_customers
    WHERE deleted_at IS NULL
) ranked ON ranked.id = esc.id
SET esc.queue_number = ranked.rn
WHERE esc.deleted_at IS NULL
  AND esc.queue_number = 0;

CREATE TABLE IF NOT EXISTS estate_sale_customer_visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    estate_sale_id BIGINT UNSIGNED NOT NULL,
    estate_sale_customer_id BIGINT UNSIGNED NOT NULL,
    checked_in_at DATETIME NOT NULL,
    checked_out_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estate_sale_customer_visits_customer (business_id, estate_sale_id, estate_sale_customer_id),
    KEY idx_estate_sale_customer_visits_checked_in (business_id, checked_in_at),
    CONSTRAINT fk_estate_sale_customer_visits_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_estate_sale_customer_visits_sale FOREIGN KEY (estate_sale_id) REFERENCES estate_sales(id) ON UPDATE CASCADE,
    CONSTRAINT fk_estate_sale_customer_visits_customer FOREIGN KEY (estate_sale_customer_id) REFERENCES estate_sale_customers(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

-- =============================================================================
-- 9. Quote conversion targets (estate sale + purchase)
-- =============================================================================

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
