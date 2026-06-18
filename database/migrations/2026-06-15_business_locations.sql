-- Operating locations (store, warehouse, terminal, other) + employee defaults
-- Date: 2026-06-15

START TRANSACTION;

CREATE TABLE IF NOT EXISTS business_locations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    location_type VARCHAR(30) NOT NULL DEFAULT 'other',
    name VARCHAR(150) NOT NULL,
    address_line1 VARCHAR(190) NULL,
    address_line2 VARCHAR(190) NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(60) NULL,
    postal_code VARCHAR(30) NULL,
    country VARCHAR(80) NULL DEFAULT 'US',
    phone VARCHAR(40) NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_business_locations_business (business_id, deleted_at),
    KEY idx_business_locations_type (business_id, location_type, deleted_at),
    CONSTRAINT fk_business_locations_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'default_store_location_id'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE employees
        ADD COLUMN default_store_location_id BIGINT UNSIGNED NULL AFTER user_id,
        ADD COLUMN default_warehouse_location_id BIGINT UNSIGNED NULL AFTER default_store_location_id,
        ADD COLUMN default_terminal_location_id BIGINT UNSIGNED NULL AFTER default_warehouse_location_id,
        ADD CONSTRAINT fk_employees_default_store_location FOREIGN KEY (default_store_location_id) REFERENCES business_locations(id) ON DELETE SET NULL ON UPDATE CASCADE,
        ADD CONSTRAINT fk_employees_default_warehouse_location FOREIGN KEY (default_warehouse_location_id) REFERENCES business_locations(id) ON DELETE SET NULL ON UPDATE CASCADE,
        ADD CONSTRAINT fk_employees_default_terminal_location FOREIGN KEY (default_terminal_location_id) REFERENCES business_locations(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
