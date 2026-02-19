-- JunkTracker beta 1.1: login record tracking
-- Run in phpMyAdmin on local/live after beta_1.0 migrations.

CREATE TABLE IF NOT EXISTS user_login_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    login_method VARCHAR(30) NOT NULL DEFAULT 'password',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(512) DEFAULT NULL,
    browser_name VARCHAR(80) DEFAULT NULL,
    browser_version VARCHAR(40) DEFAULT NULL,
    os_name VARCHAR(80) DEFAULT NULL,
    device_type VARCHAR(30) DEFAULT NULL,
    logged_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_login_records_user_date (user_id, logged_in_at),
    KEY idx_user_login_records_method (login_method),
    CONSTRAINT fk_user_login_records_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
