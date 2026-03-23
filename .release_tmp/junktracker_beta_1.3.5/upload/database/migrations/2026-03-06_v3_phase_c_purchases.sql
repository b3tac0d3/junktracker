-- v3 Phase C - Purchases
-- Date: 2026-03-06

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS purchases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    status ENUM('prospect','pending','active','complete','cancelled') NOT NULL DEFAULT 'prospect',
    contact_date DATE NULL,
    purchase_date DATE NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_purchases_business (business_id),
    KEY idx_purchases_business_status (business_id, status),
    KEY idx_purchases_business_client (business_id, client_id),
    KEY idx_purchases_business_contact (business_id, contact_date),
    KEY idx_purchases_business_purchase (business_id, purchase_date),
    KEY idx_purchases_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_purchases_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_purchases_client FOREIGN KEY (client_id) REFERENCES clients(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
