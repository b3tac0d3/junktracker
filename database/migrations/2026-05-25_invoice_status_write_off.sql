-- Add write_off invoice status (Non Payment / Write Off).
-- Safe to run multiple times (no-op if already present).

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'invoices'
          AND COLUMN_NAME = 'status'
    ),
    "ALTER TABLE invoices
      MODIFY COLUMN status ENUM(
        'draft','sent','approved','declined','converted',
        'unsent','partially_paid','paid_in_full','write_off',
        'partial','paid','cancelled'
      ) NOT NULL DEFAULT 'draft'",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Seed invoice_status option for existing businesses (form_select_values).
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
    'billing',
    'invoice_status',
    'write_off',
    50,
    1,
    NOW(),
    NOW()
FROM businesses b
WHERE b.deleted_at IS NULL
  AND EXISTS (
      SELECT 1
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'form_select_values'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM form_select_values v
      WHERE v.business_id = b.id
        AND v.form_key = 'billing'
        AND v.section_key = 'invoice_status'
        AND v.deleted_at IS NULL
        AND LOWER(v.option_value) = 'write_off'
  );
