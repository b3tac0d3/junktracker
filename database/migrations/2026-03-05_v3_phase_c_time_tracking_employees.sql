-- v3 Phase C - Employee profile fields for time tracking
-- Date: 2026-03-05

SET NAMES utf8mb4;

SET @add_suffix_col := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'employees'
              AND COLUMN_NAME = 'suffix'
        ),
        'SELECT 1',
        'ALTER TABLE employees ADD COLUMN suffix VARCHAR(20) NULL AFTER last_name'
    )
);
PREPARE stmt_emp_add_suffix FROM @add_suffix_col;
EXECUTE stmt_emp_add_suffix;
DEALLOCATE PREPARE stmt_emp_add_suffix;

SET @add_note_col := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'employees'
              AND COLUMN_NAME = 'note'
        ),
        'SELECT 1',
        'ALTER TABLE employees ADD COLUMN note TEXT NULL AFTER user_id'
    )
);
PREPARE stmt_emp_add_note FROM @add_note_col;
EXECUTE stmt_emp_add_note;
DEALLOCATE PREPARE stmt_emp_add_note;
