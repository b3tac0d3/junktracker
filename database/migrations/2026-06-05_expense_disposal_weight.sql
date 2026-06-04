-- Disposal expense weight (pounds hauled from job)
-- Date: 2026-06-05

SET NAMES utf8mb4;

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE expenses ADD COLUMN weight DECIMAL(12,3) NULL DEFAULT NULL AFTER amount',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'expenses'
      AND COLUMN_NAME = 'weight'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
