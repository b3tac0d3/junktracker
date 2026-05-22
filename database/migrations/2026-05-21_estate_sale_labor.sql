-- Estate sale labor: employee assignments + time entry linkage
-- Date: 2026-05-21

START TRANSACTION;

SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS estate_sale_employee_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    estate_sale_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_estate_sale_employee (business_id, estate_sale_id, employee_id),
    KEY idx_estate_sale_employee_assignments_sale (business_id, estate_sale_id),
    KEY idx_estate_sale_employee_assignments_employee (business_id, employee_id),
    KEY idx_estate_sale_employee_assignments_deleted (business_id, deleted_at),
    CONSTRAINT fk_estate_sale_employee_assignments_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_estate_sale_employee_assignments_sale FOREIGN KEY (estate_sale_id) REFERENCES estate_sales(id) ON UPDATE CASCADE,
    CONSTRAINT fk_estate_sale_employee_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add employee_time_entries.estate_sale_id
SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'employee_time_entries'
              AND COLUMN_NAME = 'estate_sale_id'
        ),
        'SELECT 1',
        'ALTER TABLE employee_time_entries ADD COLUMN estate_sale_id BIGINT UNSIGNED NULL AFTER job_id'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'employee_time_entries'
              AND INDEX_NAME = 'idx_time_business_estate_sale'
        ),
        'SELECT 1',
        'ALTER TABLE employee_time_entries ADD INDEX idx_time_business_estate_sale (business_id, estate_sale_id)'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'employee_time_entries'
    ) = 0
    OR (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sales'
    ) = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = 'employee_time_entries'
              AND CONSTRAINT_NAME = 'fk_time_estate_sale'
        ),
        'SELECT 1',
        'ALTER TABLE employee_time_entries
            ADD CONSTRAINT fk_time_estate_sale
            FOREIGN KEY (estate_sale_id) REFERENCES estate_sales(id)
            ON DELETE SET NULL
            ON UPDATE CASCADE'
    )
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
