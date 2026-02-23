-- JunkTracker Beta 2.0
-- Site Admin Support Queue (site_admin_tickets + notes)
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS site_admin_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NULL,
    submitted_by_user_id BIGINT UNSIGNED NOT NULL,
    submitted_by_email VARCHAR(255) NOT NULL,
    category VARCHAR(32) NOT NULL DEFAULT 'question',
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unopened',
    priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
    assigned_to_user_id BIGINT UNSIGNED NULL,
    opened_at DATETIME NULL,
    closed_at DATETIME NULL,
    last_customer_note_at DATETIME NULL,
    last_admin_note_at DATETIME NULL,
    converted_bug_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_site_admin_tickets_status (status),
    KEY idx_site_admin_tickets_priority_status (priority, status),
    KEY idx_site_admin_tickets_assigned (assigned_to_user_id),
    KEY idx_site_admin_tickets_submitter (submitted_by_user_id),
    KEY idx_site_admin_tickets_business (business_id),
    KEY idx_site_admin_tickets_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_admin_ticket_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'customer',
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_site_admin_ticket_notes_ticket (ticket_id),
    KEY idx_site_admin_ticket_notes_visibility (visibility),
    KEY idx_site_admin_ticket_notes_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
