-- Client quick-add follow-up reminders (dashboard queue)
-- Date: 2026-06-14

START TRANSACTION;

CREATE TABLE IF NOT EXISTS client_follow_up_reminders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    reminder_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    owner_user_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    completed_by BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_client_follow_up_business_status (business_id, status, deleted_at),
    KEY idx_client_follow_up_owner (business_id, owner_user_id, status, deleted_at),
    KEY idx_client_follow_up_client (business_id, client_id, deleted_at),
    CONSTRAINT fk_cfu_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_cfu_client FOREIGN KEY (client_id) REFERENCES clients(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
