-- JunkTracker 2.0 (beta) - Global User Hierarchy
-- Site Admin (4) and Dev (99) are global users and should not be tied to
-- any business row. Business-level users cannot assign global roles (enforced in app).
-- Safe to run multiple times.

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

-- Ensure users.business_id exists before running hierarchy updates.
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'users'
    )
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'business_id'
    ),
    'ALTER TABLE users ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER role',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Make global roles business-agnostic.
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'business_id'
    ),
    'UPDATE users
     SET business_id = 0
     WHERE role IN (4, 99)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure role 4 has site_admin permission row when permission table exists.
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'role_permissions'
    ),
    'INSERT INTO role_permissions
        (role_value, module_key, can_view, can_create, can_edit, can_delete, updated_by, updated_at)
     VALUES
        (4, ''site_admin'', 1, 1, 1, 1, NULL, NOW())
     ON DUPLICATE KEY UPDATE
        can_view = VALUES(can_view),
        can_create = VALUES(can_create),
        can_edit = VALUES(can_edit),
        can_delete = VALUES(can_delete),
        updated_at = NOW()',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at)
VALUES ('2026-02-21_beta_2.0_global_user_hierarchy', 'global-hierarchy-v1', NOW());
