-- Estate sale financials: client percentage + expense linkage
-- Date: 2026-05-21

START TRANSACTION;

SET @schema := DATABASE();

-- Add estate_sales.client_percentage
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

-- Add expenses.estate_sale_id
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

-- Index: expenses business + estate sale
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

-- FK: expenses.estate_sale_id -> estate_sales.id
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
