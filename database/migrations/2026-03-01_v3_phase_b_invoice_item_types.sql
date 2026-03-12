-- v3 Phase B: Invoice item types
-- Date: 2026-03-01

START TRANSACTION;

CREATE TABLE IF NOT EXISTS invoice_item_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(90) NOT NULL,
    default_unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    default_taxable TINYINT(1) NOT NULL DEFAULT 1,
    default_note VARCHAR(255) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_iit_business (business_id),
    KEY idx_iit_business_active (business_id, is_active),
    KEY idx_iit_business_sort (business_id, sort_order),
    KEY idx_iit_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_iit_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
