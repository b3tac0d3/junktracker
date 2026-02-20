-- JunkTracker Beta 1.3.1 Live Bundle
-- Includes idempotent schema updates needed for 1.3.1 deployments:
-- 1) Employee <-> user linking support
-- 2) Job scheduling windows table
-- No additional schema changes are required for policy/footer/UI updates.

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
-- 1) Employee <-> user linking
-- =========================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE BINARY TABLE_SCHEMA = BINARY @schema
      AND BINARY TABLE_NAME = BINARY 'employees'
      AND BINARY COLUMN_NAME = BINARY 'user_id'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE employees ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER email',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE BINARY TABLE_SCHEMA = BINARY @schema
      AND BINARY TABLE_NAME = BINARY 'employees'
      AND BINARY INDEX_NAME = BINARY 'idx_employees_user_id'
);
SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE employees ADD INDEX idx_employees_user_id (user_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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

SET @uniq_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE BINARY TABLE_SCHEMA = BINARY @schema
      AND BINARY TABLE_NAME = BINARY 'employees'
      AND BINARY INDEX_NAME = BINARY 'uniq_employees_user_id'
);
SET @sql := IF(
    @uniq_exists = 0,
    'ALTER TABLE employees ADD UNIQUE KEY uniq_employees_user_id (user_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_employees_user'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE employees
     ADD CONSTRAINT fk_employees_user
     FOREIGN KEY (user_id)
     REFERENCES users(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 2) Schedule windows
-- =========================================================
CREATE TABLE IF NOT EXISTS job_schedule_windows (
    job_id BIGINT UNSIGNED NOT NULL,
    scheduled_start_at DATETIME NOT NULL,
    scheduled_end_at DATETIME NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (job_id),
    KEY idx_job_schedule_windows_start (scheduled_start_at),
    KEY idx_job_schedule_windows_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_schedule_windows_job'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE job_schedule_windows
     ADD CONSTRAINT fk_job_schedule_windows_job
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
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_schedule_windows_updated_by'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE job_schedule_windows
     ADD CONSTRAINT fk_job_schedule_windows_updated_by
     FOREIGN KEY (updated_by)
     REFERENCES users(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at) VALUES
('2026-02-20_user_employee_links', 'employee-user-link-v1', NOW()),
('2026-02-19_beta_1.3.1_schedule_windows', 'beta-1.3.1-schedule-windows', NOW()),
('2026-02-20_beta_1.3.1_live_bundle', 'beta-1.3.1-live-bundle-v1', NOW());
