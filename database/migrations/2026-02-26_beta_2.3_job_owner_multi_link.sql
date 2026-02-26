-- Beta 2.3: allow jobs to link client/estate/company at the same time.
SET @schema := DATABASE();

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'jobs'
          AND COLUMN_NAME = 'owner_client_id'
    ),
    'SELECT 1',
    'ALTER TABLE jobs ADD COLUMN owner_client_id BIGINT UNSIGNED NULL AFTER contact_client_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'jobs'
          AND COLUMN_NAME = 'owner_company_id'
    ),
    'SELECT 1',
    'ALTER TABLE jobs ADD COLUMN owner_company_id BIGINT UNSIGNED NULL AFTER owner_client_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'jobs'
          AND INDEX_NAME = 'idx_jobs_owner_client_id'
    ),
    'SELECT 1',
    'CREATE INDEX idx_jobs_owner_client_id ON jobs (owner_client_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'jobs'
          AND INDEX_NAME = 'idx_jobs_owner_company_id'
    ),
    'SELECT 1',
    'CREATE INDEX idx_jobs_owner_company_id ON jobs (owner_company_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
