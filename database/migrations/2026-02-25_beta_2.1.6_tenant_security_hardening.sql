-- Beta 2.1.6 tenant security hardening
-- Adds business scoping columns/indexes used by runtime guards.

-- ------------------------------------------------------------
-- consignors
-- ------------------------------------------------------------
SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignors'
);

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignors'
      AND COLUMN_NAME = 'business_id'
);
SET @sql := IF(@table_exists > 0 AND @exists = 0,
    'ALTER TABLE consignors ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@table_exists > 0,
    'UPDATE consignors SET business_id = 1 WHERE business_id IS NULL OR business_id = 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignors'
      AND INDEX_NAME = 'idx_consignors_business'
);
SET @sql := IF(@table_exists > 0 AND @idx_exists = 0,
    'CREATE INDEX idx_consignors_business ON consignors (business_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- consignor child tables
-- ------------------------------------------------------------
SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignor_contacts'
);

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignor_contacts'
      AND COLUMN_NAME = 'business_id'
);
SET @sql := IF(@table_exists > 0 AND @exists = 0,
    'ALTER TABLE consignor_contacts ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER consignor_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@table_exists > 0,
    'UPDATE consignor_contacts cc INNER JOIN consignors c ON c.id = cc.consignor_id SET cc.business_id = c.business_id WHERE cc.business_id IS NULL OR cc.business_id = 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignor_contacts'
      AND INDEX_NAME = 'idx_consignor_contacts_business'
);
SET @sql := IF(@table_exists > 0 AND @idx_exists = 0,
    'CREATE INDEX idx_consignor_contacts_business ON consignor_contacts (business_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignor_contracts'
);

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignor_contracts'
      AND COLUMN_NAME = 'business_id'
);
SET @sql := IF(@table_exists > 0 AND @exists = 0,
    'ALTER TABLE consignor_contracts ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER consignor_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@table_exists > 0,
    'UPDATE consignor_contracts cc INNER JOIN consignors c ON c.id = cc.consignor_id SET cc.business_id = c.business_id WHERE cc.business_id IS NULL OR cc.business_id = 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignor_contracts'
      AND INDEX_NAME = 'idx_consignor_contracts_business'
);
SET @sql := IF(@table_exists > 0 AND @idx_exists = 0,
    'CREATE INDEX idx_consignor_contracts_business ON consignor_contracts (business_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignor_payouts'
);

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignor_payouts'
      AND COLUMN_NAME = 'business_id'
);
SET @sql := IF(@table_exists > 0 AND @exists = 0,
    'ALTER TABLE consignor_payouts ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER consignor_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@table_exists > 0,
    'UPDATE consignor_payouts cp INNER JOIN consignors c ON c.id = cp.consignor_id SET cp.business_id = c.business_id WHERE cp.business_id IS NULL OR cp.business_id = 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consignor_payouts'
      AND INDEX_NAME = 'idx_consignor_payouts_business'
);
SET @sql := IF(@table_exists > 0 AND @idx_exists = 0,
    'CREATE INDEX idx_consignor_payouts_business ON consignor_payouts (business_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- report presets
-- ------------------------------------------------------------
SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_presets'
);

SET @sql := IF(@table_exists = 0,
    'CREATE TABLE report_presets (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
        name VARCHAR(120) NOT NULL,
        report_key VARCHAR(60) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        filters_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_report_presets_user_business_name (user_id, business_id, name),
        KEY idx_report_presets_user_key (user_id, business_id, report_key),
        KEY idx_report_presets_business (business_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_presets'
      AND COLUMN_NAME = 'business_id'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE report_presets ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE report_presets
SET business_id = 1
WHERE business_id IS NULL OR business_id = 0;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_presets'
      AND INDEX_NAME = 'uniq_report_presets_user_name'
);
SET @sql := IF(@idx_exists > 0,
    'ALTER TABLE report_presets DROP INDEX uniq_report_presets_user_name',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_presets'
      AND INDEX_NAME = 'uniq_report_presets_user_business_name'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE report_presets ADD UNIQUE KEY uniq_report_presets_user_business_name (user_id, business_id, name)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_presets'
      AND INDEX_NAME = 'idx_report_presets_user_key'
);
SET @sql := IF(@idx_exists > 0,
    'ALTER TABLE report_presets DROP INDEX idx_report_presets_user_key',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_presets'
      AND INDEX_NAME = 'idx_report_presets_user_key'
);
SET @sql := IF(@table_exists > 0 AND @idx_exists = 0,
    'ALTER TABLE report_presets ADD KEY idx_report_presets_user_key (user_id, business_id, report_key)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_presets'
      AND INDEX_NAME = 'idx_report_presets_business'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_report_presets_business ON report_presets (business_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- dashboard KPI snapshots
-- ------------------------------------------------------------
SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dashboard_kpi_snapshots'
);

SET @sql := IF(@table_exists = 0,
    'CREATE TABLE dashboard_kpi_snapshots (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
        snapshot_date DATE NOT NULL,
        metrics_json LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_dashboard_kpi_snapshots_business_date (business_id, snapshot_date),
        KEY idx_dashboard_kpi_snapshots_business (business_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dashboard_kpi_snapshots'
      AND COLUMN_NAME = 'business_id'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE dashboard_kpi_snapshots ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE dashboard_kpi_snapshots
SET business_id = 1
WHERE business_id IS NULL OR business_id = 0;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dashboard_kpi_snapshots'
      AND INDEX_NAME = 'uniq_dashboard_kpi_snapshots_date'
);
SET @sql := IF(@idx_exists > 0,
    'ALTER TABLE dashboard_kpi_snapshots DROP INDEX uniq_dashboard_kpi_snapshots_date',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dashboard_kpi_snapshots'
      AND INDEX_NAME = 'uniq_dashboard_kpi_snapshots_business_date'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE dashboard_kpi_snapshots ADD UNIQUE KEY uniq_dashboard_kpi_snapshots_business_date (business_id, snapshot_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dashboard_kpi_snapshots'
      AND INDEX_NAME = 'idx_dashboard_kpi_snapshots_business'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_dashboard_kpi_snapshots_business ON dashboard_kpi_snapshots (business_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Record migration for tracker (safe no-op if tracker missing)
SET @tracker_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'schema_migrations'
);
SET @sql := IF(@tracker_exists > 0,
    'INSERT INTO schema_migrations (migration_key, checksum, applied_at)
     VALUES (''2026-02-25_beta_2.1.6_tenant_security_hardening'', ''manual'', NOW())
     ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), applied_at = VALUES(applied_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
