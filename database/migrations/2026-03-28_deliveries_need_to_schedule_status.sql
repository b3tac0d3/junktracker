-- Align legacy rows: unscheduled deliveries use status need_to_schedule
-- Date: 2026-03-28

SET NAMES utf8mb4;
SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_table := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'client_deliveries'
);

SET @sql := IF(
    @has_table = 0,
    'SELECT 1',
    'UPDATE client_deliveries
     SET status = ''need_to_schedule''
     WHERE scheduled_at IS NULL
       AND deleted_at IS NULL
       AND LOWER(TRIM(status)) = ''scheduled'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-03-28_deliveries_need_to_schedule_status', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
