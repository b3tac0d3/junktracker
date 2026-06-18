-- Mobile API bearer tokens (opaque, revocable)
CREATE TABLE IF NOT EXISTS api_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    business_id INT UNSIGNED NULL DEFAULT NULL,
    token_hash CHAR(64) NOT NULL,
    token_type ENUM('access', 'refresh') NOT NULL DEFAULT 'access',
    device_name VARCHAR(255) NULL DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL DEFAULT NULL,
    revoked_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_tokens_hash (token_hash),
    KEY idx_api_tokens_user (user_id),
    KEY idx_api_tokens_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
