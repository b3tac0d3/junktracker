-- JunkTracker Beta 1.3.0 Feature Bundle
-- Includes:
-- 1) Scheduling board support (no schema change required)
-- 2) Estimate/Invoice workflow tables
-- 3) Notification center state table
-- 4) Global attachments table
-- 5) Reporting preset table
-- 6) Role permission rows for notifications/reports/data_quality

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
-- 1) Estimate / Invoice workflow
-- =========================================================
CREATE TABLE IF NOT EXISTS job_estimate_invoices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(20) NOT NULL,
    title VARCHAR(190) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    amount DECIMAL(12,2) NULL,
    issued_at DATETIME NULL,
    due_at DATETIME NULL,
    sent_at DATETIME NULL,
    approved_at DATETIME NULL,
    paid_at DATETIME NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_job_estimate_invoices_job (job_id),
    KEY idx_job_estimate_invoices_type_status (document_type, status),
    KEY idx_job_estimate_invoices_deleted (deleted_at),
    KEY idx_job_estimate_invoices_created_by (created_by),
    KEY idx_job_estimate_invoices_updated_by (updated_by),
    KEY idx_job_estimate_invoices_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_estimate_invoice_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    from_status VARCHAR(30) NULL,
    to_status VARCHAR(30) NULL,
    event_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_job_estimate_invoice_events_doc (document_id, created_at),
    KEY idx_job_estimate_invoice_events_job (job_id, created_at),
    KEY idx_job_estimate_invoice_events_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoices_job'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE job_estimate_invoices
     ADD CONSTRAINT fk_job_estimate_invoices_job
     FOREIGN KEY (job_id)
     REFERENCES jobs(id)
     ON DELETE CASCADE
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoices_created_by'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE job_estimate_invoices
     ADD CONSTRAINT fk_job_estimate_invoices_created_by
     FOREIGN KEY (created_by)
     REFERENCES users(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoices_updated_by'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE job_estimate_invoices
     ADD CONSTRAINT fk_job_estimate_invoices_updated_by
     FOREIGN KEY (updated_by)
     REFERENCES users(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoices_deleted_by'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE job_estimate_invoices
     ADD CONSTRAINT fk_job_estimate_invoices_deleted_by
     FOREIGN KEY (deleted_by)
     REFERENCES users(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoice_events_doc'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE job_estimate_invoice_events
     ADD CONSTRAINT fk_job_estimate_invoice_events_doc
     FOREIGN KEY (document_id)
     REFERENCES job_estimate_invoices(id)
     ON DELETE CASCADE
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoice_events_job'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE job_estimate_invoice_events
     ADD CONSTRAINT fk_job_estimate_invoice_events_job
     FOREIGN KEY (job_id)
     REFERENCES jobs(id)
     ON DELETE CASCADE
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoice_events_created_by'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE job_estimate_invoice_events
     ADD CONSTRAINT fk_job_estimate_invoice_events_created_by
     FOREIGN KEY (created_by)
     REFERENCES users(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 2) Notification state tracking
-- =========================================================
CREATE TABLE IF NOT EXISTS user_notification_states (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    notification_key VARCHAR(190) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    dismissed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_user_notification_state (user_id, notification_key),
    KEY idx_user_notification_states_user (user_id),
    KEY idx_user_notification_states_read (user_id, is_read),
    KEY idx_user_notification_states_dismissed (user_id, dismissed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_user_notification_states_user'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE user_notification_states
     ADD CONSTRAINT fk_user_notification_states_user
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
-- 3) Global attachments
-- =========================================================
CREATE TABLE IF NOT EXISTS attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    link_type VARCHAR(40) NOT NULL,
    link_id BIGINT UNSIGNED NOT NULL,
    tag VARCHAR(40) NOT NULL DEFAULT 'other',
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size BIGINT UNSIGNED NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_attachments_link (link_type, link_id),
    KEY idx_attachments_tag (tag),
    KEY idx_attachments_deleted (deleted_at),
    KEY idx_attachments_created_by (created_by),
    KEY idx_attachments_updated_by (updated_by),
    KEY idx_attachments_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_attachments_created_by'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE attachments
     ADD CONSTRAINT fk_attachments_created_by
     FOREIGN KEY (created_by)
     REFERENCES users(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_attachments_updated_by'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE attachments
     ADD CONSTRAINT fk_attachments_updated_by
     FOREIGN KEY (updated_by)
     REFERENCES users(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_attachments_deleted_by'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE attachments
     ADD CONSTRAINT fk_attachments_deleted_by
     FOREIGN KEY (deleted_by)
     REFERENCES users(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 4) Reporting presets
-- =========================================================
CREATE TABLE IF NOT EXISTS report_presets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    report_key VARCHAR(60) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    filters_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_report_presets_user_name (user_id, name),
    KEY idx_report_presets_user_key (user_id, report_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_report_presets_user'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE report_presets
     ADD CONSTRAINT fk_report_presets_user
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
-- 5) Role permissions for new modules
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
    (1, 'notifications', 1, 0, 0, 0, NULL, NOW()),
    (2, 'notifications', 1, 0, 0, 0, NULL, NOW()),
    (3, 'notifications', 1, 1, 1, 1, NULL, NOW()),
    (1, 'reports', 1, 1, 1, 1, NULL, NOW()),
    (2, 'reports', 1, 1, 1, 1, NULL, NOW()),
    (3, 'reports', 1, 1, 1, 1, NULL, NOW()),
    (1, 'data_quality', 0, 0, 0, 0, NULL, NOW()),
    (2, 'data_quality', 1, 0, 0, 0, NULL, NOW()),
    (3, 'data_quality', 1, 1, 1, 1, NULL, NOW())
ON DUPLICATE KEY UPDATE
    can_view = VALUES(can_view),
    can_create = VALUES(can_create),
    can_edit = VALUES(can_edit),
    can_delete = VALUES(can_delete),
    updated_by = VALUES(updated_by),
    updated_at = NOW();

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at) VALUES
('2026-02-19_beta_1.3.0_feature_bundle', 'beta-1.3.0-feature-bundle', NOW());
