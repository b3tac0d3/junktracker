-- Add 'converted' to invoices.status ENUM for estimates converted to invoices.
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
        'unsent','partially_paid','paid_in_full',
        'partial','paid','cancelled'
      ) NOT NULL DEFAULT 'draft'",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
