-- JunkTracker v3 beta 3.0 live bundle
-- Generated: 2026-03-07
SET NAMES utf8mb4;


-- >>> 2026-02-27_v3_phase_a_core.sql

-- JunkTracker v3 Phase A Core Schema
-- Date: 2026-02-27

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS businesses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    legal_name VARCHAR(200) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    address_line1 VARCHAR(190) NULL,
    address_line2 VARCHAR(190) NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(60) NULL,
    postal_code VARCHAR(30) NULL,
    country VARCHAR(80) NULL DEFAULT 'US',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_businesses_active (is_active),
    KEY idx_businesses_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NULL,
    first_name VARCHAR(90) NULL,
    last_name VARCHAR(90) NULL,
    role ENUM('general_user','admin','punch_only','site_admin') NOT NULL DEFAULT 'general_user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_active (is_active),
    KEY idx_users_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_user_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('general_user','admin','punch_only') NOT NULL DEFAULT 'general_user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_membership_business_user (business_id, user_id),
    KEY idx_membership_user (user_id),
    KEY idx_membership_business_role (business_id, role),
    KEY idx_membership_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_membership_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_membership_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(90) NULL,
    last_name VARCHAR(90) NULL,
    company_name VARCHAR(150) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    address_line1 VARCHAR(190) NULL,
    address_line2 VARCHAR(190) NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(60) NULL,
    postal_code VARCHAR(30) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_clients_business (business_id),
    KEY idx_clients_business_status (business_id, status),
    KEY idx_clients_business_deleted (business_id, deleted_at),
    KEY idx_clients_business_name (business_id, last_name, first_name),
    CONSTRAINT fk_clients_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(90) NULL,
    last_name VARCHAR(90) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    hourly_rate DECIMAL(10,2) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    user_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_employees_business (business_id),
    KEY idx_employees_business_status (business_id, status),
    KEY idx_employees_business_deleted (business_id, deleted_at),
    KEY idx_employees_user_id (user_id),
    CONSTRAINT fk_employees_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    status ENUM('pending','active','complete','cancelled') NOT NULL DEFAULT 'pending',
    scheduled_start_at DATETIME NULL,
    scheduled_end_at DATETIME NULL,
    actual_start_at DATETIME NULL,
    actual_end_at DATETIME NULL,
    address_line1 VARCHAR(190) NULL,
    address_line2 VARCHAR(190) NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(60) NULL,
    postal_code VARCHAR(30) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_jobs_business (business_id),
    KEY idx_jobs_business_status (business_id, status),
    KEY idx_jobs_business_client (business_id, client_id),
    KEY idx_jobs_business_schedule (business_id, scheduled_start_at),
    KEY idx_jobs_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_jobs_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_client FOREIGN KEY (client_id) REFERENCES clients(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NULL,
    status ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
    owner_user_id BIGINT UNSIGNED NOT NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    due_at DATETIME NULL,
    priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
    link_type VARCHAR(40) NULL,
    link_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_tasks_business (business_id),
    KEY idx_tasks_business_status (business_id, status),
    KEY idx_tasks_business_owner (business_id, owner_user_id),
    KEY idx_tasks_business_assigned (business_id, assigned_user_id),
    KEY idx_tasks_due (business_id, due_at),
    KEY idx_tasks_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_tasks_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_owner_user FOREIGN KEY (owner_user_id) REFERENCES users(id) ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_time_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    is_non_job TINYINT(1) NOT NULL DEFAULT 0,
    clock_in_at DATETIME NOT NULL,
    clock_out_at DATETIME NULL,
    duration_minutes INT UNSIGNED NULL,
    clock_in_lat DECIMAL(10,7) NULL,
    clock_in_lng DECIMAL(10,7) NULL,
    clock_out_lat DECIMAL(10,7) NULL,
    clock_out_lng DECIMAL(10,7) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_time_business (business_id),
    KEY idx_time_business_employee (business_id, employee_id),
    KEY idx_time_business_job (business_id, job_id),
    KEY idx_time_business_open (business_id, clock_out_at),
    KEY idx_time_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_time_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_time_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON UPDATE CASCADE,
    CONSTRAINT fk_time_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    type ENUM('estimate','invoice') NOT NULL DEFAULT 'estimate',
    status ENUM('draft','sent','approved','partial','paid','cancelled') NOT NULL DEFAULT 'draft',
    invoice_number VARCHAR(80) NULL,
    issue_date DATE NULL,
    due_date DATE NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    customer_note TEXT NULL,
    internal_note TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_invoices_business (business_id),
    KEY idx_invoices_business_status (business_id, status),
    KEY idx_invoices_business_client (business_id, client_id),
    KEY idx_invoices_business_job (business_id, job_id),
    KEY idx_invoices_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_invoices_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_client FOREIGN KEY (client_id) REFERENCES clients(id) ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    item_type VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    note VARCHAR(255) NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    taxable TINYINT(1) NOT NULL DEFAULT 1,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_invoice_items_business (business_id),
    KEY idx_invoice_items_business_invoice (business_id, invoice_id),
    CONSTRAINT fk_invoice_items_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    paid_at DATETIME NOT NULL,
    payment_type VARCHAR(20) NOT NULL DEFAULT 'payment',
    method VARCHAR(60) NULL,
    note VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_payments_business (business_id),
    KEY idx_payments_business_invoice (business_id, invoice_id),
    KEY idx_payments_business_paid_at (business_id, paid_at),
    KEY idx_payments_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_payments_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    entity VARCHAR(120) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_business (business_id),
    KEY idx_activity_business_created (business_id, created_at),
    KEY idx_activity_user_created (user_id, created_at),
    KEY idx_activity_entity (entity, entity_id),
    CONSTRAINT fk_activity_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- >>> 2026-02-27_v3_phase_b_client_contact_flags.sql

-- JunkTracker v3 Phase B
-- Client contact fields: email + secondary can-text flag
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

SET @has_email := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'email'
);
SET @sql := IF(
    @has_email > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN email VARCHAR(190) NULL AFTER last_name'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_secondary_can_text := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'secondary_can_text'
);
SET @sql := IF(
    @has_secondary_can_text > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN secondary_can_text TINYINT(1) NOT NULL DEFAULT 0 AFTER can_text'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-02-27_v3_phase_b_client_contact_flags', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);



