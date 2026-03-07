-- v3 Phase B - Job Expenses
-- Date: 2026-03-03

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NULL,
    expense_date DATE NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(80) NULL,
    payment_method VARCHAR(80) NULL,
    note VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_expenses_business (business_id),
    KEY idx_expenses_business_job (business_id, job_id),
    KEY idx_expenses_business_date (business_id, expense_date),
    KEY idx_expenses_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_expenses_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_expenses_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
