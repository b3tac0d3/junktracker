-- JunkTracker 2.1.2 Performance Bundle
-- Safe to run multiple times.

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

-- jobs: core status filters and dashboard cards.
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES t
        WHERE t.TABLE_SCHEMA = @schema
          AND BINARY t.TABLE_NAME = BINARY 'jobs'
    )
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS c
        WHERE c.TABLE_SCHEMA = @schema
          AND BINARY c.TABLE_NAME = BINARY 'jobs'
          AND BINARY c.COLUMN_NAME IN ('business_id', 'deleted_at', 'active', 'job_status', 'scheduled_date')
    ) = 5
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS s
        WHERE s.TABLE_SCHEMA = @schema
          AND BINARY s.TABLE_NAME = BINARY 'jobs'
          AND BINARY s.INDEX_NAME = BINARY 'idx_jobs_business_status_sched'
    ),
    'ALTER TABLE jobs ADD INDEX idx_jobs_business_status_sched (business_id, deleted_at, active, job_status, scheduled_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES t
        WHERE t.TABLE_SCHEMA = @schema
          AND BINARY t.TABLE_NAME = BINARY 'jobs'
    )
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS c
        WHERE c.TABLE_SCHEMA = @schema
          AND BINARY c.TABLE_NAME = BINARY 'jobs'
          AND BINARY c.COLUMN_NAME IN ('business_id', 'deleted_at', 'active', 'job_status', 'updated_at')
    ) = 5
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS s
        WHERE s.TABLE_SCHEMA = @schema
          AND BINARY s.TABLE_NAME = BINARY 'jobs'
          AND BINARY s.INDEX_NAME = BINARY 'idx_jobs_business_status_updated'
    ),
    'ALTER TABLE jobs ADD INDEX idx_jobs_business_status_updated (business_id, deleted_at, active, job_status, updated_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- prospects: follow-up queue.
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES t
        WHERE t.TABLE_SCHEMA = @schema
          AND BINARY t.TABLE_NAME = BINARY 'prospects'
    )
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS c
        WHERE c.TABLE_SCHEMA = @schema
          AND BINARY c.TABLE_NAME = BINARY 'prospects'
          AND BINARY c.COLUMN_NAME IN ('business_id', 'deleted_at', 'active', 'status', 'follow_up_on')
    ) = 5
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS s
        WHERE s.TABLE_SCHEMA = @schema
          AND BINARY s.TABLE_NAME = BINARY 'prospects'
          AND BINARY s.INDEX_NAME = BINARY 'idx_prospects_business_status_followup'
    ),
    'ALTER TABLE prospects ADD INDEX idx_prospects_business_status_followup (business_id, deleted_at, active, status, follow_up_on)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- todos: task queues and dashboard outstanding tasks.
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES t
        WHERE t.TABLE_SCHEMA = @schema
          AND BINARY t.TABLE_NAME = BINARY 'todos'
    )
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS c
        WHERE c.TABLE_SCHEMA = @schema
          AND BINARY c.TABLE_NAME = BINARY 'todos'
          AND BINARY c.COLUMN_NAME IN ('business_id', 'deleted_at', 'status', 'due_at')
    ) = 4
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS s
        WHERE s.TABLE_SCHEMA = @schema
          AND BINARY s.TABLE_NAME = BINARY 'todos'
          AND BINARY s.INDEX_NAME = BINARY 'idx_todos_business_status_due'
    ),
    'ALTER TABLE todos ADD INDEX idx_todos_business_status_due (business_id, deleted_at, status, due_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES t
        WHERE t.TABLE_SCHEMA = @schema
          AND BINARY t.TABLE_NAME = BINARY 'todos'
    )
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS c
        WHERE c.TABLE_SCHEMA = @schema
          AND BINARY c.TABLE_NAME = BINARY 'todos'
          AND BINARY c.COLUMN_NAME IN ('business_id', 'link_type', 'link_id', 'deleted_at')
    ) = 4
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS s
        WHERE s.TABLE_SCHEMA = @schema
          AND BINARY s.TABLE_NAME = BINARY 'todos'
          AND BINARY s.INDEX_NAME = BINARY 'idx_todos_business_link'
    ),
    'ALTER TABLE todos ADD INDEX idx_todos_business_link (business_id, link_type, link_id, deleted_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- time tracking open/punched-in lookups.
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES t
        WHERE t.TABLE_SCHEMA = @schema
          AND BINARY t.TABLE_NAME = BINARY 'employee_time_entries'
    )
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS c
        WHERE c.TABLE_SCHEMA = @schema
          AND BINARY c.TABLE_NAME = BINARY 'employee_time_entries'
          AND BINARY c.COLUMN_NAME IN ('business_id', 'deleted_at', 'active', 'end_time', 'employee_id', 'work_date')
    ) = 6
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS s
        WHERE s.TABLE_SCHEMA = @schema
          AND BINARY s.TABLE_NAME = BINARY 'employee_time_entries'
          AND BINARY s.INDEX_NAME = BINARY 'idx_time_business_open_lookup'
    ),
    'ALTER TABLE employee_time_entries ADD INDEX idx_time_business_open_lookup (business_id, deleted_at, active, end_time, employee_id, work_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sales and expenses: dashboard rollups.
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES t
        WHERE t.TABLE_SCHEMA = @schema
          AND BINARY t.TABLE_NAME = BINARY 'sales'
    )
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS c
        WHERE c.TABLE_SCHEMA = @schema
          AND BINARY c.TABLE_NAME = BINARY 'sales'
          AND BINARY c.COLUMN_NAME IN ('business_id', 'deleted_at', 'active', 'end_date', 'start_date', 'created_at')
    ) = 6
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS s
        WHERE s.TABLE_SCHEMA = @schema
          AND BINARY s.TABLE_NAME = BINARY 'sales'
          AND BINARY s.INDEX_NAME = BINARY 'idx_sales_business_active_dates'
    ),
    'ALTER TABLE sales ADD INDEX idx_sales_business_active_dates (business_id, deleted_at, active, end_date, start_date, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES t
        WHERE t.TABLE_SCHEMA = @schema
          AND BINARY t.TABLE_NAME = BINARY 'expenses'
    )
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS c
        WHERE c.TABLE_SCHEMA = @schema
          AND BINARY c.TABLE_NAME = BINARY 'expenses'
          AND BINARY c.COLUMN_NAME IN ('business_id', 'deleted_at', 'is_active', 'expense_date', 'created_at')
    ) = 5
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS s
        WHERE s.TABLE_SCHEMA = @schema
          AND BINARY s.TABLE_NAME = BINARY 'expenses'
          AND BINARY s.INDEX_NAME = BINARY 'idx_expenses_business_active_date'
    ),
    'ALTER TABLE expenses ADD INDEX idx_expenses_business_active_date (business_id, deleted_at, is_active, expense_date, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- notifications/read state.
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES t
        WHERE t.TABLE_SCHEMA = @schema
          AND BINARY t.TABLE_NAME = BINARY 'user_notification_states'
    )
    AND (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS c
        WHERE c.TABLE_SCHEMA = @schema
          AND BINARY c.TABLE_NAME = BINARY 'user_notification_states'
          AND BINARY c.COLUMN_NAME IN ('business_id', 'user_id', 'is_read', 'dismissed_at')
    ) = 4
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS s
        WHERE s.TABLE_SCHEMA = @schema
          AND BINARY s.TABLE_NAME = BINARY 'user_notification_states'
          AND BINARY s.INDEX_NAME = BINARY 'idx_uns_business_user_read'
    ),
    'ALTER TABLE user_notification_states ADD INDEX idx_uns_business_user_read (business_id, user_id, is_read, dismissed_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at)
VALUES ('2026-02-23_beta_2.1.2_performance', 'performance-v2-1-2', NOW());
