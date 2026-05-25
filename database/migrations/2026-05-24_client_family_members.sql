-- Client family members (follow-up contacts who may not be the primary client)
-- Date: 2026-05-24

START TRANSACTION;

CREATE TABLE IF NOT EXISTS client_family_members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    linked_client_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(90) NULL,
    last_name VARCHAR(90) NULL,
    relationship VARCHAR(40) NOT NULL,
    phone VARCHAR(40) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_client_family_members_client (business_id, client_id, deleted_at),
    KEY idx_client_family_members_linked (business_id, linked_client_id),
    CONSTRAINT fk_cfm_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_cfm_client FOREIGN KEY (client_id) REFERENCES clients(id) ON UPDATE CASCADE,
    CONSTRAINT fk_cfm_linked_client FOREIGN KEY (linked_client_id) REFERENCES clients(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
