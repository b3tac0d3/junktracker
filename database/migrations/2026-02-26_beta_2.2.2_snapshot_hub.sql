CREATE TABLE IF NOT EXISTS performance_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
    user_id BIGINT UNSIGNED NULL,
    label VARCHAR(120) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    gross_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expense_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    net_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    summary_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_performance_snapshots_business_created (business_id, created_at),
    KEY idx_performance_snapshots_business_range (business_id, start_date, end_date),
    KEY idx_performance_snapshots_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
