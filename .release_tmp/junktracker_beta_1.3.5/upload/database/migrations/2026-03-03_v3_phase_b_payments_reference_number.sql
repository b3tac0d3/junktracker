-- Payments: add optional reference_number (check # / Venmo ID / etc).
SET @schema := DATABASE();

SET @has_payments := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'payments'
);

SET @has_reference := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'reference_number'
);

SET @sql := IF(
    @has_payments = 1 AND @has_reference = 0,
    'ALTER TABLE payments ADD COLUMN reference_number VARCHAR(120) NULL AFTER method',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
