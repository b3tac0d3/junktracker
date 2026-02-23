-- JunkTracker Beta 2.0: Dev bug statuses + notes

CREATE TABLE IF NOT EXISTS dev_bug_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bug_id BIGINT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dev_bug_notes_bug (bug_id),
    KEY idx_dev_bug_notes_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dev_bugs
    MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'unresearched';

UPDATE dev_bugs
SET status = CASE
    WHEN status = 'new' THEN 'unresearched'
    WHEN status = 'in_progress' THEN 'working'
    WHEN status IN ('fixed', 'wont_fix') THEN 'fixed_closed'
    ELSE status
END
WHERE status IN ('new', 'in_progress', 'fixed', 'wont_fix');
