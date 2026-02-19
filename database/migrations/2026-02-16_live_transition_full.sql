-- JunkTracker live transition schema script
-- Safe to run multiple times on MySQL 8+
-- Run this one file on local and live before launch.

SET @schema := DATABASE();

-- =========================================================
-- 1) Jobs owner/contact fields
-- =========================================================
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'jobs'
          AND BINARY COLUMN_NAME = BINARY 'job_owner_type'
    ),
    'SELECT 1',
    'ALTER TABLE jobs ADD COLUMN job_owner_type VARCHAR(20) NULL AFTER estate_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 12) Admin settings store
-- =========================================================
CREATE TABLE IF NOT EXISTS app_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_app_settings_key (setting_key),
    KEY idx_app_settings_updated_by (updated_by),
    CONSTRAINT fk_app_settings_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 13) Role permission matrix
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

-- =========================================================
-- 14) Lookup option management
-- =========================================================
CREATE TABLE IF NOT EXISTS app_lookups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_key VARCHAR(80) NOT NULL,
    value_key VARCHAR(80) NOT NULL,
    label VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_app_lookups_group_value (group_key, value_key),
    KEY idx_app_lookups_group_active (group_key, active, deleted_at),
    KEY idx_app_lookups_sort (group_key, sort_order),
    KEY idx_app_lookups_created_by (created_by),
    KEY idx_app_lookups_updated_by (updated_by),
    CONSTRAINT fk_app_lookups_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_app_lookups_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_lookups (group_key, value_key, label, sort_order, active, created_at, updated_at) VALUES
('job_status', 'pending', 'Pending', 10, 1, NOW(), NOW()),
('job_status', 'active', 'Active', 20, 1, NOW(), NOW()),
('job_status', 'complete', 'Complete', 30, 1, NOW(), NOW()),
('job_status', 'cancelled', 'Cancelled', 40, 1, NOW(), NOW()),
('prospect_status', 'active', 'Active', 10, 1, NOW(), NOW()),
('prospect_status', 'converted', 'Converted', 20, 1, NOW(), NOW()),
('prospect_status', 'closed', 'Closed', 30, 1, NOW(), NOW()),
('prospect_next_step', 'follow_up', 'Follow Up', 10, 1, NOW(), NOW()),
('prospect_next_step', 'call', 'Call', 20, 1, NOW(), NOW()),
('prospect_next_step', 'text', 'Text', 30, 1, NOW(), NOW()),
('prospect_next_step', 'send_quote', 'Send Quote', 40, 1, NOW(), NOW()),
('prospect_next_step', 'make_appointment', 'Make Appointment', 50, 1, NOW(), NOW()),
('prospect_next_step', 'other', 'Other', 60, 1, NOW(), NOW());

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'jobs'
          AND BINARY COLUMN_NAME = BINARY 'job_owner_id'
    ),
    'SELECT 1',
    'ALTER TABLE jobs ADD COLUMN job_owner_id BIGINT UNSIGNED NULL AFTER job_owner_type'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'jobs'
          AND BINARY COLUMN_NAME = BINARY 'contact_client_id'
    ),
    'SELECT 1',
    'ALTER TABLE jobs ADD COLUMN contact_client_id BIGINT UNSIGNED NULL AFTER job_owner_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE jobs
SET job_owner_type = CASE
        WHEN estate_id IS NOT NULL THEN 'estate'
        ELSE 'client'
    END
WHERE job_owner_type IS NULL
   OR job_owner_type = '';

UPDATE jobs
SET job_owner_id = CASE
        WHEN job_owner_type = 'estate' THEN estate_id
        WHEN job_owner_type = 'company' THEN job_owner_id
        ELSE client_id
    END
WHERE job_owner_id IS NULL;

UPDATE jobs
SET contact_client_id = client_id
WHERE contact_client_id IS NULL;

UPDATE jobs
SET job_owner_type = 'client'
WHERE job_owner_type NOT IN ('client', 'estate', 'company');

UPDATE jobs j
LEFT JOIN clients c ON c.id = j.contact_client_id
SET j.contact_client_id = NULL
WHERE j.contact_client_id IS NOT NULL
  AND c.id IS NULL;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'jobs'
          AND BINARY INDEX_NAME = BINARY 'idx_jobs_owner'
    ),
    'SELECT 1',
    'CREATE INDEX idx_jobs_owner ON jobs (job_owner_type, job_owner_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'jobs'
          AND BINARY INDEX_NAME = BINARY 'idx_jobs_contact_client'
    ),
    'SELECT 1',
    'CREATE INDEX idx_jobs_contact_client ON jobs (contact_client_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
          AND BINARY CONSTRAINT_NAME = BINARY 'fk_jobs_contact_client'
    ),
    'SELECT 1',
    'ALTER TABLE jobs
     ADD CONSTRAINT fk_jobs_contact_client
     FOREIGN KEY (contact_client_id)
     REFERENCES clients(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 2) Expense categories + expenses.expense_category_id
