-- v3 Phase B - Expense name and payment method
-- Date: 2026-03-04

SET NAMES utf8mb4;

SET @add_name_col := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'expenses'
              AND COLUMN_NAME = 'name'
        ),
        'SELECT 1',
        'ALTER TABLE expenses ADD COLUMN name VARCHAR(120) NULL AFTER job_id'
    )
);
PREPARE stmt_exp_name FROM @add_name_col;
EXECUTE stmt_exp_name;
DEALLOCATE PREPARE stmt_exp_name;

SET @add_payment_method_col := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'expenses'
              AND COLUMN_NAME = 'payment_method'
        ),
        'SELECT 1',
        'ALTER TABLE expenses ADD COLUMN payment_method VARCHAR(80) NULL AFTER category'
    )
);
PREPARE stmt_exp_payment_method FROM @add_payment_method_col;
EXECUTE stmt_exp_payment_method;
DEALLOCATE PREPARE stmt_exp_payment_method;
