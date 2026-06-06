-- Sub-contractor address fields
-- Safe to run multiple times.

SET NAMES utf8mb4;

SET @db := DATABASE();

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'subcontractors' AND COLUMN_NAME = 'address_line1') = 0,
    'ALTER TABLE subcontractors
        ADD COLUMN address_line1 VARCHAR(190) NULL AFTER company,
        ADD COLUMN address_line2 VARCHAR(190) NULL AFTER address_line1,
        ADD COLUMN city VARCHAR(120) NULL AFTER address_line2,
        ADD COLUMN state VARCHAR(30) NULL AFTER city,
        ADD COLUMN postal_code VARCHAR(30) NULL AFTER state',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
