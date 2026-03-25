-- Add "Tip" to expense category options (for recording crew tip payouts).
-- Idempotent per business.

SET NAMES utf8mb4;

INSERT INTO form_select_values (business_id, form_key, section_key, option_value, sort_order, is_active, created_at, updated_at)
SELECT b.id, 'expenses', 'expense_category', 'Tip', 55, 1, NOW(), NOW()
FROM businesses b
LEFT JOIN form_select_values v
  ON v.business_id = b.id
 AND v.form_key = 'expenses'
 AND v.section_key = 'expense_category'
 AND LOWER(v.option_value) = 'tip'
 AND v.deleted_at IS NULL
WHERE v.id IS NULL;
