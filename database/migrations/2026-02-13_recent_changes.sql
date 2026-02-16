-- JunkTracker recent schema updates
-- Safe to run multiple times on MySQL 8+

SET @schema := DATABASE();

-- 1) Jobs: owner/contact fields
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'jobs'
          AND BINARY COLUMN_NAME = BINARY 'job_owner_type'
    ),
    'SELECT 1',
    'ALTER TABLE jobs ADD COLUMN job_owner_type VARCHAR(20) NULL AFTER estate_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'jobs'
          AND BINARY COLUMN_NAME = BINARY 'job_owner_id'
    ),
    'SELECT 1',
    'ALTER TABLE jobs ADD COLUMN job_owner_id BIGINT UNSIGNED NULL AFTER job_owner_type'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'jobs'
          AND BINARY COLUMN_NAME = BINARY 'contact_client_id'
    ),
    'SELECT 1',
    'ALTER TABLE jobs ADD COLUMN contact_client_id BIGINT UNSIGNED NULL AFTER job_owner_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill existing rows
UPDATE jobs
SET job_owner_type = CASE
        WHEN estate_id IS NOT NULL THEN 'estate'
        ELSE 'client'
    END
WHERE job_owner_type IS NULL
   OR job_owner_type = '';

UPDATE jobs
SET job_owner_id = CASE
        WHEN job_owner_type = 'estate' THEN estate_id
        WHEN job_owner_type = 'company' THEN job_owner_id
        ELSE client_id
    END
WHERE job_owner_id IS NULL;

UPDATE jobs
SET contact_client_id = client_id
WHERE contact_client_id IS NULL;

UPDATE jobs
SET job_owner_type = 'client'
WHERE job_owner_type NOT IN ('client', 'estate', 'company');

UPDATE jobs j
LEFT JOIN clients c ON c.id = j.contact_client_id
SET j.contact_client_id = NULL
WHERE j.contact_client_id IS NOT NULL
  AND c.id IS NULL;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'jobs'
          AND BINARY INDEX_NAME = BINARY 'idx_jobs_owner'
    ),
    'SELECT 1',
    'CREATE INDEX idx_jobs_owner ON jobs (job_owner_type, job_owner_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'jobs'
          AND BINARY INDEX_NAME = BINARY 'idx_jobs_contact_client'
    ),
    'SELECT 1',
    'CREATE INDEX idx_jobs_contact_client ON jobs (contact_client_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
          AND BINARY CONSTRAINT_NAME = BINARY 'fk_jobs_contact_client'
    ),
    'SELECT 1',
    'ALTER TABLE jobs
     ADD CONSTRAINT fk_jobs_contact_client
     FOREIGN KEY (contact_client_id)
     REFERENCES clients(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Expense categories master table
CREATE TABLE IF NOT EXISTS expense_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    note TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_expense_categories_name (name),
    KEY idx_expense_categories_deleted_at (deleted_at),
    KEY idx_expense_categories_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed categories from existing expense rows (legacy free-text data)
INSERT IGNORE INTO expense_categories (name, created_at, updated_at)
SELECT DISTINCT TRIM(e.category) AS name, NOW(), NOW()
FROM expenses e
WHERE e.category IS NOT NULL
  AND TRIM(e.category) <> '';

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'expenses'
          AND BINARY COLUMN_NAME = BINARY 'expense_category_id'
    ),
    'SELECT 1',
    'ALTER TABLE expenses ADD COLUMN expense_category_id BIGINT UNSIGNED NULL AFTER disposal_location_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill the new foreign key from legacy category text
UPDATE expenses e
JOIN expense_categories ec
  ON BINARY ec.name = BINARY TRIM(e.category)
SET e.expense_category_id = ec.id
WHERE e.expense_category_id IS NULL
  AND e.category IS NOT NULL
  AND TRIM(e.category) <> '';

UPDATE expenses e
LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
SET e.expense_category_id = NULL
WHERE e.expense_category_id IS NOT NULL
  AND ec.id IS NULL;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'expenses'
          AND BINARY INDEX_NAME = BINARY 'idx_expenses_category_id'
    ),
    'SELECT 1',
    'CREATE INDEX idx_expenses_category_id ON expenses (expense_category_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema
          AND BINARY CONSTRAINT_NAME = BINARY 'fk_expenses_category'
    ),
    'SELECT 1',
    'ALTER TABLE expenses
     ADD CONSTRAINT fk_expenses_category
     FOREIGN KEY (expense_category_id)
     REFERENCES expense_categories(id)
     ON DELETE SET NULL
     ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
