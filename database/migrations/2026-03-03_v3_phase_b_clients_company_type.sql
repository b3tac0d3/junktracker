-- Clients: add company client_type option for business-only records.
SET @schema := DATABASE();

SET @has_clients := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
);

SET @has_client_type := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'client_type'
);

SET @has_company_type := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'client_type'
      AND COLUMN_TYPE LIKE "%'company'%"
);

SET @sql := IF(
    @has_clients = 1 AND @has_client_type = 1 AND @has_company_type = 0,
    "ALTER TABLE clients MODIFY COLUMN client_type ENUM('realtor','client','company','other') NOT NULL DEFAULT 'client'",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

