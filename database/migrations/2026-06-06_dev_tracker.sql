-- Dev tracker: bugs, updates, and progress notes (site admin)
-- Date: 2026-06-06

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dev_tracker_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_type VARCHAR(32) NOT NULL DEFAULT 'bug',
    title VARCHAR(200) NOT NULL,
    notes TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'backlog',
    priority VARCHAR(16) NOT NULL DEFAULT 'normal',
    area VARCHAR(80) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_dev_tracker_status (status, deleted_at),
    KEY idx_dev_tracker_type (item_type, deleted_at),
    KEY idx_dev_tracker_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
