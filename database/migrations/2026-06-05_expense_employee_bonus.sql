-- Job expense employee link (bonus payouts per employee)
-- Date: 2026-06-05

SET NAMES utf8mb4;

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE expenses ADD COLUMN employee_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER job_id',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'expenses'
      AND COLUMN_NAME = 'employee_id'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE expenses ADD INDEX idx_expenses_business_employee (business_id, employee_id)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'expenses'
      AND INDEX_NAME = 'idx_expenses_business_employee'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE expenses ADD CONSTRAINT fk_expenses_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL ON UPDATE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'expenses'
      AND CONSTRAINT_NAME = 'fk_expenses_employee'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
