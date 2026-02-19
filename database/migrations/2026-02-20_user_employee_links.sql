-- JunkTracker: employee <-> user linking for punch-me workflows
-- Safe to run multiple times on MySQL/MariaDB without IF NOT EXISTS in ALTER TABLE.

-- 1) Add employees.user_id when missing
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'user_id'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE employees ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER email',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Add supporting index when missing
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND INDEX_NAME = 'idx_employees_user_id'
);
SET @ddl := IF(
    @idx_exists = 0,
    'ALTER TABLE employees ADD INDEX idx_employees_user_id (user_id)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Normalize duplicates before unique key enforcement
UPDATE employees e
INNER JOIN (
    SELECT user_id, MIN(id) AS keep_id
    FROM employees
    WHERE user_id IS NOT NULL
    GROUP BY user_id
    HAVING COUNT(*) > 1
) d ON d.user_id = e.user_id
SET e.user_id = NULL
WHERE e.id <> d.keep_id;

-- 4) Enforce one employee link per user
SET @uniq_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND INDEX_NAME = 'uniq_employees_user_id'
);
SET @ddl := IF(
    @uniq_exists = 0,
    'ALTER TABLE employees ADD UNIQUE KEY uniq_employees_user_id (user_id)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5) Foreign key to users.id
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_employees_user'
);
SET @ddl := IF(
    @fk_exists = 0,
    'ALTER TABLE employees
     ADD CONSTRAINT fk_employees_user
     FOREIGN KEY (user_id)
     REFERENCES users(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(190) NOT NULL,
    checksum VARCHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_schema_migrations_key (migration_key),
    KEY idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at)
VALUES ('2026-02-20_user_employee_links', 'employee-user-link-v1', NOW());
