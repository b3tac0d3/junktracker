-- Estate sales module (Phase 1)
-- Safe to run multiple times.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS estate_sales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    status VARCHAR(60) NOT NULL DEFAULT 'scheduled',
    start_at DATETIME NULL,
    end_at DATETIME NULL,
    address_line1 VARCHAR(190) NULL,
    address_line2 VARCHAR(190) NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(60) NULL,
    postal_code VARCHAR(30) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_estate_sales_business (business_id),
    KEY idx_estate_sales_business_status (business_id, status),
    KEY idx_estate_sales_business_start (business_id, start_at),
    KEY idx_estate_sales_business_deleted (business_id, deleted_at),
    CONSTRAINT fk_estate_sales_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estate_sale_customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    estate_sale_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(90) NULL,
    last_name VARCHAR(90) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(60) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_estate_sale_customers_sale (business_id, estate_sale_id),
    KEY idx_estate_sale_customers_name (business_id, last_name, first_name),
    KEY idx_estate_sale_customers_deleted (business_id, deleted_at),
    CONSTRAINT fk_estate_sale_customers_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_estate_sale_customers_sale FOREIGN KEY (estate_sale_id) REFERENCES estate_sales(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO form_select_values (
    business_id,
    form_key,
    section_key,
    option_value,
    sort_order,
    is_active,
    created_at,
    updated_at
)
SELECT
    b.id,
    d.form_key,
    d.section_key,
    d.option_value,
    d.sort_order,
    1,
    NOW(),
    NOW()
FROM businesses b
JOIN (
    SELECT 'estate_sales' AS form_key, 'estate_sale_status' AS section_key, 'scheduled' AS option_value, 10 AS sort_order
    UNION ALL SELECT 'estate_sales', 'estate_sale_status', 'active', 20
    UNION ALL SELECT 'estate_sales', 'estate_sale_status', 'complete', 30
    UNION ALL SELECT 'estate_sales', 'estate_sale_status', 'cancelled', 40
) d
WHERE NOT EXISTS (
    SELECT 1
    FROM form_select_values v
    WHERE v.business_id = b.id
      AND v.form_key = d.form_key
      AND v.section_key = d.section_key
      AND v.option_value = d.option_value
      AND v.deleted_at IS NULL
);

COMMIT;
