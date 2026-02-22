-- JunkTracker 2.0 (beta) - Site Admin Workspace
-- Adds site-admin role permission defaults and businesses table fields used by the
-- site-admin dashboard (create + switch business context).
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

CREATE TABLE IF NOT EXISTS businesses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    legal_name VARCHAR(190) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    website VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_businesses_active (is_active),
    KEY idx_businesses_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO businesses (id, name, legal_name, is_active, created_at, updated_at)
VALUES (1, 'Default Business', 'Default Business', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    legal_name = VALUES(legal_name),
    is_active = VALUES(is_active),
    updated_at = NOW();

-- Ensure optional businesses columns exist on older installs.
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'businesses' AND COLUMN_NAME = 'email'),
    'ALTER TABLE businesses ADD COLUMN email VARCHAR(255) NULL AFTER legal_name',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'businesses' AND COLUMN_NAME = 'phone'),
    'ALTER TABLE businesses ADD COLUMN phone VARCHAR(50) NULL AFTER email',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'businesses' AND COLUMN_NAME = 'website'),
    'ALTER TABLE businesses ADD COLUMN website VARCHAR(255) NULL AFTER phone',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Permission module seed: site_admin
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'role_permissions'),
    'INSERT INTO role_permissions (role_value, module_key, can_view, can_create, can_edit, can_delete, updated_by, updated_at) VALUES
        (0, ''site_admin'', 0, 0, 0, 0, NULL, NOW()),
        (1, ''site_admin'', 0, 0, 0, 0, NULL, NOW()),
        (2, ''site_admin'', 0, 0, 0, 0, NULL, NOW()),
        (3, ''site_admin'', 0, 0, 0, 0, NULL, NOW()),
        (4, ''site_admin'', 1, 1, 1, 1, NULL, NOW())
     ON DUPLICATE KEY UPDATE
        can_view = VALUES(can_view),
        can_create = VALUES(can_create),
        can_edit = VALUES(can_edit),
        can_delete = VALUES(can_delete),
        updated_by = VALUES(updated_by),
        updated_at = NOW()',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed role 4 defaults by copying role 3 where missing.
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'role_permissions'),
    'INSERT INTO role_permissions (role_value, module_key, can_view, can_create, can_edit, can_delete, updated_by, updated_at)
     SELECT 4, rp.module_key, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete, NULL, NOW()
     FROM role_permissions rp
     WHERE rp.role_value = 3
       AND NOT EXISTS (
           SELECT 1
           FROM role_permissions existing
           WHERE existing.role_value = 4
             AND existing.module_key = rp.module_key
       )',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Make sure role 4 always has site_admin module enabled.
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'role_permissions'),
    'UPDATE role_permissions
     SET can_view = 1,
         can_create = 1,
         can_edit = 1,
         can_delete = 1,
         updated_at = NOW()
     WHERE role_value = 4
       AND module_key = ''site_admin''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at)
VALUES ('2026-02-21_beta_2.0_site_admin_workspace', 'site-admin-workspace-v1', NOW());
