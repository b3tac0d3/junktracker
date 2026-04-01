-- Per-client referral: who referred this client (self-FK on clients)
-- Date: 2026-03-31

SET NAMES utf8mb4;
SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_clients := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'clients'
);

SET @has_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'referred_by_client_id'
);

SET @sql := IF(
    @has_clients = 0 OR @has_col > 0,
    'SELECT 1',
    'ALTER TABLE clients
        ADD COLUMN referred_by_client_id BIGINT UNSIGNED NULL AFTER business_id,
        ADD KEY idx_clients_referred_by (business_id, referred_by_client_id),
        ADD CONSTRAINT fk_clients_referred_by FOREIGN KEY (referred_by_client_id) REFERENCES clients(id) ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-03-31_client_referrals', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
