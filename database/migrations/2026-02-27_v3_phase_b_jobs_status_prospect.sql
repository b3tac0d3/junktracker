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

