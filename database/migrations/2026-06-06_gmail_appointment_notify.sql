-- Gmail appointment notification preferences (per connected Google user)
-- Date: 2026-06-06

SET NAMES utf8mb4;

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE user_google_calendar_connections ADD COLUMN appointment_gmail_notify_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER calendar_id',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'user_google_calendar_connections'
      AND COLUMN_NAME = 'appointment_gmail_notify_enabled'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE user_google_calendar_connections ADD COLUMN appointment_gmail_notify_to VARCHAR(500) NULL DEFAULT NULL AFTER appointment_gmail_notify_enabled',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'user_google_calendar_connections'
      AND COLUMN_NAME = 'appointment_gmail_notify_to'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
