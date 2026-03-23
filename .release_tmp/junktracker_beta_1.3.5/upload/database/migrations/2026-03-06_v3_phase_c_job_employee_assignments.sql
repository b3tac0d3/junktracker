-- v3 Phase C - Job Employee Assignments
-- Date: 2026-03-06

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS job_employee_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_job_employee (business_id, job_id, employee_id),
    KEY idx_job_employee_assignments_job (business_id, job_id),
    KEY idx_job_employee_assignments_employee (business_id, employee_id),
    KEY idx_job_employee_assignments_deleted (business_id, deleted_at),
    CONSTRAINT fk_job_employee_assignments_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_job_employee_assignments_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON UPDATE CASCADE,
    CONSTRAINT fk_job_employee_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
