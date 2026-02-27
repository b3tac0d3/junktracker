-- JunkTracker v3 Phase B
-- Client profile fields for detail view
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

SET @has_secondary_phone := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'secondary_phone'
);
SET @sql := IF(
    @has_secondary_phone > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN secondary_phone VARCHAR(40) NULL AFTER phone'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_can_text := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'can_text'
);
SET @sql := IF(
    @has_can_text > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN can_text TINYINT(1) NOT NULL DEFAULT 0 AFTER secondary_phone'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_primary_note := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'primary_note'
);
SET @sql := IF(
    @has_primary_note > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN primary_note TEXT NULL AFTER can_text'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_notes := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'notes'
);
SET @has_note := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'note'
);

SET @sql := IF(
    @has_notes > 0,
    "UPDATE clients
     SET primary_note = COALESCE(NULLIF(primary_note, ''), notes)
     WHERE (primary_note IS NULL OR primary_note = '')
       AND notes IS NOT NULL
       AND notes <> ''",
    IF(
        @has_note > 0,
        "UPDATE clients
         SET primary_note = COALESCE(NULLIF(primary_note, ''), note)
         WHERE (primary_note IS NULL OR primary_note = '')
           AND note IS NOT NULL
           AND note <> ''",
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-02-27_v3_phase_b_clients_profile_fields', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);