-- =========================================================
CREATE TABLE IF NOT EXISTS expense_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    note TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_expense_categories_name (name),
    KEY idx_expense_categories_deleted_at (deleted_at),
    KEY idx_expense_categories_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO expense_categories (name, created_at, updated_at)
SELECT DISTINCT TRIM(e.category) AS name, NOW(), NOW()
FROM expenses e
WHERE e.category IS NOT NULL
  AND TRIM(e.category) <> '';

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'expenses'
          AND BINARY COLUMN_NAME = BINARY 'expense_category_id'
    ),
    'SELECT 1',
    'ALTER TABLE expenses ADD COLUMN expense_category_id BIGINT UNSIGNED NULL AFTER disposal_location_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE expenses e
JOIN expense_categories ec
  ON BINARY ec.name = BINARY TRIM(e.category)
SET e.expense_category_id = ec.id
WHERE e.expense_category_id IS NULL
  AND e.category IS NOT NULL
  AND TRIM(e.category) <> '';

UPDATE expenses e
LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
SET e.expense_category_id = NULL
WHERE e.expense_category_id IS NOT NULL
  AND ec.id IS NULL;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'expenses'
          AND BINARY INDEX_NAME = BINARY 'idx_expenses_category_id'
    ),
    'SELECT 1',
    'CREATE INDEX idx_expenses_category_id ON expenses (expense_category_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
          AND BINARY CONSTRAINT_NAME = BINARY 'fk_expenses_category'
    ),
    'SELECT 1',
    'ALTER TABLE expenses
     ADD CONSTRAINT fk_expenses_category
     FOREIGN KEY (expense_category_id)
     REFERENCES expense_categories(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 3) Prospects columns used by current app
-- =========================================================
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'prospects'
          AND BINARY COLUMN_NAME = BINARY 'priority_rating'
    ),
    'SELECT 1',
    'ALTER TABLE prospects ADD COLUMN priority_rating TINYINT UNSIGNED NULL AFTER status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'prospects'
          AND BINARY COLUMN_NAME = BINARY 'next_step'
    ),
    'SELECT 1',
    'ALTER TABLE prospects ADD COLUMN next_step VARCHAR(50) NULL AFTER priority_rating'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'prospects'
          AND BINARY COLUMN_NAME = BINARY 'created_by'
    ),
    'SELECT 1',
    'ALTER TABLE prospects ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER created_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'prospects'
          AND BINARY COLUMN_NAME = BINARY 'updated_by'
    ),
    'SELECT 1',
    'ALTER TABLE prospects ADD COLUMN updated_by BIGINT UNSIGNED NULL AFTER updated_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'prospects'
          AND BINARY COLUMN_NAME = BINARY 'deleted_by'
    ),
    'SELECT 1',
    'ALTER TABLE prospects ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'prospects'
          AND BINARY COLUMN_NAME = BINARY 'contacted_on'
    ),
    'SELECT 1',
    'ALTER TABLE prospects ADD COLUMN contacted_on DATE NULL AFTER client_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'prospects'
          AND BINARY COLUMN_NAME = BINARY 'follow_up_on'
    ),
    'SELECT 1',
    'ALTER TABLE prospects ADD COLUMN follow_up_on DATE NULL AFTER contacted_on'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 4) Task table (todos)
-- =========================================================
CREATE TABLE IF NOT EXISTS todos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    body TEXT NULL,
    link_type VARCHAR(30) NOT NULL DEFAULT 'general',
    link_id BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    importance TINYINT UNSIGNED NOT NULL DEFAULT 3,
    status ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
    outcome TEXT NULL,
    due_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_link (link_type, link_id),
    KEY idx_status (status),
    KEY idx_importance (importance),
    KEY idx_assigned_user (assigned_user_id),
    KEY idx_due_at (due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'todos'
          AND BINARY COLUMN_NAME = BINARY 'link_type'
          AND LOWER(DATA_TYPE) = 'varchar'
    ),
    'SELECT 1',
    'ALTER TABLE todos MODIFY COLUMN link_type VARCHAR(30) NOT NULL DEFAULT ''general'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 5) Client/company link table
-- =========================================================
CREATE TABLE IF NOT EXISTS companies_x_clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    updated_by BIGINT UNSIGNED DEFAULT NULL,
    deleted_by BIGINT UNSIGNED DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_company_id (company_id),
    KEY idx_client_id (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 6) Time entries + Non-Job Time support
