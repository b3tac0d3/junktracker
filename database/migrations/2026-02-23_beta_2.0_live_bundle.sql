-- JunkTracker Beta 2.0 Live Bundle
-- Generated: 2026-02-23 00:37:35

-- ==================================================
-- SOURCE: 2026-02-21_beta_1.4.4_punch_role_geo.sql
-- ==================================================

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


-- ==================================================
-- SOURCE: 2026-02-21_beta_2.0_global_user_hierarchy.sql
-- ==================================================

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


-- ==================================================
-- SOURCE: 2026-02-21_beta_2.0_site_admin_workspace.sql
-- ==================================================

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


-- ==================================================
-- SOURCE: 2026-02-21_beta_2.0_v2_v5_bundle.sql
-- ==================================================

-- JunkTracker 2.0 (beta)
-- v2: Notification ownership + manager/admin visibility controls
-- v3: Task assignment lifecycle (pending/accept/decline)
-- v4: Business Info module (single-business settings)
-- v5: Business scoping foundation (business_id across core tables)
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
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_businesses_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO businesses (id, name, legal_name, is_active, created_at, updated_at)
VALUES (1, 'Default Business', 'Default Business', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    legal_name = VALUES(legal_name),
    is_active = VALUES(is_active),
    updated_at = NOW();

-- users.business_id
SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'business_id'
    ),
    'ALTER TABLE users ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER role',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND INDEX_NAME = 'idx_users_business'
    ),
    'SELECT 1',
    'CREATE INDEX idx_users_business ON users (business_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- todos business + assignment lifecycle columns
SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
          AND COLUMN_NAME = 'business_id'
    ),
    'ALTER TABLE todos ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
          AND INDEX_NAME = 'idx_todos_business'
    ),
    'SELECT 1',
    'CREATE INDEX idx_todos_business ON todos (business_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
          AND COLUMN_NAME = 'assignment_status'
    ),
    'ALTER TABLE todos ADD COLUMN assignment_status VARCHAR(20) NOT NULL DEFAULT ''unassigned'' AFTER assigned_user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
          AND COLUMN_NAME = 'assignment_requested_at'
    ),
    'ALTER TABLE todos ADD COLUMN assignment_requested_at DATETIME NULL AFTER assignment_status',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
          AND COLUMN_NAME = 'assignment_responded_at'
    ),
    'ALTER TABLE todos ADD COLUMN assignment_responded_at DATETIME NULL AFTER assignment_requested_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
          AND COLUMN_NAME = 'assignment_requested_by'
    ),
    'ALTER TABLE todos ADD COLUMN assignment_requested_by BIGINT UNSIGNED NULL AFTER assignment_responded_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
          AND COLUMN_NAME = 'assignment_note'
    ),
    'ALTER TABLE todos ADD COLUMN assignment_note VARCHAR(255) NULL AFTER assignment_requested_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'todos'
          AND INDEX_NAME = 'idx_assignment_status'
    ),
    'SELECT 1',
    'CREATE INDEX idx_assignment_status ON todos (assignment_status)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- notifications state storage (business-aware)
CREATE TABLE IF NOT EXISTS user_notification_states (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
    notification_key VARCHAR(190) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    dismissed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_user_notification_state (user_id, business_id, notification_key),
    KEY idx_user_notification_states_user (user_id),
    KEY idx_user_notification_states_business (business_id),
    KEY idx_user_notification_states_read (user_id, is_read),
    KEY idx_user_notification_states_dismissed (user_id, dismissed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_notification_states'
          AND COLUMN_NAME = 'business_id'
    ),
    'SELECT 1',
    'ALTER TABLE user_notification_states ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER user_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_notification_states'
          AND INDEX_NAME = 'uniq_user_notification_state'
          AND COLUMN_NAME = 'business_id'
    ),
    'SELECT 1',
    'ALTER TABLE user_notification_states DROP INDEX uniq_user_notification_state'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_notification_states'
          AND INDEX_NAME = 'uniq_user_notification_state'
          AND COLUMN_NAME = 'business_id'
    ),
    'SELECT 1',
    'ALTER TABLE user_notification_states ADD UNIQUE KEY uniq_user_notification_state (user_id, business_id, notification_key)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_notification_states'
          AND INDEX_NAME = 'idx_user_notification_states_business'
    ),
    'SELECT 1',
    'CREATE INDEX idx_user_notification_states_business ON user_notification_states (business_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- user_actions + user_login_records business_id
SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_actions'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_actions'
          AND COLUMN_NAME = 'business_id'
    ),
    'ALTER TABLE user_actions ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_actions'
          AND INDEX_NAME = 'idx_user_actions_business'
    ),
    'SELECT 1',
    'CREATE INDEX idx_user_actions_business ON user_actions (business_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_login_records'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_login_records'
          AND COLUMN_NAME = 'business_id'
    ),
    'ALTER TABLE user_login_records ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_login_records'
          AND INDEX_NAME = 'idx_user_login_records_business'
    ),
    'SELECT 1',
    'CREATE INDEX idx_user_login_records_business ON user_login_records (business_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Core operational tables: add business_id where missing.
-- (single-business default behavior now; app will scope by this column)

SET @tables := 'clients,companies,estates,prospects,jobs,job_actions,job_billing,job_disposals,expenses,sales,employees,employee_time_entries,contacts,client_contacts,consignors,attachments,disposal_locations,expense_categories,lookup_options';

-- clients
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE clients ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- companies
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE companies ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- estates
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estates')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estates' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE estates ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- prospects
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prospects')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prospects' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE prospects ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- jobs
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE jobs ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- job_actions
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_actions')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_actions' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE job_actions ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- job_billing
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_billing')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_billing' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE job_billing ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- job_disposals
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_disposals')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_disposals' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE job_disposals ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- expenses
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE expenses ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sales
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE sales ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- employees
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE employees ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- employee_time_entries
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee_time_entries')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee_time_entries' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE employee_time_entries ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- contacts
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE contacts ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- client_contacts
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_contacts')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_contacts' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE client_contacts ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- consignors
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consignors')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consignors' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE consignors ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- attachments
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attachments')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attachments' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE attachments ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- disposal_locations
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'disposal_locations')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'disposal_locations' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE disposal_locations ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- expense_categories
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expense_categories')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expense_categories' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE expense_categories ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- lookup_options
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lookup_options')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lookup_options' AND COLUMN_NAME = 'business_id'),
    'ALTER TABLE lookup_options ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Role permission defaults for new module: business_info
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_permissions'),
    'INSERT INTO role_permissions (role_value, module_key, can_view, can_create, can_edit, can_delete, updated_by, updated_at) VALUES
        (0, ''business_info'', 0, 0, 0, 0, NULL, NOW()),
        (1, ''business_info'', 0, 0, 0, 0, NULL, NOW()),
        (2, ''business_info'', 1, 0, 1, 0, NULL, NOW()),
        (3, ''business_info'', 1, 1, 1, 1, NULL, NOW())
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

