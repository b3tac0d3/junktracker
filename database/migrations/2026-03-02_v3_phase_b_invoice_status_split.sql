-- Invoice statuses are now separate from estimate statuses.
-- Estimates: draft, sent, approved, declined.
-- Invoices: unsent, sent, partially_paid, paid_in_full.

SET @schema := DATABASE();

-- Ensure invoices.status can store both legacy and new values safely.
SET @alter_sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'invoices'
          AND COLUMN_NAME = 'status'
    ),
    "ALTER TABLE invoices
      MODIFY COLUMN status ENUM(
        'draft','sent','approved','declined',
        'unsent','partially_paid','paid_in_full',
        'partial','paid','cancelled'
      ) NOT NULL DEFAULT 'draft'",
    'SELECT 1'
);
PREPARE stmt FROM @alter_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill legacy invoice rows into the new invoice status names.
UPDATE invoices
SET status = 'unsent'
WHERE LOWER(COALESCE(type, 'invoice')) = 'invoice'
  AND status = 'draft';

UPDATE invoices
SET status = 'partially_paid'
WHERE LOWER(COALESCE(type, 'invoice')) = 'invoice'
  AND status = 'partial';

UPDATE invoices
SET status = 'paid_in_full'
WHERE LOWER(COALESCE(type, 'invoice')) = 'invoice'
  AND status = 'paid';