-- >>> 2026-02-27_v3_phase_b_client_phone_flags.sql

-- JunkTracker v3 Phase B
-- Ensure client phone/text flag columns exist for profile + form behavior
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

SET @has_secondary_can_text := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'secondary_can_text'
);
SET @sql := IF(
    @has_secondary_can_text > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN secondary_can_text TINYINT(1) NOT NULL DEFAULT 0 AFTER can_text'
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
    'ALTER TABLE clients ADD COLUMN primary_note TEXT NULL AFTER secondary_can_text'
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
SET @sql := IF(
    @has_notes > 0,
    "UPDATE clients
     SET primary_note = COALESCE(NULLIF(primary_note, ''), notes)
     WHERE (primary_note IS NULL OR primary_note = '')
       AND notes IS NOT NULL
       AND notes <> ''",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-02-27_v3_phase_b_client_phone_flags', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);


-- >>> 2026-02-27_v3_phase_b_clients_profile_fields.sql

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



-- >>> 2026-02-27_v3_phase_b_jobs_status_prospect.sql

-- JunkTracker v3 Phase B
-- Jobs status expansion: Prospect replaces separate prospect pipeline
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

-- Ensure jobs.status includes prospect.
SET @has_jobs_status := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'jobs'
      AND COLUMN_NAME = 'status'
);

SET @status_has_prospect := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'jobs'
      AND COLUMN_NAME = 'status'
      AND COLUMN_TYPE LIKE "%'prospect'%"
);

