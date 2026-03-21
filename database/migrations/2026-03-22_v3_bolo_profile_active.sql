-- JunkTracker v3
-- BOLO profile active flag (deactivate hides from list/search)
-- Date: 2026-03-22

SET NAMES utf8mb4;
SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_profiles_table := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'client_bolo_profiles'
);
SET @has_is_active := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'client_bolo_profiles'
      AND COLUMN_NAME = 'is_active'
);
SET @sql := IF(
    @has_profiles_table = 0 OR @has_is_active > 0,
    'SELECT 1',
    'ALTER TABLE client_bolo_profiles ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER notes'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-03-22_v3_bolo_profile_active', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
