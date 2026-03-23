-- Task completion tracking (who marked done + when).

SET @has_completed_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND COLUMN_NAME = 'completed_at'
);
SET @sql := IF(
    @has_completed_at > 0,
    'SELECT 1',
    'ALTER TABLE tasks ADD COLUMN completed_at DATETIME NULL AFTER due_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_completed_by := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND COLUMN_NAME = 'completed_by'
);
SET @sql := IF(
    @has_completed_by > 0,
    'SELECT 1',
    'ALTER TABLE tasks ADD COLUMN completed_by BIGINT UNSIGNED NULL AFTER completed_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx_completed_at := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND INDEX_NAME = 'idx_tasks_completed_at'
);
SET @sql := IF(
    @has_idx_completed_at > 0,
    'SELECT 1',
    'ALTER TABLE tasks ADD KEY idx_tasks_completed_at (business_id, completed_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx_completed_by := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND INDEX_NAME = 'idx_tasks_completed_by'
);
SET @sql := IF(
    @has_idx_completed_by > 0,
    'SELECT 1',
    'ALTER TABLE tasks ADD KEY idx_tasks_completed_by (business_id, completed_by)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk_completed_by := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_tasks_completed_by_user'
);
SET @sql := IF(
    @has_fk_completed_by > 0,
    'SELECT 1',
    'ALTER TABLE tasks
     ADD CONSTRAINT fk_tasks_completed_by_user
     FOREIGN KEY (completed_by)
     REFERENCES users(id)
     ON UPDATE CASCADE
     ON DELETE SET NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