SET @sql := IF(
    @has_jobs_status = 0,
    'SELECT 1',
    IF(
        @status_has_prospect > 0,
        'SELECT 1',
        "ALTER TABLE jobs
         MODIFY COLUMN status ENUM('prospect','pending','active','complete','cancelled')
         NOT NULL DEFAULT 'pending'"
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-02-27_v3_phase_b_jobs_status_prospect', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);



-- >>> 2026-02-28_v3_phase_b_billing_status_declined.sql

-- Add "declined" to invoice status enum for estimate/invoice workflows.

SET @has_invoices := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
);

SET @sql := IF(
    @has_invoices > 0,
    "ALTER TABLE invoices
     MODIFY COLUMN status ENUM('draft','sent','approved','declined','partial','paid','cancelled')
     NOT NULL DEFAULT 'draft'",
    "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;



-- >>> 2026-02-28_v3_phase_b_site_admin_global_wall.sql

-- Ensure site_admin users remain global-only and are not tied to business memberships.

UPDATE business_user_memberships m
INNER JOIN users u ON u.id = m.user_id
SET
    m.is_active = 0,
    m.deleted_at = COALESCE(m.deleted_at, NOW()),
    m.updated_at = NOW()
WHERE u.role = 'site_admin'
  AND (m.deleted_at IS NULL OR COALESCE(m.is_active, 1) = 1);



-- >>> 2026-02-28_v3_phase_b_tasks_completion_tracking.sql

-- Task completion tracking (who marked done + when).

SET @has_completed_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND COLUMN_NAME = 'completed_at'
);
SET @sql := IF(
    @has_completed_at > 0,
    'SELECT 1',
    'ALTER TABLE tasks ADD COLUMN completed_at DATETIME NULL AFTER due_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_completed_by := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND COLUMN_NAME = 'completed_by'
);
SET @sql := IF(
    @has_completed_by > 0,
    'SELECT 1',
    'ALTER TABLE tasks ADD COLUMN completed_by BIGINT UNSIGNED NULL AFTER completed_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx_completed_at := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND INDEX_NAME = 'idx_tasks_completed_at'
);
SET @sql := IF(
    @has_idx_completed_at > 0,
    'SELECT 1',
    'ALTER TABLE tasks ADD KEY idx_tasks_completed_at (business_id, completed_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx_completed_by := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND INDEX_NAME = 'idx_tasks_completed_by'
);
SET @sql := IF(
    @has_idx_completed_by > 0,
    'SELECT 1',
    'ALTER TABLE tasks ADD KEY idx_tasks_completed_by (business_id, completed_by)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk_completed_by := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_tasks_completed_by_user'
);
SET @sql := IF(
    @has_fk_completed_by > 0,
    'SELECT 1',
    'ALTER TABLE tasks
     ADD CONSTRAINT fk_tasks_completed_by_user
     FOREIGN KEY (completed_by)
     REFERENCES users(id)
     ON UPDATE CASCADE
     ON DELETE SET NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;



-- >>> 2026-03-01_v3_phase_b_business_details_profile.sql

-- v3 Phase B: Business details profile fields for estimate/invoice output
-- Date: 2026-03-01

START TRANSACTION;

ALTER TABLE businesses
    ADD COLUMN primary_contact_name VARCHAR(190) NULL AFTER phone,
    ADD COLUMN website_url VARCHAR(255) NULL AFTER primary_contact_name,
    ADD COLUMN ein_number VARCHAR(40) NULL AFTER website_url,
    ADD COLUMN mailing_same_as_physical TINYINT(1) NOT NULL DEFAULT 1 AFTER country,
    ADD COLUMN mailing_address_line1 VARCHAR(190) NULL AFTER mailing_same_as_physical,
    ADD COLUMN mailing_address_line2 VARCHAR(190) NULL AFTER mailing_address_line1,
    ADD COLUMN mailing_city VARCHAR(120) NULL AFTER mailing_address_line2,
    ADD COLUMN mailing_state VARCHAR(60) NULL AFTER mailing_city,
    ADD COLUMN mailing_postal_code VARCHAR(30) NULL AFTER mailing_state,
    ADD COLUMN mailing_country VARCHAR(80) NULL DEFAULT 'US' AFTER mailing_postal_code;

UPDATE businesses
SET
    mailing_address_line1 = COALESCE(NULLIF(mailing_address_line1, ''), address_line1),
    mailing_address_line2 = COALESCE(NULLIF(mailing_address_line2, ''), address_line2),
    mailing_city = COALESCE(NULLIF(mailing_city, ''), city),
    mailing_state = COALESCE(NULLIF(mailing_state, ''), state),
    mailing_postal_code = COALESCE(NULLIF(mailing_postal_code, ''), postal_code),
    mailing_country = COALESCE(NULLIF(mailing_country, ''), country),
    mailing_same_as_physical = 1
WHERE deleted_at IS NULL;

COMMIT;


-- >>> 2026-03-01_v3_phase_b_invoice_item_types.sql

-- v3 Phase B: Invoice item types
-- Date: 2026-03-01

START TRANSACTION;

CREATE TABLE IF NOT EXISTS invoice_item_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(90) NOT NULL,
    default_unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    default_taxable TINYINT(1) NOT NULL DEFAULT 1,
    default_note VARCHAR(255) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_iit_business (business_id),
    KEY idx_iit_business_active (business_id, is_active),
    KEY idx_iit_business_sort (business_id, sort_order),
    KEY idx_iit_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_iit_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;


-- >>> 2026-03-01_v3_phase_b_sales_client_link.sql

-- v3 Phase B: Link sales to optional client
-- Date: 2026-03-01

START TRANSACTION;

SET @sales_table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sales'
);

SET @add_client_column_sql := IF(
    @sales_table_exists > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sales'
          AND COLUMN_NAME = 'client_id'
    ) = 0,
    'ALTER TABLE sales ADD COLUMN client_id BIGINT UNSIGNED NULL AFTER business_id',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_client_column_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @add_client_index_sql := IF(
    @sales_table_exists > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sales'
          AND INDEX_NAME = 'idx_sales_business_client'
    ) = 0,
    'ALTER TABLE sales ADD INDEX idx_sales_business_client (business_id, client_id)',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_client_index_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @add_client_fk_sql := IF(
    @sales_table_exists > 0
    AND (
        SELECT COUNT(*)
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sales'
          AND CONSTRAINT_NAME = 'fk_sales_client'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) = 0,
    'ALTER TABLE sales ADD CONSTRAINT fk_sales_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE jt_stmt FROM @add_client_fk_sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;


-- >>> 2026-03-01_v3_phase_b_sales_table.sql

-- v3 Phase B: Sales table for simple sales index
-- Date: 2026-03-01

START TRANSACTION;

CREATE TABLE IF NOT EXISTS sales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    sale_type VARCHAR(60) NULL,
    sale_date DATETIME NULL,
    gross_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sales_business (business_id),
    KEY idx_sales_business_type (business_id, sale_type),
    KEY idx_sales_business_date (business_id, sale_date),
    KEY idx_sales_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_sales_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;


-- >>> 2026-03-02_v3_phase_b_invoice_status_split.sql

-- Invoice statuses are now separate from estimate statuses.
-- Estimates: draft, sent, approved, declined.
-- Invoices: unsent, sent, partially_paid, paid_in_full.

SET @schema := DATABASE();

-- Ensure invoices.status can store both legacy and new values safely.
SET @alter_sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'invoices'
          AND COLUMN_NAME = 'status'
    ),
    "ALTER TABLE invoices
      MODIFY COLUMN status ENUM(
        'draft','sent','approved','declined',
        'unsent','partially_paid','paid_in_full',
        'partial','paid','cancelled'
      ) NOT NULL DEFAULT 'draft'",
    'SELECT 1'
);
PREPARE stmt FROM @alter_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill legacy invoice rows into the new invoice status names.
UPDATE invoices
SET status = 'unsent'
WHERE LOWER(COALESCE(type, 'invoice')) = 'invoice'
  AND status = 'draft';

