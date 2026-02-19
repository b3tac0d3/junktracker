-- JunkTracker beta 1.1.3: security + ops foundation
-- Run in phpMyAdmin after prior beta migrations.

SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(190) NOT NULL,
    checksum VARCHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_schema_migrations_key (migration_key),
    KEY idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NULL,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'failed',
    reason VARCHAR(80) NULL,
    user_agent VARCHAR(512) NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auth_login_attempts_email_date (email, attempted_at),
    KEY idx_auth_login_attempts_ip_date (ip_address, attempted_at),
    KEY idx_auth_login_attempts_user_date (user_id, attempted_at),
    KEY idx_auth_login_attempts_status_date (status, attempted_at),
    CONSTRAINT fk_auth_login_attempts_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'failed_login_count'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN failed_login_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_2fa_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'last_failed_login_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN last_failed_login_at DATETIME NULL AFTER failed_login_count'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'last_failed_login_ip'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN last_failed_login_ip VARCHAR(45) NULL AFTER last_failed_login_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'locked_until'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN locked_until DATETIME NULL AFTER last_failed_login_ip'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY COLUMN_NAME = BINARY 'last_login_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER locked_until'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY INDEX_NAME = BINARY 'idx_users_locked_until'
    ),
    'SELECT 1',
    'CREATE INDEX idx_users_locked_until ON users (locked_until)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY @schema
          AND BINARY TABLE_NAME = BINARY 'users'
          AND BINARY INDEX_NAME = BINARY 'idx_users_last_failed_login_at'
    ),
    'SELECT 1',
    'CREATE INDEX idx_users_last_failed_login_at ON users (last_failed_login_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at) VALUES
('2026-02-20_beta_1.1.3_security_ops', 'beta-1.1.3', NOW());