-- =========================================================
CREATE TABLE IF NOT EXISTS employee_time_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    work_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    minutes_worked INT UNSIGNED NULL,
    pay_rate DECIMAL(12,2) UNSIGNED NULL,
    total_paid DECIMAL(12,2) UNSIGNED NULL,
    note TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_time_employee_date (employee_id, work_date),
    KEY idx_time_job_date (job_id, work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE employee_time_entries
    MODIFY COLUMN job_id BIGINT UNSIGNED NULL;

UPDATE employee_time_entries
SET job_id = NULL
WHERE job_id = 0;

UPDATE employee_time_entries e
LEFT JOIN jobs j ON j.id = e.job_id
SET e.job_id = NULL
WHERE e.job_id IS NOT NULL
  AND j.id IS NULL;

-- =========================================================
-- 7) Job crew table for punch-in workflow
-- =========================================================
CREATE TABLE IF NOT EXISTS job_crew (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_job_crew_member (job_id, employee_id),
    KEY idx_job_crew_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 8) User action log
-- =========================================================
CREATE TABLE IF NOT EXISTS user_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    action_key VARCHAR(80) NOT NULL,
    entity_table VARCHAR(80) DEFAULT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    summary VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_actions_user_created (user_id, created_at),
    KEY idx_user_actions_entity (entity_table, entity_id),
    KEY idx_user_actions_action_key (action_key),
    CONSTRAINT fk_user_actions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 8a) User login records
-- =========================================================
CREATE TABLE IF NOT EXISTS user_login_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    login_method VARCHAR(30) NOT NULL DEFAULT 'password',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(512) DEFAULT NULL,
    browser_name VARCHAR(80) DEFAULT NULL,
    browser_version VARCHAR(40) DEFAULT NULL,
    os_name VARCHAR(80) DEFAULT NULL,
    device_type VARCHAR(30) DEFAULT NULL,
    logged_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_login_records_user_date (user_id, logged_in_at),
    KEY idx_user_login_records_method (login_method),
    CONSTRAINT fk_user_login_records_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 9) Client contacts log
-- =========================================================
CREATE TABLE IF NOT EXISTS client_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    link_type VARCHAR(30) NOT NULL DEFAULT 'general',
    link_id BIGINT UNSIGNED NULL,
    contact_method VARCHAR(20) NOT NULL DEFAULT 'call',
    direction VARCHAR(10) NOT NULL DEFAULT 'outbound',
    subject VARCHAR(150) NULL,
    notes TEXT NULL,
    contacted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    follow_up_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client_contacts_client_date (client_id, contacted_at),
    KEY idx_client_contacts_link (link_type, link_id),
    KEY idx_client_contacts_method (contact_method),
    KEY idx_client_contacts_active (active, deleted_at),
    KEY idx_client_contacts_created_by (created_by),
    KEY idx_client_contacts_updated_by (updated_by),
    KEY idx_client_contacts_deleted_by (deleted_by),
    CONSTRAINT fk_client_contacts_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_client_contacts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_client_contacts_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_client_contacts_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 10) Consignors + contracts + payouts + contacts
