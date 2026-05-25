-- Purchase quotes pipeline (early-stage buy deals, separate from committed purchases)
-- Date: 2026-05-25

START TRANSACTION;

CREATE TABLE IF NOT EXISTS purchase_quotes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'new',
    contact_date DATE NULL,
    next_follow_up_at DATETIME NULL,
    notes TEXT NULL,
    lost_reason VARCHAR(190) NULL,
    converted_purchase_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_purchase_quotes_business_status (business_id, status, deleted_at),
    KEY idx_purchase_quotes_follow_up (business_id, next_follow_up_at, deleted_at),
    KEY idx_purchase_quotes_client (business_id, client_id, deleted_at),
    CONSTRAINT fk_pq_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_pq_client FOREIGN KEY (client_id) REFERENCES clients(id) ON UPDATE CASCADE,
    CONSTRAINT fk_pq_converted_purchase FOREIGN KEY (converted_purchase_id) REFERENCES purchases(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_quote_offers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    purchase_quote_id BIGINT UNSIGNED NOT NULL,
    offer_type VARCHAR(40) NOT NULL DEFAULT 'our_offer',
    amount DECIMAL(12,2) NULL,
    note TEXT NULL,
    offered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pqo_quote (business_id, purchase_quote_id, deleted_at),
    CONSTRAINT fk_pqo_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_pqo_quote FOREIGN KEY (purchase_quote_id) REFERENCES purchase_quotes(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_quote_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    purchase_quote_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    contacted_at DATETIME NOT NULL,
    contact_type VARCHAR(50) NULL,
    note TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pqc_quote (business_id, purchase_quote_id, deleted_at),
    CONSTRAINT fk_pqc_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_pqc_quote FOREIGN KEY (purchase_quote_id) REFERENCES purchase_quotes(id) ON UPDATE CASCADE,
    CONSTRAINT fk_pqc_client FOREIGN KEY (client_id) REFERENCES clients(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
