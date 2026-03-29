-- Deliveries: address line 2, optional scheduled time (need to schedule), nullable scheduled_at
-- Date: 2026-03-27

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

SET @has_line2 := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'client_deliveries' AND COLUMN_NAME = 'address_line2'
);
SET @sql := IF(
    @has_table = 0 OR @has_line2 > 0,
    'SELECT 1',
    'ALTER TABLE client_deliveries ADD COLUMN address_line2 VARCHAR(190) NULL AFTER address_line1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sched_nullable := (
    SELECT IS_NULLABLE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'client_deliveries' AND COLUMN_NAME = 'scheduled_at'
    LIMIT 1
);
SET @sql := IF(
    @has_table = 0 OR IFNULL(@sched_nullable, '') = 'YES',
    'SELECT 1',
    'ALTER TABLE client_deliveries MODIFY COLUMN scheduled_at DATETIME NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-03-27_deliveries_address_need_schedule', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
