-- JunkTracker beta launch prep
-- Run this once on local, then on live before deploy.

SET @schema := DATABASE();

-- 1) Ensure employee_time_entries exists with nullable job_id for Non-Job Time entries
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

-- Normalize legacy non-job placeholders and any orphaned references
UPDATE employee_time_entries
SET job_id = NULL
WHERE job_id = 0;

UPDATE employee_time_entries e
LEFT JOIN jobs j ON j.id = e.job_id
SET e.job_id = NULL
WHERE e.job_id IS NOT NULL
  AND j.id IS NULL;

-- 2) Crew assignment table for punch-in flow on jobs
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

-- 3) User action log for settings/activity screens
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

-- 4) Client contacts log
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

-- 5) Consignors + contracts + payouts + contacts
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

-- 6) User auth hardening: invite-password + email 2FA columns
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

-- 7) Admin settings store
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

-- 8) Role permission matrix
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

-- 9) Lookup option management
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

-- 10) User login records for audit + last-login details
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

-- 11) Auth hardening: failed login telemetry + lockout metadata
CREATE TABLE IF NOT EXISTS auth_login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NULL,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'failed',
    reason VARCHAR(80) NULL,
    user_agent VARCHAR(512) NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auth_login_attempts_email_date (email, attempted_at),
    KEY idx_auth_login_attempts_ip_date (ip_address, attempted_at),
    KEY idx_auth_login_attempts_user_date (user_id, attempted_at),
    KEY idx_auth_login_attempts_status_date (status, attempted_at),
    CONSTRAINT fk_auth_login_attempts_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'failed_login_count'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN failed_login_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_2fa_at'
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
          AND BINARY COLUMN_NAME = BINARY 'last_failed_login_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN last_failed_login_at DATETIME NULL AFTER failed_login_count'
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
          AND BINARY COLUMN_NAME = BINARY 'last_failed_login_ip'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN last_failed_login_ip VARCHAR(45) NULL AFTER last_failed_login_at'
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
          AND BINARY COLUMN_NAME = BINARY 'locked_until'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN locked_until DATETIME NULL AFTER last_failed_login_ip'
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
          AND BINARY COLUMN_NAME = BINARY 'last_login_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER locked_until'
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
          AND BINARY INDEX_NAME = BINARY 'idx_users_locked_until'
    ),
    'SELECT 1',
    'CREATE INDEX idx_users_locked_until ON users (locked_until)'
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
          AND BINARY INDEX_NAME = BINARY 'idx_users_last_failed_login_at'
    ),
    'SELECT 1',
    'CREATE INDEX idx_users_last_failed_login_at ON users (last_failed_login_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 12) Migration tracking
CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(190) NOT NULL,
    checksum VARCHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_schema_migrations_key (migration_key),
    KEY idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at) VALUES
('2026-02-16_beta_launch_prep', 'beta-1.1.3', NOW());
