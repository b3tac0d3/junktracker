-- Add "declined" to invoice status enum for estimate/invoice workflows.

SET @has_invoices := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
);

SET @sql := IF(
    @has_invoices > 0,
    "ALTER TABLE invoices
     MODIFY COLUMN status ENUM('draft','sent','approved','declined','partial','paid','cancelled')
     NOT NULL DEFAULT 'draft'",
    "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

