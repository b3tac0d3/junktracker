-- Dev tracker: company bug submissions, review workflow, and activity log
-- Date: 2026-06-14

SET NAMES utf8mb4;

ALTER TABLE dev_tracker_items
    ADD COLUMN business_id BIGINT UNSIGNED NULL AFTER area,
    ADD COLUMN review_status VARCHAR(32) NULL AFTER business_id,
    ADD COLUMN submitted_by BIGINT UNSIGNED NULL AFTER review_status,
    ADD KEY idx_dev_tracker_review (review_status, deleted_at),
    ADD KEY idx_dev_tracker_business (business_id, deleted_at);

CREATE TABLE IF NOT EXISTS dev_tracker_log_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dev_tracker_item_id BIGINT UNSIGNED NOT NULL,
    entry_type VARCHAR(32) NOT NULL DEFAULT 'comment',
    body TEXT NULL,
    status_from VARCHAR(32) NULL,
    status_to VARCHAR(32) NULL,
    screenshot_path VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dev_tracker_log_item (dev_tracker_item_id, created_at),
    KEY idx_dev_tracker_log_type (entry_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
