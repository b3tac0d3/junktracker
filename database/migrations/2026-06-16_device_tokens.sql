-- Push notification device tokens (FCM)
CREATE TABLE IF NOT EXISTS device_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    business_id INT UNSIGNED NULL DEFAULT NULL,
    platform ENUM('ios', 'android', 'web') NOT NULL DEFAULT 'android',
    token VARCHAR(512) NOT NULL,
    device_name VARCHAR(255) NULL DEFAULT NULL,
    last_seen_at DATETIME NULL DEFAULT NULL,
    revoked_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_device_tokens_token (token(191)),
    KEY idx_device_tokens_user (user_id),
    KEY idx_device_tokens_business (business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
