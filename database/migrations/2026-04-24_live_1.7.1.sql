-- Live 1.7.1: normalize form_select_values soft-delete/audit columns.
-- Safe to run multiple times.

SET @add_is_active := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'form_select_values'
      AND COLUMN_NAME = 'is_active'
  ),
  'SELECT 1',
  'ALTER TABLE form_select_values ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order'
);
PREPARE stmt_add_is_active FROM @add_is_active;
EXECUTE stmt_add_is_active;
DEALLOCATE PREPARE stmt_add_is_active;

SET @add_deleted_at := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'form_select_values'
      AND COLUMN_NAME = 'deleted_at'
  ),
  'SELECT 1',
  'ALTER TABLE form_select_values ADD COLUMN deleted_at DATETIME NULL AFTER updated_at'
);
PREPARE stmt_add_deleted_at FROM @add_deleted_at;
EXECUTE stmt_add_deleted_at;
DEALLOCATE PREPARE stmt_add_deleted_at;

SET @add_deleted_by := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'form_select_values'
      AND COLUMN_NAME = 'deleted_by'
  ),
  'SELECT 1',
  'ALTER TABLE form_select_values ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at'
);
PREPARE stmt_add_deleted_by FROM @add_deleted_by;
EXECUTE stmt_add_deleted_by;
DEALLOCATE PREPARE stmt_add_deleted_by;

SET @add_active_idx := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'form_select_values'
      AND INDEX_NAME = 'idx_form_select_values_active'
  ),
  'SELECT 1',
  'ALTER TABLE form_select_values ADD INDEX idx_form_select_values_active (business_id, is_active, deleted_at)'
);
PREPARE stmt_add_active_idx FROM @add_active_idx;
EXECUTE stmt_add_active_idx;
DEALLOCATE PREPARE stmt_add_active_idx;
