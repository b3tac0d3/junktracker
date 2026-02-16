-- JunkTracker beta launch prep
-- Run this once on local, then on live before deploy.

-- 1) Ensure employee_time_entries exists with nullable job_id for Non-Job Time entries
CREATE TABLE IF NOT EXISTS employee_time_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    work_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    minutes_worked INT UNSIGNED NULL,
    pay_rate DECIMAL(12,2) UNSIGNED NULL,
    total_paid DECIMAL(12,2) UNSIGNED NULL,
    note TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_time_employee_date (employee_id, work_date),
    KEY idx_time_job_date (job_id, work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE employee_time_entries
    MODIFY COLUMN job_id BIGINT UNSIGNED NULL;

-- Normalize legacy non-job placeholders and any orphaned references
UPDATE employee_time_entries
SET job_id = NULL
WHERE job_id = 0;

UPDATE employee_time_entries e
LEFT JOIN jobs j ON j.id = e.job_id
SET e.job_id = NULL
WHERE e.job_id IS NOT NULL
  AND j.id IS NULL;

-- 2) Crew assignment table for punch-in flow on jobs
CREATE TABLE IF NOT EXISTS job_crew (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_job_crew_member (job_id, employee_id),
    KEY idx_job_crew_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
