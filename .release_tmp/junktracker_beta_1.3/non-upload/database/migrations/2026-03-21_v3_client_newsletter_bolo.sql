-- JunkTracker v3
-- Client newsletter subscription + BOLO (buyer) profile with line items
-- Date: 2026-03-21

SET NAMES utf8mb4;
SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- clients.newsletter_subscribed
SET @has_newsletter_sub := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'newsletter_subscribed'
);
SET @sql := IF(
    @has_newsletter_sub > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN newsletter_subscribed TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- clients.newsletter_unsubscribe_token (for future email unsubscribe links)
SET @has_newsletter_token := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'newsletter_unsubscribe_token'
);
SET @sql := IF(
    @has_newsletter_token > 0,
    'SELECT 1',
    'ALTER TABLE clients ADD COLUMN newsletter_unsubscribe_token CHAR(64) NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BOLO profile (one per client per business)
CREATE TABLE IF NOT EXISTS client_bolo_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_client_bolo_profiles_client (client_id),
    KEY idx_client_bolo_profiles_business (business_id),
    CONSTRAINT fk_client_bolo_profiles_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_client_bolo_profiles_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Line items for each BOLO profile
CREATE TABLE IF NOT EXISTS client_bolo_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bolo_profile_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    item_text VARCHAR(500) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_client_bolo_lines_profile_sort (bolo_profile_id, sort_order),
    CONSTRAINT fk_client_bolo_lines_profile
        FOREIGN KEY (bolo_profile_id) REFERENCES client_bolo_profiles(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-03-21_v3_client_newsletter_bolo', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
