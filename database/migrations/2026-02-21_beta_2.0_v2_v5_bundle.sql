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
