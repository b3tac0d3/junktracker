-- JunkTracker v3.0.0 combined bundle
-- Generated: 2026-03-10
SET NAMES utf8mb4;

-- >>> 2026-03-08_v3_phase_c_form_select_values.sql
-- v3 Phase C - Business admin form select value management
-- Date: 2026-03-08

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS form_select_values (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    form_key VARCHAR(60) NOT NULL,
    section_key VARCHAR(60) NOT NULL,
    option_value VARCHAR(160) NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_form_select_values_scope (business_id, form_key, section_key, deleted_at),
    KEY idx_form_select_values_order (business_id, form_key, section_key, sort_order),
    KEY idx_form_select_values_active (business_id, is_active, deleted_at),
    CONSTRAINT fk_form_select_values_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default values for existing businesses when missing.
INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'clients', 'client_type', 'client', 10, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'clients'
 AND v.section_key = 'client_type'
 AND LOWER(v.option_value) = 'client'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'clients', 'client_type', 'company', 20, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'clients'
 AND v.section_key = 'client_type'
 AND LOWER(v.option_value) = 'company'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'clients', 'client_type', 'realtor', 30, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'clients'
 AND v.section_key = 'client_type'
 AND LOWER(v.option_value) = 'realtor'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'clients', 'client_type', 'other', 40, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'clients'
 AND v.section_key = 'client_type'
 AND LOWER(v.option_value) = 'other'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'sales', 'sale_type', 'shop', 10, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'sales'
 AND v.section_key = 'sale_type'
 AND LOWER(v.option_value) = 'shop'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'sales', 'sale_type', 'ebay', 20, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'sales'
 AND v.section_key = 'sale_type'
 AND LOWER(v.option_value) = 'ebay'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'sales', 'sale_type', 'scrap', 30, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'sales'
 AND v.section_key = 'sale_type'
 AND LOWER(v.option_value) = 'scrap'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'sales', 'sale_type', 'b2b', 40, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'sales'
 AND v.section_key = 'sale_type'
 AND LOWER(v.option_value) = 'b2b'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'expenses', 'expense_category', 'Fuel', 10, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'expenses'
 AND v.section_key = 'expense_category'
 AND LOWER(v.option_value) = 'fuel'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'expenses', 'expense_category', 'Disposal', 20, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'expenses'
 AND v.section_key = 'expense_category'
 AND LOWER(v.option_value) = 'disposal'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'expenses', 'expense_category', 'Materials', 30, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'expenses'
 AND v.section_key = 'expense_category'
 AND LOWER(v.option_value) = 'materials'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'expenses', 'expense_category', 'Labor', 40, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'expenses'
 AND v.section_key = 'expense_category'
 AND LOWER(v.option_value) = 'labor'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'expenses', 'expense_category', 'Payroll', 50, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'expenses'
 AND v.section_key = 'expense_category'
 AND LOWER(v.option_value) = 'payroll'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'expenses', 'expense_category', 'Supplies', 60, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'expenses'
 AND v.section_key = 'expense_category'
 AND LOWER(v.option_value) = 'supplies'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'expenses', 'expense_category', 'Rent', 70, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'expenses'
 AND v.section_key = 'expense_category'
 AND LOWER(v.option_value) = 'rent'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'expenses', 'expense_category', 'Utilities', 80, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'expenses'
 AND v.section_key = 'expense_category'
 AND LOWER(v.option_value) = 'utilities'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'expenses', 'expense_category', 'Other', 90, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'expenses'
 AND v.section_key = 'expense_category'
 AND LOWER(v.option_value) = 'other'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'billing', 'payment_method', 'check', 10, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'billing'
 AND v.section_key = 'payment_method'
 AND LOWER(v.option_value) = 'check'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'billing', 'payment_method', 'cc', 20, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'billing'
 AND v.section_key = 'payment_method'
 AND LOWER(v.option_value) = 'cc'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'billing', 'payment_method', 'cash', 30, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'billing'
 AND v.section_key = 'payment_method'
 AND LOWER(v.option_value) = 'cash'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'billing', 'payment_method', 'venmo', 40, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'billing'
 AND v.section_key = 'payment_method'
 AND LOWER(v.option_value) = 'venmo'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'billing', 'payment_method', 'cashapp', 50, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'billing'
 AND v.section_key = 'payment_method'
 AND LOWER(v.option_value) = 'cashapp'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'billing', 'payment_method', 'other', 60, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'billing'
 AND v.section_key = 'payment_method'
 AND LOWER(v.option_value) = 'other'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'billing', 'payment_type', 'deposit', 10, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'billing'
 AND v.section_key = 'payment_type'
 AND LOWER(v.option_value) = 'deposit'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'billing', 'payment_type', 'payment', 20, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'billing'
 AND v.section_key = 'payment_type'
 AND LOWER(v.option_value) = 'payment'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;


-- >>> 2026-03-09_v3_phase_c_form_select_values_backfill.sql
-- v3 Phase C follow-up
-- Populate all current form select defaults for every business (idempotent)
-- Date: 2026-03-09

SET NAMES utf8mb4;

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
    SELECT 'clients' AS form_key, 'client_type' AS section_key, 'client' AS option_value, 10 AS sort_order
    UNION ALL SELECT 'clients', 'client_type', 'company', 20
    UNION ALL SELECT 'clients', 'client_type', 'realtor', 30
    UNION ALL SELECT 'clients', 'client_type', 'other', 40

    UNION ALL SELECT 'sales', 'sale_type', 'shop', 10
    UNION ALL SELECT 'sales', 'sale_type', 'ebay', 20
    UNION ALL SELECT 'sales', 'sale_type', 'scrap', 30
    UNION ALL SELECT 'sales', 'sale_type', 'b2b', 40

    UNION ALL SELECT 'expenses', 'expense_category', 'Fuel', 10
    UNION ALL SELECT 'expenses', 'expense_category', 'Disposal', 20
    UNION ALL SELECT 'expenses', 'expense_category', 'Materials', 30
    UNION ALL SELECT 'expenses', 'expense_category', 'Labor', 40
    UNION ALL SELECT 'expenses', 'expense_category', 'Payroll', 50
    UNION ALL SELECT 'expenses', 'expense_category', 'Supplies', 60
    UNION ALL SELECT 'expenses', 'expense_category', 'Rent', 70
    UNION ALL SELECT 'expenses', 'expense_category', 'Utilities', 80
    UNION ALL SELECT 'expenses', 'expense_category', 'Other', 90

    UNION ALL SELECT 'jobs', 'job_status', 'prospect', 10
    UNION ALL SELECT 'jobs', 'job_status', 'pending', 20
    UNION ALL SELECT 'jobs', 'job_status', 'active', 30
    UNION ALL SELECT 'jobs', 'job_status', 'complete', 40
    UNION ALL SELECT 'jobs', 'job_status', 'cancelled', 50

    UNION ALL SELECT 'purchases', 'purchase_status', 'prospect', 10
    UNION ALL SELECT 'purchases', 'purchase_status', 'pending', 20
    UNION ALL SELECT 'purchases', 'purchase_status', 'active', 30
    UNION ALL SELECT 'purchases', 'purchase_status', 'complete', 40
    UNION ALL SELECT 'purchases', 'purchase_status', 'cancelled', 50

    UNION ALL SELECT 'tasks', 'task_status', 'open', 10
    UNION ALL SELECT 'tasks', 'task_status', 'in_progress', 20
    UNION ALL SELECT 'tasks', 'task_status', 'closed', 30

    UNION ALL SELECT 'billing', 'estimate_status', 'draft', 10
    UNION ALL SELECT 'billing', 'estimate_status', 'sent', 20
    UNION ALL SELECT 'billing', 'estimate_status', 'approved', 30
    UNION ALL SELECT 'billing', 'estimate_status', 'declined', 40

    UNION ALL SELECT 'billing', 'invoice_status', 'unsent', 10
    UNION ALL SELECT 'billing', 'invoice_status', 'sent', 20
    UNION ALL SELECT 'billing', 'invoice_status', 'partially_paid', 30
    UNION ALL SELECT 'billing', 'invoice_status', 'paid_in_full', 40

    UNION ALL SELECT 'billing', 'payment_method', 'check', 10
    UNION ALL SELECT 'billing', 'payment_method', 'cc', 20
    UNION ALL SELECT 'billing', 'payment_method', 'cash', 30
    UNION ALL SELECT 'billing', 'payment_method', 'venmo', 40
    UNION ALL SELECT 'billing', 'payment_method', 'cashapp', 50
    UNION ALL SELECT 'billing', 'payment_method', 'other', 60

    UNION ALL SELECT 'billing', 'payment_type', 'deposit', 10
    UNION ALL SELECT 'billing', 'payment_type', 'payment', 20
) d
LEFT JOIN form_select_values v
    ON v.business_id = b.id
   AND v.form_key = d.form_key
   AND v.section_key = d.section_key
   AND v.deleted_at IS NULL
   AND LOWER(v.option_value) = LOWER(d.option_value)
WHERE v.id IS NULL;
