-- JunkTracker Bundle 1.2.0
-- Safe for phpMyAdmin import (MySQL/MariaDB without ADD COLUMN IF NOT EXISTS support).
-- Includes:
-- - Employee <-> user linking (for punch-me workflows)
-- - Dev bug tracker table
-- - Saved filter presets table
-- - Dashboard KPI snapshots table
-- - Dev tools permission rows for role matrix

SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(190) NOT NULL,
    checksum VARCHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_schema_migrations_key (migration_key),
    KEY idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 1) Employee <-> User link support
-- =========================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE BINARY TABLE_SCHEMA = BINARY @schema
      AND BINARY TABLE_NAME = BINARY 'employees'
      AND BINARY COLUMN_NAME = BINARY 'user_id'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE employees ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER email',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE BINARY TABLE_SCHEMA = BINARY @schema
      AND BINARY TABLE_NAME = BINARY 'employees'
      AND BINARY INDEX_NAME = BINARY 'idx_employees_user_id'
);
SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE employees ADD INDEX idx_employees_user_id (user_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remove duplicate links before applying unique constraint.
UPDATE employees e
INNER JOIN (
    SELECT user_id, MIN(id) AS keep_id
    FROM employees
    WHERE user_id IS NOT NULL
    GROUP BY user_id
    HAVING COUNT(*) > 1
) d ON d.user_id = e.user_id
SET e.user_id = NULL
WHERE e.id <> d.keep_id;

SET @uniq_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE BINARY TABLE_SCHEMA = BINARY @schema
      AND BINARY TABLE_NAME = BINARY 'employees'
      AND BINARY INDEX_NAME = BINARY 'uniq_employees_user_id'
);
SET @sql := IF(
    @uniq_exists = 0,
    'ALTER TABLE employees ADD UNIQUE KEY uniq_employees_user_id (user_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_employees_user'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE employees
     ADD CONSTRAINT fk_employees_user
     FOREIGN KEY (user_id)
     REFERENCES users(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 2) Dev tools bug tracker
-- =========================================================
CREATE TABLE IF NOT EXISTS dev_bugs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    details TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'new',
    severity TINYINT UNSIGNED NOT NULL DEFAULT 3,
    environment VARCHAR(16) NOT NULL DEFAULT 'local',
    module_key VARCHAR(80) NULL,
    route_path VARCHAR(255) NULL,
    reported_by BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    fixed_at DATETIME NULL,
    fixed_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_dev_bugs_status_updated (status, updated_at),
    KEY idx_dev_bugs_severity_status (severity, status),
    KEY idx_dev_bugs_environment_status (environment, status),
    KEY idx_dev_bugs_assigned (assigned_user_id),
    KEY idx_dev_bugs_reported (reported_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 3) Saved filter presets
-- =========================================================
CREATE TABLE IF NOT EXISTS user_filter_presets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    module_key VARCHAR(50) NOT NULL,
    preset_name VARCHAR(80) NOT NULL,
    filters_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_user_filter_preset_name (user_id, module_key, preset_name),
    KEY idx_user_filter_presets_user_module (user_id, module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @preset_fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_user_filter_presets_user'
);
SET @sql := IF(
    @preset_fk_exists = 0,
    'ALTER TABLE user_filter_presets
     ADD CONSTRAINT fk_user_filter_presets_user
     FOREIGN KEY (user_id)
     REFERENCES users(id)
     ON DELETE CASCADE
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 4) Dashboard KPI snapshots
-- =========================================================
CREATE TABLE IF NOT EXISTS dashboard_kpi_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    snapshot_date DATE NOT NULL,
    metrics_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_dashboard_kpi_snapshots_date (snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5) Role permission rows for new module(s)
-- =========================================================
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
    KEY idx_role_permissions_updated_by (updated_by),
    CONSTRAINT fk_role_permissions_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO role_permissions (role_value, module_key, can_view, can_create, can_edit, can_delete, updated_by, updated_at)
VALUES
    (1, 'dev_tools', 0, 0, 0, 0, NULL, NOW()),
    (2, 'dev_tools', 0, 0, 0, 0, NULL, NOW()),
    (3, 'dev_tools', 0, 0, 0, 0, NULL, NOW())
ON DUPLICATE KEY UPDATE
    can_view = VALUES(can_view),
    can_create = VALUES(can_create),
    can_edit = VALUES(can_edit),
    can_delete = VALUES(can_delete),
    updated_by = VALUES(updated_by),
    updated_at = NOW();

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at) VALUES
('2026-02-20_user_employee_links', 'bundle-1.2.0', NOW()),
('2026-02-20_dev_tools_bugs', 'bundle-1.2.0', NOW()),
('2026-02-20_role_permissions_beta_1_2_refresh', 'bundle-1.2.0', NOW()),
('2026-02-20_user_filter_presets', 'bundle-1.2.0', NOW()),
('2026-02-20_dashboard_kpi_snapshots', 'bundle-1.2.0', NOW()),
('2026-02-20_bundle_1.2.0', 'bundle-1.2.0', NOW());
