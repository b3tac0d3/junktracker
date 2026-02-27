-- JunkTracker v3 Phase B
-- Clients index foundation + sample data
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

-- Compatibility patch for pre-v3 client schema.
SET @has_business_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'business_id'
);
SET @sql := IF(
    @has_business_id > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN business_id BIGINT UNSIGNED NULL AFTER id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_company_name := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'company_name'
);
SET @sql := IF(
    @has_company_name > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN company_name VARCHAR(150) NULL AFTER last_name'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_address_line1 := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'address_line1'
);
SET @sql := IF(
    @has_address_line1 > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN address_line1 VARCHAR(190) NULL AFTER phone'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_city := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'city'
);
SET @sql := IF(
    @has_city > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN city VARCHAR(120) NULL AFTER address_line1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_state := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'state'
);
SET @sql := IF(
    @has_state > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN state VARCHAR(60) NULL AFTER city'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_postal_code := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'postal_code'
);
SET @sql := IF(
    @has_postal_code > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN postal_code VARCHAR(30) NULL AFTER state'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_status := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'status'
);
SET @sql := IF(
    @has_status > 0,
    'SELECT 1',
    "ALTER TABLE clients ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER postal_code"
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
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN notes TEXT NULL AFTER status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_created_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(
    @has_created_at > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_updated_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(
    @has_updated_at > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_deleted_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'deleted_at'
);
SET @sql := IF(
    @has_deleted_at > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN deleted_at DATETIME NULL AFTER updated_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_note := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'note'
);
SET @sql := IF(
    @has_note > 0,
    "UPDATE clients
     SET notes = COALESCE(NULLIF(notes, ''''), note)
     WHERE (notes IS NULL OR notes = '''')
       AND note IS NOT NULL
       AND note <> ''''",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_is_active := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'is_active'
);
SET @sql := IF(
    @has_is_active > 0,
    "UPDATE clients
     SET status = CASE WHEN COALESCE(is_active, 1) = 1 THEN 'active' ELSE 'inactive' END
     WHERE status IS NULL OR status = ''",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE clients SET business_id = 1 WHERE business_id IS NULL OR business_id = 0;

-- Ensure client_type exists for simplified client tracking.
SET @has_client_type := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'client_type'
);

SET @sql := IF(
    @has_client_type > 0,
    'SELECT 1',
    "ALTER TABLE clients
     ADD COLUMN client_type ENUM('realtor','client','other') NOT NULL DEFAULT 'client' AFTER phone,
     ADD KEY idx_clients_business_type (business_id, client_type)"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Seed a few businesses for multi-business testing.
INSERT INTO businesses (
    id, name, legal_name, email, phone, is_active, created_at, updated_at
) VALUES
    (1, 'Jimmy''s Junk', 'Jimmy''s Junk, LLC', 'hello@jimmysjunk.com', '401-555-1001', 1, NOW(), NOW()),
    (2, 'Metro Haul Co', 'Metro Haul Company', 'hello@metrohaul.com', '401-555-2001', 1, NOW(), NOW()),
    (3, 'Coastal Cleanouts', 'Coastal Cleanouts LLC', 'hello@coastalcleanouts.com', '401-555-3001', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    legal_name = VALUES(legal_name),
    email = VALUES(email),
    phone = VALUES(phone),
    is_active = VALUES(is_active),
    updated_at = NOW();

-- Business-scoped client samples.
INSERT INTO clients (
    id, business_id, first_name, last_name, company_name, phone, client_type,
    address_line1, city, state, postal_code, notes, status, created_at, updated_at
) VALUES
    (1001, 1, 'Annabelle', 'McGovern', NULL, '860-803-3249', 'client', '14 Cedar St', 'Warwick', 'RI', '02886', 'Prefers text before calls.', 'active', NOW(), NOW()),
    (1002, 1, 'Jazzy', 'Fae', NULL, '401-555-1010', 'client', '99 Maple Ave', 'Cranston', 'RI', '02910', 'Evening availability only.', 'active', NOW(), NOW()),
    (1003, 1, 'Steven', 'Parker', NULL, '401-555-1011', 'client', '12 Ferry Rd', 'Bristol', 'RI', '02809', 'Has gate code for side entry.', 'active', NOW(), NOW()),
    (1004, 1, 'Sarah', 'Wheaton', NULL, '401-555-1012', 'realtor', '5 Harbor View Dr', 'Newport', 'RI', '02840', 'Referral partner for estate cleanouts.', 'active', NOW(), NOW()),
    (1005, 1, NULL, NULL, 'McGovern FPS', '401-555-1013', 'other', '1 Industrial Pkwy', 'West Warwick', 'RI', '02893', 'Commercial account.', 'active', NOW(), NOW()),
    (2001, 2, 'Logan', 'Goins', NULL, '617-555-2001', 'client', '18 River St', 'Boston', 'MA', '02118', 'Needs COI before service date.', 'active', NOW(), NOW()),
    (2002, 2, 'Julia', 'Nash', NULL, '617-555-2002', 'realtor', '42 Garden Ln', 'Brookline', 'MA', '02445', 'Coordinates move-out schedules.', 'active', NOW(), NOW()),
    (2003, 2, NULL, NULL, 'Metro Property Group', '617-555-2003', 'other', '88 Beacon St', 'Boston', 'MA', '02108', 'Monthly hauling contract.', 'active', NOW(), NOW()),
    (3001, 3, 'Caleb', 'Morris', NULL, '203-555-3001', 'client', '7 Elm Terrace', 'Mystic', 'CT', '06355', 'Wants photo updates during job.', 'active', NOW(), NOW()),
    (3002, 3, 'Nina', 'Drake', NULL, '203-555-3002', 'realtor', '24 Willow Ct', 'Groton', 'CT', '06340', 'Often books same-week jobs.', 'active', NOW(), NOW()),
    (3003, 3, NULL, NULL, 'Coastal Estate Services', '203-555-3003', 'other', '300 Main St', 'New London', 'CT', '06320', 'Estate coordination partner.', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    company_name = VALUES(company_name),
    phone = VALUES(phone),
    client_type = VALUES(client_type),
    address_line1 = VALUES(address_line1),
    city = VALUES(city),
    state = VALUES(state),
    postal_code = VALUES(postal_code),
    notes = VALUES(notes),
    status = VALUES(status),
    updated_at = NOW();

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-02-27_v3_phase_b_clients_index_seed', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
