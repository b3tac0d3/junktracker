-- Client deliveries (scheduled drop-offs / deliveries per client)
-- Date: 2026-03-28

START TRANSACTION;

SET @schema := DATABASE();

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'client_deliveries') > 0,
    'SELECT 1',
    'CREATE TABLE client_deliveries (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        business_id BIGINT UNSIGNED NOT NULL,
        client_id BIGINT UNSIGNED NOT NULL,
        scheduled_at DATETIME NOT NULL,
        end_at DATETIME NULL,
        address_line1 VARCHAR(190) NULL,
        city VARCHAR(120) NULL,
        state VARCHAR(60) NULL,
        postal_code VARCHAR(30) NULL,
        notes TEXT NULL,
        status VARCHAR(32) NOT NULL DEFAULT ''scheduled'',
        created_by BIGINT UNSIGNED NULL,
        updated_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_cd_business_scheduled (business_id, scheduled_at),
        KEY idx_cd_business_status (business_id, status),
        KEY idx_cd_client (client_id),
        CONSTRAINT fk_cd_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
        CONSTRAINT fk_cd_client FOREIGN KEY (client_id) REFERENCES clients(id) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

COMMIT;