UPDATE invoices
SET status = 'partially_paid'
WHERE LOWER(COALESCE(type, 'invoice')) = 'invoice'
  AND status = 'partial';

UPDATE invoices
SET status = 'paid_in_full'
WHERE LOWER(COALESCE(type, 'invoice')) = 'invoice'
  AND status = 'paid';


-- >>> 2026-03-03_v3_phase_b_clients_company_type.sql

-- Clients: add company client_type option for business-only records.
SET @schema := DATABASE();

SET @has_clients := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
);

SET @has_client_type := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'client_type'
);

SET @has_company_type := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'client_type'
      AND COLUMN_TYPE LIKE "%'company'%"
);

SET @sql := IF(
    @has_clients = 1 AND @has_client_type = 1 AND @has_company_type = 0,
    "ALTER TABLE clients MODIFY COLUMN client_type ENUM('realtor','client','company','other') NOT NULL DEFAULT 'client'",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;



-- >>> 2026-03-03_v3_phase_b_job_expenses.sql

-- v3 Phase B - Job Expenses
-- Date: 2026-03-03

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NULL,
    expense_date DATE NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(80) NULL,
    payment_method VARCHAR(80) NULL,
    note VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_expenses_business (business_id),
    KEY idx_expenses_business_job (business_id, job_id),
    KEY idx_expenses_business_date (business_id, expense_date),
    KEY idx_expenses_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_expenses_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_expenses_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- >>> 2026-03-03_v3_phase_b_payments_payment_type.sql

-- Payments: add payment_type (deposit/payment) for reporting + UX.
SET @schema := DATABASE();

SET @has_payments := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'payments'
);

SET @has_payment_type := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'payment_type'
);

SET @sql := IF(
    @has_payments = 1 AND @has_payment_type = 0,
    "ALTER TABLE payments ADD COLUMN payment_type VARCHAR(20) NOT NULL DEFAULT 'payment' AFTER paid_at",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;



-- >>> 2026-03-03_v3_phase_b_payments_reference_number.sql

-- Payments: add optional reference_number (check # / Venmo ID / etc).
SET @schema := DATABASE();

SET @has_payments := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'payments'
);

SET @has_reference := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'reference_number'
);