-- =========================================================
CREATE TABLE IF NOT EXISTS consignors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NULL,
    business_name VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(255) NULL,
    address_1 VARCHAR(150) NULL,
    address_2 VARCHAR(150) NULL,
    city VARCHAR(80) NULL,
    state VARCHAR(2) NULL,
    zip VARCHAR(12) NULL,
    consignor_number VARCHAR(40) NULL,
    consignment_start_date DATE NULL,
    consignment_end_date DATE NULL,
    payment_schedule VARCHAR(20) NULL,
    next_payment_due_date DATE NULL,
    inventory_estimate_amount DECIMAL(12,2) NULL,
    inventory_description TEXT NULL,
    note TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_consignors_number (consignor_number),
    KEY idx_consignors_name (last_name, first_name, business_name),
    KEY idx_consignors_active (active, deleted_at),
    KEY idx_consignors_next_payment_due (next_payment_due_date),
    KEY idx_consignors_created_by (created_by),
    KEY idx_consignors_updated_by (updated_by),
    KEY idx_consignors_deleted_by (deleted_by),
    CONSTRAINT fk_consignors_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_consignors_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_consignors_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'consignors'
          AND BINARY COLUMN_NAME = BINARY 'consignor_number'
    ),
    'SELECT 1',
    'ALTER TABLE consignors ADD COLUMN consignor_number VARCHAR(40) NULL AFTER zip'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'consignors'
          AND BINARY COLUMN_NAME = BINARY 'consignment_start_date'
    ),
    'SELECT 1',
    'ALTER TABLE consignors ADD COLUMN consignment_start_date DATE NULL AFTER consignor_number'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'consignors'
          AND BINARY COLUMN_NAME = BINARY 'consignment_end_date'
    ),
    'SELECT 1',
    'ALTER TABLE consignors ADD COLUMN consignment_end_date DATE NULL AFTER consignment_start_date'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'consignors'
          AND BINARY COLUMN_NAME = BINARY 'payment_schedule'
    ),
    'SELECT 1',
    'ALTER TABLE consignors ADD COLUMN payment_schedule VARCHAR(20) NULL AFTER consignment_end_date'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'consignors'
          AND BINARY COLUMN_NAME = BINARY 'next_payment_due_date'
    ),
    'SELECT 1',
    'ALTER TABLE consignors ADD COLUMN next_payment_due_date DATE NULL AFTER payment_schedule'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'consignors'
          AND BINARY INDEX_NAME = BINARY 'uniq_consignors_number'
    ),
    'SELECT 1',
    'CREATE UNIQUE INDEX uniq_consignors_number ON consignors (consignor_number)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'consignors'
          AND BINARY INDEX_NAME = BINARY 'idx_consignors_next_payment_due'
    ),
    'SELECT 1',
    'CREATE INDEX idx_consignors_next_payment_due ON consignors (next_payment_due_date)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS consignor_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    consignor_id BIGINT UNSIGNED NOT NULL,
    link_type VARCHAR(30) NOT NULL DEFAULT 'general',
    link_id BIGINT UNSIGNED NULL,
    contact_method VARCHAR(20) NOT NULL DEFAULT 'call',
    direction VARCHAR(10) NOT NULL DEFAULT 'outbound',
    subject VARCHAR(150) NULL,
    notes TEXT NULL,
    contacted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    follow_up_at DATETIME NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consignor_contacts_consignor_date (consignor_id, contacted_at),
    KEY idx_consignor_contacts_link (link_type, link_id),
    KEY idx_consignor_contacts_active (active, deleted_at),
    KEY idx_consignor_contacts_created_by (created_by),
    KEY idx_consignor_contacts_updated_by (updated_by),
    KEY idx_consignor_contacts_deleted_by (deleted_by),
    CONSTRAINT fk_consignor_contacts_consignor
        FOREIGN KEY (consignor_id) REFERENCES consignors(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_consignor_contacts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_consignor_contacts_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_consignor_contacts_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consignor_contracts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    consignor_id BIGINT UNSIGNED NOT NULL,
    contract_title VARCHAR(150) NOT NULL,
    original_file_name VARCHAR(255) NOT NULL,
    stored_file_name VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size BIGINT UNSIGNED NULL,
    contract_signed_at DATE NULL,
    expires_at DATE NULL,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consignor_contracts_consignor (consignor_id),
    KEY idx_consignor_contracts_active (active, deleted_at),
    KEY idx_consignor_contracts_created_by (created_by),
    KEY idx_consignor_contracts_updated_by (updated_by),
    KEY idx_consignor_contracts_deleted_by (deleted_by),
    CONSTRAINT fk_consignor_contracts_consignor
        FOREIGN KEY (consignor_id) REFERENCES consignors(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_consignor_contracts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_consignor_contracts_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_consignor_contracts_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consignor_payouts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    consignor_id BIGINT UNSIGNED NOT NULL,
    payout_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    estimate_amount DECIMAL(12,2) NULL,
    payout_method VARCHAR(30) NOT NULL DEFAULT 'other',
    reference_no VARCHAR(80) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'paid',
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consignor_payouts_consignor_date (consignor_id, payout_date),
    KEY idx_consignor_payouts_status (status),
    KEY idx_consignor_payouts_active (active, deleted_at),
    KEY idx_consignor_payouts_created_by (created_by),
    KEY idx_consignor_payouts_updated_by (updated_by),
    KEY idx_consignor_payouts_deleted_by (deleted_by),
    CONSTRAINT fk_consignor_payouts_consignor
        FOREIGN KEY (consignor_id) REFERENCES consignors(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_consignor_payouts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_consignor_payouts_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_consignor_payouts_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 11) User auth hardening: invite-password + email 2FA
-- =========================================================
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'password_setup_token_hash'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN password_setup_token_hash VARCHAR(255) NULL AFTER password_hash'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'password_setup_expires_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN password_setup_expires_at DATETIME NULL AFTER password_setup_token_hash'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'password_setup_sent_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN password_setup_sent_at DATETIME NULL AFTER password_setup_expires_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'password_setup_used_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN password_setup_used_at DATETIME NULL AFTER password_setup_sent_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'two_factor_code_hash'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN two_factor_code_hash VARCHAR(255) NULL AFTER password_setup_used_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'two_factor_expires_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN two_factor_expires_at DATETIME NULL AFTER two_factor_code_hash'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'two_factor_sent_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN two_factor_sent_at DATETIME NULL AFTER two_factor_expires_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'last_2fa_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN last_2fa_at DATETIME NULL AFTER two_factor_sent_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY INDEX_NAME = BINARY 'idx_users_password_setup_expires'
    ),
    'SELECT 1',
    'CREATE INDEX idx_users_password_setup_expires ON users (password_setup_expires_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
