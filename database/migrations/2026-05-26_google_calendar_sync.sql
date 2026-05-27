-- Google Calendar one-way sync (OAuth + event links)
-- Date: 2026-05-26

START TRANSACTION;

CREATE TABLE IF NOT EXISTS user_google_calendar_connections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    google_account_email VARCHAR(190) NULL,
    calendar_id VARCHAR(190) NOT NULL DEFAULT 'primary',
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    token_expires_at DATETIME NULL,
    connected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_google_calendar_user (user_id),
    CONSTRAINT fk_ugcc_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS google_calendar_event_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    google_calendar_id VARCHAR(190) NOT NULL,
    google_event_id VARCHAR(190) NOT NULL,
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_google_calendar_event_link (user_id, source_type, source_id),
    KEY idx_google_calendar_event_links_google (google_calendar_id, google_event_id),
    CONSTRAINT fk_gcel_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