SET @sql := IF(
    @has_payments = 1 AND @has_reference = 0,
    'ALTER TABLE payments ADD COLUMN reference_number VARCHAR(120) NULL AFTER method',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- >>> 2026-03-04_v3_phase_b_expenses_name_payment_method.sql

-- v3 Phase B - Expense name and payment method
-- Date: 2026-03-04

SET NAMES utf8mb4;

SET @add_name_col := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'expenses'
              AND COLUMN_NAME = 'name'
        ),
        'SELECT 1',
        'ALTER TABLE expenses ADD COLUMN name VARCHAR(120) NULL AFTER job_id'
    )
);
PREPARE stmt_exp_name FROM @add_name_col;
EXECUTE stmt_exp_name;
DEALLOCATE PREPARE stmt_exp_name;

SET @add_payment_method_col := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'expenses'
              AND COLUMN_NAME = 'payment_method'
        ),
        'SELECT 1',
        'ALTER TABLE expenses ADD COLUMN payment_method VARCHAR(80) NULL AFTER category'
    )
);
PREPARE stmt_exp_payment_method FROM @add_payment_method_col;
EXECUTE stmt_exp_payment_method;
DEALLOCATE PREPARE stmt_exp_payment_method;


-- >>> 2026-03-05_v3_phase_c_time_tracking_employees.sql

-- v3 Phase C - Employee profile fields for time tracking
-- Date: 2026-03-05

SET NAMES utf8mb4;

SET @add_suffix_col := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'employees'
              AND COLUMN_NAME = 'suffix'
        ),
        'SELECT 1',
        'ALTER TABLE employees ADD COLUMN suffix VARCHAR(20) NULL AFTER last_name'
    )
);
PREPARE stmt_emp_add_suffix FROM @add_suffix_col;
EXECUTE stmt_emp_add_suffix;
DEALLOCATE PREPARE stmt_emp_add_suffix;

SET @add_note_col := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'employees'
              AND COLUMN_NAME = 'note'
        ),
        'SELECT 1',
        'ALTER TABLE employees ADD COLUMN note TEXT NULL AFTER user_id'
    )
);
PREPARE stmt_emp_add_note FROM @add_note_col;
EXECUTE stmt_emp_add_note;
DEALLOCATE PREPARE stmt_emp_add_note;


-- >>> 2026-03-06_v3_phase_c_job_adjustments.sql

-- v3 Phase C - Job labor adjustments
-- Date: 2026-03-06

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS job_adjustments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NULL,
    adjustment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_job_adjustments_business (business_id),
    KEY idx_job_adjustments_job (business_id, job_id),
    KEY idx_job_adjustments_date (business_id, adjustment_date),
    KEY idx_job_adjustments_deleted (business_id, deleted_at),
    CONSTRAINT fk_job_adjustments_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_job_adjustments_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- >>> 2026-03-06_v3_phase_c_job_adjustments_name.sql

-- v3 Phase C - Job adjustment name field
-- Date: 2026-03-06

SET NAMES utf8mb4;

SET @add_name_col := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'job_adjustments'
              AND COLUMN_NAME = 'name'
        ),
        'SELECT 1',
        'ALTER TABLE job_adjustments ADD COLUMN name VARCHAR(120) NULL AFTER job_id'
    )
);
PREPARE stmt_job_adj_name FROM @add_name_col;
EXECUTE stmt_job_adj_name;
DEALLOCATE PREPARE stmt_job_adj_name;


-- >>> 2026-03-06_v3_phase_c_job_employee_assignments.sql

-- v3 Phase C - Job Employee Assignments
-- Date: 2026-03-06

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS job_employee_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_job_employee (business_id, job_id, employee_id),
    KEY idx_job_employee_assignments_job (business_id, job_id),
    KEY idx_job_employee_assignments_employee (business_id, employee_id),
    KEY idx_job_employee_assignments_deleted (business_id, deleted_at),
    CONSTRAINT fk_job_employee_assignments_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_job_employee_assignments_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON UPDATE CASCADE,
    CONSTRAINT fk_job_employee_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- >>> 2026-03-06_v3_phase_c_purchases.sql

-- v3 Phase C - Purchases
-- Date: 2026-03-06

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS purchases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    status ENUM('prospect','pending','active','complete','cancelled') NOT NULL DEFAULT 'prospect',
    contact_date DATE NULL,
    purchase_date DATE NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_purchases_business (business_id),
    KEY idx_purchases_business_status (business_id, status),
    KEY idx_purchases_business_client (business_id, client_id),
    KEY idx_purchases_business_contact (business_id, contact_date),
    KEY idx_purchases_business_purchase (business_id, purchase_date),
    KEY idx_purchases_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_purchases_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_purchases_client FOREIGN KEY (client_id) REFERENCES clients(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

