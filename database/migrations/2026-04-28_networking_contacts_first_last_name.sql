-- Networking contacts: split name into first_name / last_name
-- Safe to run multiple times.

SET @add_first_name := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'networking_contacts'
      AND COLUMN_NAME = 'first_name'
  ),
  'SELECT 1',
  'ALTER TABLE networking_contacts ADD COLUMN first_name VARCHAR(90) NULL AFTER business_id'
);
PREPARE stmt_add_first_name FROM @add_first_name;
EXECUTE stmt_add_first_name;
DEALLOCATE PREPARE stmt_add_first_name;

SET @add_last_name := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'networking_contacts'
      AND COLUMN_NAME = 'last_name'
  ),
  'SELECT 1',
  'ALTER TABLE networking_contacts ADD COLUMN last_name VARCHAR(90) NULL AFTER first_name'
);
PREPARE stmt_add_last_name FROM @add_last_name;
EXECUTE stmt_add_last_name;
DEALLOCATE PREPARE stmt_add_last_name;

UPDATE networking_contacts
SET first_name = CASE
      WHEN COALESCE(TRIM(first_name), '') = '' THEN TRIM(SUBSTRING_INDEX(COALESCE(name, ''), ' ', 1))
      ELSE first_name
    END,
    last_name = CASE
      WHEN COALESCE(TRIM(last_name), '') = '' THEN TRIM(SUBSTRING(COALESCE(name, ''), LENGTH(SUBSTRING_INDEX(COALESCE(name, ''), ' ', 1)) + 1))
      ELSE last_name
    END
WHERE COALESCE(TRIM(name), '') <> '';
