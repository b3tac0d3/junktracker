-- v3 Phase C - Job adjustment name field
-- Date: 2026-03-06

SET NAMES utf8mb4;

SET @add_name_col := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'job_adjustments'
              AND COLUMN_NAME = 'name'
        ),
        'SELECT 1',
        'ALTER TABLE job_adjustments ADD COLUMN name VARCHAR(120) NULL AFTER job_id'
    )
);
PREPARE stmt_job_adj_name FROM @add_name_col;
EXECUTE stmt_job_adj_name;
DEALLOCATE PREPARE stmt_job_adj_name;
