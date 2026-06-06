-- Sub-contractors and job sub-out assignments
-- Safe to run multiple times.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS subcontractors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(90) NOT NULL,
    last_name VARCHAR(90) NULL,
    company VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    notes TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_subcontractors_business (business_id, deleted_at),
    KEY idx_subcontractors_status (business_id, status, deleted_at),
    KEY idx_subcontractors_name (business_id, first_name, last_name, deleted_at),
    CONSTRAINT fk_subcontractors_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_subcontractor_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    subcontractor_id BIGINT UNSIGNED NOT NULL,
    status ENUM('assigned','in_progress','completed','cancelled') NOT NULL DEFAULT 'assigned',
    client_amount DECIMAL(10,2) NULL,
    sub_amount DECIMAL(10,2) NULL,
    our_cut DECIMAL(10,2) NULL,
    notes TEXT NULL,
    assigned_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_job_sub_assignment (business_id, job_id),
    KEY idx_job_sub_assignments_job (business_id, job_id),
    KEY idx_job_sub_assignments_sub (business_id, subcontractor_id),
    KEY idx_job_sub_assignments_status (business_id, status, deleted_at),
    KEY idx_job_sub_assignments_deleted (business_id, deleted_at),
    CONSTRAINT fk_job_sub_assignments_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_job_sub_assignments_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON UPDATE CASCADE,
    CONSTRAINT fk_job_sub_assignments_sub FOREIGN KEY (subcontractor_id) REFERENCES subcontractors(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
