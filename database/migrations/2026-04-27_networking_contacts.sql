-- Networking contacts module
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS networking_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    company VARCHAR(190) NULL,
    contact_type VARCHAR(80) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    deleted_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_networking_contacts_business (business_id, deleted_at),
    KEY idx_networking_contacts_type (business_id, contact_type, deleted_at),
    KEY idx_networking_contacts_name (business_id, name, deleted_at),
    CONSTRAINT fk_networking_contacts_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE
);
