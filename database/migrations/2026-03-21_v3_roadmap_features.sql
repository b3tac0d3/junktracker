-- Roadmap batch: client portal tokens, job close-out, bank deposits, deposit↔payment links
-- Date: 2026-03-21

START TRANSACTION;

SET @schema := DATABASE();

-- client_portal_access
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'client_portal_access') > 0,
    'SELECT 1',
    'CREATE TABLE client_portal_access (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        business_id BIGINT UNSIGNED NOT NULL,
        invoice_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_portal_invoice (invoice_id),
        KEY idx_portal_token (token_hash),
        KEY idx_portal_business (business_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- bank_deposits
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'bank_deposits') > 0,
    'SELECT 1',
    'CREATE TABLE bank_deposits (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        business_id BIGINT UNSIGNED NOT NULL,
        deposit_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        note VARCHAR(500) NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_bd_business_date (business_id, deposit_date),
        KEY idx_bd_business_deleted (business_id, deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- bank_deposit_payments
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'bank_deposit_payments') > 0,
    'SELECT 1',
    'CREATE TABLE bank_deposit_payments (
        deposit_id BIGINT UNSIGNED NOT NULL,
        payment_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (deposit_id, payment_id),
        KEY idx_bdp_payment (payment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- jobs.closeout_* (idempotent)
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'jobs') = 0,
    'SELECT 1',
    IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'closeout_truck_loaded'),
        'SELECT 1',
        'ALTER TABLE jobs ADD COLUMN closeout_truck_loaded TINYINT(1) NOT NULL DEFAULT 0 AFTER notes'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'jobs') = 0,
    'SELECT 1',
    IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'closeout_site_clean'),
        'SELECT 1',
        'ALTER TABLE jobs ADD COLUMN closeout_site_clean TINYINT(1) NOT NULL DEFAULT 0 AFTER closeout_truck_loaded'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'jobs') = 0,
    'SELECT 1',
    IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'closeout_signature_name'),
        'SELECT 1',
        'ALTER TABLE jobs ADD COLUMN closeout_signature_name VARCHAR(190) NULL DEFAULT NULL AFTER closeout_site_clean'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'jobs') = 0,
    'SELECT 1',
    IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'closeout_completed_at'),
        'SELECT 1',
        'ALTER TABLE jobs ADD COLUMN closeout_completed_at DATETIME NULL DEFAULT NULL AFTER closeout_signature_name'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
