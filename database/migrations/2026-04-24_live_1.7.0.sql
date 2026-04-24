-- Live 1.7.0: standalone quotes pipeline + estimate->quote linkage support.
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS quotes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'new',
  service_type VARCHAR(120) NULL,
  quoted_amount DECIMAL(12,2) NULL,
  notes TEXT NULL,
  next_follow_up_at DATETIME NULL,
  lost_reason VARCHAR(190) NULL,
  converted_job_id INT UNSIGNED NULL,
  source VARCHAR(120) NULL,
  priority VARCHAR(80) NULL,
  address_line1 VARCHAR(190) NULL,
  address_line2 VARCHAR(190) NULL,
  city VARCHAR(120) NULL,
  state VARCHAR(30) NULL,
  postal_code VARCHAR(30) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_quotes_business_status (business_id, status),
  KEY idx_quotes_client (client_id),
  KEY idx_quotes_follow_up (next_follow_up_at),
  KEY idx_quotes_converted_job (converted_job_id)
);

SET @add_quote_id := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
      AND COLUMN_NAME = 'quote_id'
  ),
  'SELECT 1',
  'ALTER TABLE invoices ADD COLUMN quote_id INT UNSIGNED NULL AFTER job_id'
);
PREPARE stmt_add_quote_id FROM @add_quote_id;
EXECUTE stmt_add_quote_id;
DEALLOCATE PREPARE stmt_add_quote_id;

SET @add_quote_idx := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
      AND INDEX_NAME = 'idx_invoices_quote_id'
  ),
  'SELECT 1',
  'ALTER TABLE invoices ADD INDEX idx_invoices_quote_id (quote_id)'
);
PREPARE stmt_add_quote_idx FROM @add_quote_idx;
EXECUTE stmt_add_quote_idx;
DEALLOCATE PREPARE stmt_add_quote_idx;
