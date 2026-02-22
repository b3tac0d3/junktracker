-- JunkTracker Beta 1.4.4: punch-only role + punch geolocation
-- Safe to run multiple times.

SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS role_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_value SMALLINT UNSIGNED NOT NULL,
    module_key VARCHAR(80) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 1,
    can_create TINYINT(1) NOT NULL DEFAULT 1,
    can_edit TINYINT(1) NOT NULL DEFAULT 1,
    can_delete TINYINT(1) NOT NULL DEFAULT 1,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_role_permissions_role_module (role_value, module_key),
    KEY idx_role_permissions_module (module_key),
    KEY idx_role_permissions_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed role 0 (Punch Only) module rows based on existing role 1 modules.
INSERT IGNORE INTO role_permissions (role_value, module_key, can_view, can_create, can_edit, can_delete, updated_by, updated_at)
SELECT 0, module_key, 0, 0, 0, 0, NULL, NOW()
FROM role_permissions
WHERE role_value = 1;

-- Enforce punch-only permissions.
INSERT INTO role_permissions (role_value, module_key, can_view, can_create, can_edit, can_delete, updated_by, updated_at)
VALUES (0, 'time_tracking', 1, 1, 1, 0, NULL, NOW())
ON DUPLICATE KEY UPDATE
    can_view = VALUES(can_view),
    can_create = VALUES(can_create),
    can_edit = VALUES(can_edit),
    can_delete = VALUES(can_delete),
    updated_by = VALUES(updated_by),
    updated_at = NOW();

-- employee_time_entries: add geo columns (MySQL-safe, no IF NOT EXISTS on ADD COLUMN).
SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
          AND COLUMN_NAME = 'punch_in_lat'
    ),
    'SELECT 1',
    'ALTER TABLE employee_time_entries ADD COLUMN punch_in_lat DECIMAL(10,7) NULL AFTER note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
          AND COLUMN_NAME = 'punch_in_lng'
    ),
    'SELECT 1',
    'ALTER TABLE employee_time_entries ADD COLUMN punch_in_lng DECIMAL(10,7) NULL AFTER punch_in_lat'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
          AND COLUMN_NAME = 'punch_in_accuracy_m'
    ),
    'SELECT 1',
    'ALTER TABLE employee_time_entries ADD COLUMN punch_in_accuracy_m DECIMAL(10,2) NULL AFTER punch_in_lng'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
          AND COLUMN_NAME = 'punch_in_source'
    ),
    'SELECT 1',
    'ALTER TABLE employee_time_entries ADD COLUMN punch_in_source VARCHAR(32) NULL AFTER punch_in_accuracy_m'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
          AND COLUMN_NAME = 'punch_in_captured_at'
    ),
    'SELECT 1',
    'ALTER TABLE employee_time_entries ADD COLUMN punch_in_captured_at DATETIME NULL AFTER punch_in_source'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
          AND COLUMN_NAME = 'punch_out_lat'
    ),
    'SELECT 1',
    'ALTER TABLE employee_time_entries ADD COLUMN punch_out_lat DECIMAL(10,7) NULL AFTER punch_in_captured_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
          AND COLUMN_NAME = 'punch_out_lng'
    ),
    'SELECT 1',
    'ALTER TABLE employee_time_entries ADD COLUMN punch_out_lng DECIMAL(10,7) NULL AFTER punch_out_lat'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
          AND COLUMN_NAME = 'punch_out_accuracy_m'
    ),
    'SELECT 1',
    'ALTER TABLE employee_time_entries ADD COLUMN punch_out_accuracy_m DECIMAL(10,2) NULL AFTER punch_out_lng'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
          AND COLUMN_NAME = 'punch_out_source'
    ),
    'SELECT 1',
    'ALTER TABLE employee_time_entries ADD COLUMN punch_out_source VARCHAR(32) NULL AFTER punch_out_accuracy_m'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
          AND COLUMN_NAME = 'punch_out_captured_at'
    ),
    'SELECT 1',
    'ALTER TABLE employee_time_entries ADD COLUMN punch_out_captured_at DATETIME NULL AFTER punch_out_source'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(190) NOT NULL,
    checksum VARCHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_schema_migrations_key (migration_key),
    KEY idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at)
VALUES ('2026-02-21_beta_1.4.4_punch_role_geo', 'punch-role-geo-v1', NOW());
