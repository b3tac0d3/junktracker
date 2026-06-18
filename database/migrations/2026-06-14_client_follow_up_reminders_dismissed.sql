-- Track when user saved client edit without completing the dashboard follow-up prompt
-- Date: 2026-06-14

START TRANSACTION;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'client_follow_up_reminders'
      AND COLUMN_NAME = 'complete_prompt_dismissed_at'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE client_follow_up_reminders ADD COLUMN complete_prompt_dismissed_at DATETIME NULL AFTER completed_at',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
