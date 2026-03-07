-- Payments: add payment_type (deposit/payment) for reporting + UX.
SET @schema := DATABASE();

SET @has_payments := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'payments'
);

SET @has_payment_type := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'payment_type'
);

SET @sql := IF(
    @has_payments = 1 AND @has_payment_type = 0,
    "ALTER TABLE payments ADD COLUMN payment_type VARCHAR(20) NOT NULL DEFAULT 'payment' AFTER paid_at",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

