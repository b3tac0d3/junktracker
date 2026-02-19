-- JunkTracker: Dev tools bug tracker

CREATE TABLE IF NOT EXISTS dev_bugs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    details TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'new',
    severity TINYINT UNSIGNED NOT NULL DEFAULT 3,
    environment VARCHAR(16) NOT NULL DEFAULT 'local',
    module_key VARCHAR(80) NULL,
    route_path VARCHAR(255) NULL,
    reported_by BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    fixed_at DATETIME NULL,
    fixed_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_dev_bugs_status_updated (status, updated_at),
    KEY idx_dev_bugs_severity_status (severity, status),
    KEY idx_dev_bugs_environment_status (environment, status),
    KEY idx_dev_bugs_assigned (assigned_user_id),
    KEY idx_dev_bugs_reported (reported_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