-- Business info defaults in app_settings (safe if table does not exist)
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings'),
    'INSERT INTO app_settings (setting_key, setting_value, updated_by, updated_at) VALUES
        (''business.name'', ''Default Business'', NULL, NOW()),
        (''business.legal_name'', ''Default Business'', NULL, NOW()),
        (''business.email'', '''', NULL, NOW()),
        (''business.phone'', '''', NULL, NOW()),
        (''business.website'', '''', NULL, NOW()),
        (''business.address_line1'', '''', NULL, NOW()),
        (''business.address_line2'', '''', NULL, NOW()),
        (''business.city'', '''', NULL, NOW()),
        (''business.state'', '''', NULL, NOW()),
        (''business.postal_code'', '''', NULL, NOW()),
        (''business.country'', ''US'', NULL, NOW()),
        (''business.tax_id'', '''', NULL, NOW()),
        (''business.timezone'', ''America/New_York'', NULL, NOW())
     ON DUPLICATE KEY UPDATE
        setting_value = setting_value',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at)
VALUES ('2026-02-21_beta_2.0_v2_v5_bundle', 'v2-v5-business-scope-1', NOW());


-- ==================================================
-- SOURCE: 2026-02-22_beta_2.0_site_admin_support_queue.sql
-- ==================================================

-- JunkTracker Beta 2.0
-- Site Admin Support Queue (site_admin_tickets + notes)
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS site_admin_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NULL,
    submitted_by_user_id BIGINT UNSIGNED NOT NULL,
    submitted_by_email VARCHAR(255) NOT NULL,
    category VARCHAR(32) NOT NULL DEFAULT 'question',
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unopened',
    priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
    assigned_to_user_id BIGINT UNSIGNED NULL,
    opened_at DATETIME NULL,
    closed_at DATETIME NULL,
    last_customer_note_at DATETIME NULL,
    last_admin_note_at DATETIME NULL,
    converted_bug_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_site_admin_tickets_status (status),
    KEY idx_site_admin_tickets_priority_status (priority, status),
    KEY idx_site_admin_tickets_assigned (assigned_to_user_id),
    KEY idx_site_admin_tickets_submitter (submitted_by_user_id),
    KEY idx_site_admin_tickets_business (business_id),
    KEY idx_site_admin_tickets_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_admin_ticket_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'customer',
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_site_admin_ticket_notes_ticket (ticket_id),
    KEY idx_site_admin_ticket_notes_visibility (visibility),
    KEY idx_site_admin_ticket_notes_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==================================================
-- SOURCE: 2026-02-22_beta_2.0_dev_bug_statuses_and_notes.sql
-- ==================================================

-- JunkTracker Beta 2.0: Dev bug statuses + notes

CREATE TABLE IF NOT EXISTS dev_bug_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bug_id BIGINT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dev_bug_notes_bug (bug_id),
    KEY idx_dev_bug_notes_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dev_bugs
    MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'unresearched';

UPDATE dev_bugs
SET status = CASE
    WHEN status = 'new' THEN 'unresearched'
    WHEN status = 'in_progress' THEN 'working'
    WHEN status IN ('fixed', 'wont_fix') THEN 'fixed_closed'
    ELSE status
END
WHERE status IN ('new', 'in_progress', 'fixed', 'wont_fix');


-- ==================================================
-- SOURCE: 2026-02-22_beta_2.0_prelive_hardening.sql
-- ==================================================

-- JunkTracker Beta 2.0 pre-live hardening bundle
-- Adds performance indexes for support queue / bug notes / activity filters.
-- Safe to run multiple times in phpMyAdmin.

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(190) NOT NULL,
    checksum VARCHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_schema_migrations_key (migration_key),
    KEY idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @schema := DATABASE();

-- user_actions: user + action + date filters
SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'user_actions'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'user_actions'
          AND INDEX_NAME = 'idx_user_actions_user_action_created'
    ),
    'ALTER TABLE user_actions ADD INDEX idx_user_actions_user_action_created (user_id, action_key, created_at)',
    'SELECT 1'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- site_admin_tickets: admin unread counters and queue filtering
SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_tickets'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_tickets'
          AND INDEX_NAME = 'idx_site_admin_tickets_status_customer_admin'
    ),
    'ALTER TABLE site_admin_tickets ADD INDEX idx_site_admin_tickets_status_customer_admin (status, last_customer_note_at, last_admin_note_at)',
    'SELECT 1'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_tickets'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_tickets'
          AND INDEX_NAME = 'idx_site_admin_tickets_business_status_updated'
    ),
    'ALTER TABLE site_admin_tickets ADD INDEX idx_site_admin_tickets_business_status_updated (business_id, status, updated_at)',
    'SELECT 1'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- site_admin_ticket_notes: ticket thread reads
SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_ticket_notes'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'site_admin_ticket_notes'
          AND INDEX_NAME = 'idx_site_admin_ticket_notes_ticket_visibility_created'
    ),
    'ALTER TABLE site_admin_ticket_notes ADD INDEX idx_site_admin_ticket_notes_ticket_visibility_created (ticket_id, visibility, created_at)',
    'SELECT 1'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- dev_bug_notes: bug detail timeline reads
SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'dev_bug_notes'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'dev_bug_notes'
          AND INDEX_NAME = 'idx_dev_bug_notes_bug_created'
    ),
    'ALTER TABLE dev_bug_notes ADD INDEX idx_dev_bug_notes_bug_created (bug_id, created_at)',
    'SELECT 1'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at) VALUES
('2026-02-22_beta_2.0_prelive_hardening', 'beta-2.0-prelive-hardening-v1', NOW());


