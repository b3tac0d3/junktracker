-- JunkTracker Beta 1.3.1 scheduling window support
-- Adds persisted schedule start/end times for calendar drag/drop scheduling.

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
('2026-02-19_beta_1.3.1_schedule_windows', 'beta-1.3.1-schedule-windows', NOW());
