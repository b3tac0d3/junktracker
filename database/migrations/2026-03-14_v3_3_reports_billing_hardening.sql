-- v3.3 reporting + billing hardening
-- Safe to run multiple times.

-- Ensure invoices.status supports both estimate and invoice lifecycle values.
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
        'draft','sent','approved','declined',
        'unsent','partially_paid','paid_in_full',
        'partial','paid','cancelled'
      ) NOT NULL DEFAULT 'draft'",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Normalize legacy invoice statuses for invoice-type records.
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

-- Optional performance indexes for reporting and billing lookups.
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'invoices'
          AND INDEX_NAME = 'idx_invoices_reporting'
    ),
    'SELECT 1',
    'ALTER TABLE invoices ADD INDEX idx_invoices_reporting (business_id, type, issue_date, deleted_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sales'
          AND INDEX_NAME = 'idx_sales_reporting'
    ),
    'SELECT 1',
    'ALTER TABLE sales ADD INDEX idx_sales_reporting (business_id, sale_date, deleted_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'expenses'
          AND INDEX_NAME = 'idx_expenses_reporting'
    ),
    'SELECT 1',
    'ALTER TABLE expenses ADD INDEX idx_expenses_reporting (business_id, expense_date, deleted_at, job_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'purchases'
          AND INDEX_NAME = 'idx_purchases_reporting'
    ),
    'SELECT 1',
    'ALTER TABLE purchases ADD INDEX idx_purchases_reporting (business_id, purchase_date, deleted_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'jobs'
          AND INDEX_NAME = 'idx_jobs_reporting'
    ),
    'SELECT 1',
    'ALTER TABLE jobs ADD INDEX idx_jobs_reporting (business_id, scheduled_start_at, deleted_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'payments'
          AND INDEX_NAME = 'idx_payments_invoice_active'
    ),
    'SELECT 1',
    'ALTER TABLE payments ADD INDEX idx_payments_invoice_active (business_id, invoice_id, deleted_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
