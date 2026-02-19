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

CREATE TEMPORARY TABLE tmp_dev_bugs_backfill_1_1 (
    title VARCHAR(255) NOT NULL,
    details TEXT NULL,
    severity TINYINT UNSIGNED NOT NULL,
    environment VARCHAR(16) NOT NULL,
    module_key VARCHAR(80) NULL,
    route_path VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_dev_bugs_backfill_1_1 (title, details, severity, environment, module_key, route_path) VALUES
('Login redirected back to login without error after submit', 'Added clear login error handling and flash messaging for failed authentication attempts.', 4, 'both', 'users', '/login'),
('Session expired error unless Remember Me selected', 'Fixed session/csrf behavior so login works correctly with or without Remember Me.', 5, 'both', 'users', '/login'),
('Logout action failed for active sessions', 'Resolved logout/session cleanup flow so users can reliably end sessions.', 4, 'both', 'users', '/logout'),
('Newly invited users could not sign in after setting password', 'Fixed setup-token password flow and login checks for invited accounts.', 5, 'both', 'users', '/set-password'),
('Login route produced too many redirects', 'Resolved authentication redirect loop causing ERR_TOO_MANY_REDIRECTS.', 5, 'local', 'users', '/login'),
('UsersController parse error from invalid token sequence', 'Removed malformed token causing controller parse failure.', 4, 'local', 'users', '/users'),
('Prospect model parse error on cancel flow', 'Removed malformed token causing prospect add/cancel parse failure.', 4, 'local', 'prospects', '/prospects/new'),
('Job soft delete failed on missing updated_by column', 'Aligned delete query with actual schema so soft delete succeeds.', 4, 'local', 'jobs', '/jobs/{id}/delete'),
('Add job submitted then returned session expired without save', 'Fixed job form CSRF/session handling to save reliably.', 4, 'both', 'jobs', '/jobs/new'),
('Job contact lookup returned companies instead of clients', 'Corrected contact data source to clients table in job forms.', 3, 'both', 'jobs', '/jobs/{id}/edit'),
('Second time-entry save failed with session expired', 'Fixed repeated submit token/session behavior on time tracking create.', 4, 'both', 'time_tracking', '/time-tracking/new'),
('Time entry saved but did not appear in job hours summary', 'Fixed job hours query to include valid active time entries.', 3, 'both', 'time_tracking', '/jobs/{id}'),
('Disposal fees on jobs did not persist', 'Corrected disposal fee save path and action logging.', 3, 'both', 'jobs', '/jobs/{id}'),
('Expense save failed when job_id was 0', 'Handled non-job expenses correctly by storing NULL job_id and validating input.', 5, 'local', 'expenses', '/expenses/new'),
('Employee quick punch-in forced job selection', 'Updated quick punch flow to allow non-job punch-ins.', 3, 'both', 'employees', '/employees/{id}'),
('Job view showed Punch In after employee already punched in', 'Fixed stale punch-state refresh logic after navigation.', 3, 'both', 'time_tracking', '/jobs/{id}'),
('User self-deactivation was allowed for non-dev users', 'Added guard so only dev users can deactivate their own account.', 4, 'both', 'users', '/users/{id}'),
('Employee-link autosuggest dropdown clipped inside card', 'Adjusted overflow and stacking so autosuggest renders fully above card boundaries.', 2, 'both', 'users', '/users/{id}'),
('Time entries displayed with incorrect timezone offset', 'Standardized app timezone handling to Eastern Time for punch and display consistency.', 3, 'both', 'time_tracking', '/time-tracking'),
('Dashboard punch-in only supported non-job flow', 'Added modal flow to choose Job Time vs Non-Job Time at punch-in.', 2, 'local', 'time_tracking', '/');

INSERT INTO dev_bugs (
    title,
    details,
    status,
    severity,
    environment,
    module_key,
    route_path,
    fixed_at,
    updated_at,
    created_at
)
SELECT
    t.title,
    t.details,
    'fixed',
    t.severity,
    t.environment,
    t.module_key,
    t.route_path,
    NOW(),
    NOW(),
    NOW()
FROM tmp_dev_bugs_backfill_1_1 t
LEFT JOIN dev_bugs b
    ON b.title = t.title
   AND b.deleted_at IS NULL
WHERE b.id IS NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_dev_bugs_backfill_1_1;

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
('2026-02-20_dev_bugs_backfill_since_1_1', 'bundle-1.2.0', NOW()),
('2026-02-20_role_permissions_beta_1_2_refresh', 'bundle-1.2.0', NOW()),
('2026-02-20_user_filter_presets', 'bundle-1.2.0', NOW()),
('2026-02-20_dashboard_kpi_snapshots', 'bundle-1.2.0', NOW()),
('2026-02-20_bundle_1.2.0', 'bundle-1.2.0', NOW());
