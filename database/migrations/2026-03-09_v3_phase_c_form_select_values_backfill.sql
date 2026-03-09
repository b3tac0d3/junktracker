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
