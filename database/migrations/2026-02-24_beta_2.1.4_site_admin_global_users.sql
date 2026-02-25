-- beta 2.1.4: make site-admin/dev users global (business_id = 0) and keep company users scoped.
SET @schema := DATABASE();

-- Ensure users.business_id exists.
SET @has_users_business_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'business_id'
);
SET @sql := IF(
    @has_users_business_id = 0,
    'ALTER TABLE users ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER role',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Keep business users attached to a company when missing.
SET @sql := IF(
    @has_users_business_id > 0,
    'UPDATE users
     SET business_id = 1
     WHERE (business_id IS NULL OR business_id = 0)
       AND (role IS NULL OR role < 4)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Site admin/dev accounts are global and must not be tied to a specific company.
SET @sql := IF(
    @has_users_business_id > 0,
    'UPDATE users
     SET business_id = 0
     WHERE role >= 4',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Helpful index for company user directory and global user directory lookups.
SET @has_idx_users_business_role := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_business_role'
);
SET @sql := IF(
    @has_idx_users_business_role = 0,
    'CREATE INDEX idx_users_business_role ON users (business_id, role)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
