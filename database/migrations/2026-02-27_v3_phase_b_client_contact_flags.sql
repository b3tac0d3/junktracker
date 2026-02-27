-- JunkTracker v3 Phase B
-- Client contact fields: email + secondary can-text flag
-- Date: 2026-02-27

SET NAMES utf8mb4;
SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_email := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'email'
);
SET @sql := IF(
    @has_email > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN email VARCHAR(190) NULL AFTER last_name'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_secondary_can_text := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'secondary_can_text'
);
SET @sql := IF(
    @has_secondary_can_text > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN secondary_can_text TINYINT(1) NOT NULL DEFAULT 0 AFTER can_text'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-02-27_v3_phase_b_client_contact_flags', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);

