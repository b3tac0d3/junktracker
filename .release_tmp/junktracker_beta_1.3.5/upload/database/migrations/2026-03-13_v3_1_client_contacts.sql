CREATE TABLE IF NOT EXISTS client_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    contacted_at DATETIME NOT NULL,
    contact_type VARCHAR(50) NULL,
    note TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_client_contacts_business_client (business_id, client_id),
    KEY idx_client_contacts_client_date (client_id, contacted_at),
    KEY idx_client_contacts_deleted_at (deleted_at),
    CONSTRAINT fk_client_contacts_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_client_contacts_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_client_contacts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

