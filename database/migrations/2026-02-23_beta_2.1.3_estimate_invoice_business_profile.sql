-- JunkTracker 2.1.3 (beta)
-- Estimate + invoice line items, business profile expansion, and logo support.
-- Safe to run multiple times.

SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(190) NOT NULL,
    checksum VARCHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_schema_migrations_key (migration_key),
    KEY idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- businesses profile columns
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses' AND BINARY COLUMN_NAME = BINARY 'address_line1'),
    'ALTER TABLE businesses ADD COLUMN address_line1 VARCHAR(190) NULL AFTER website',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses' AND BINARY COLUMN_NAME = BINARY 'address_line2'),
    'ALTER TABLE businesses ADD COLUMN address_line2 VARCHAR(190) NULL AFTER address_line1',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses' AND BINARY COLUMN_NAME = BINARY 'city'),
    'ALTER TABLE businesses ADD COLUMN city VARCHAR(120) NULL AFTER address_line2',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses' AND BINARY COLUMN_NAME = BINARY 'state'),
    'ALTER TABLE businesses ADD COLUMN state VARCHAR(120) NULL AFTER city',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses' AND BINARY COLUMN_NAME = BINARY 'postal_code'),
    'ALTER TABLE businesses ADD COLUMN postal_code VARCHAR(40) NULL AFTER state',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses' AND BINARY COLUMN_NAME = BINARY 'country'),
    'ALTER TABLE businesses ADD COLUMN country VARCHAR(80) NULL AFTER postal_code',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses' AND BINARY COLUMN_NAME = BINARY 'tax_id'),
    'ALTER TABLE businesses ADD COLUMN tax_id VARCHAR(100) NULL AFTER country',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses' AND BINARY COLUMN_NAME = BINARY 'invoice_default_tax_rate'),
    'ALTER TABLE businesses ADD COLUMN invoice_default_tax_rate DECIMAL(8,4) NULL AFTER tax_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses' AND BINARY COLUMN_NAME = BINARY 'timezone'),
    'ALTER TABLE businesses ADD COLUMN timezone VARCHAR(80) NULL AFTER invoice_default_tax_rate',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses' AND BINARY COLUMN_NAME = BINARY 'logo_path'),
    'ALTER TABLE businesses ADD COLUMN logo_path VARCHAR(255) NULL AFTER timezone',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'businesses' AND BINARY COLUMN_NAME = BINARY 'logo_mime_type'),
    'ALTER TABLE businesses ADD COLUMN logo_mime_type VARCHAR(120) NULL AFTER logo_path',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- job_estimate_invoices document expansion
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices' AND BINARY COLUMN_NAME = BINARY 'customer_note'),
    'ALTER TABLE job_estimate_invoices ADD COLUMN customer_note TEXT NULL AFTER note',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices' AND BINARY COLUMN_NAME = BINARY 'subtotal_amount'),
    'ALTER TABLE job_estimate_invoices ADD COLUMN subtotal_amount DECIMAL(12,2) NULL AFTER amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices' AND BINARY COLUMN_NAME = BINARY 'tax_rate'),
    'ALTER TABLE job_estimate_invoices ADD COLUMN tax_rate DECIMAL(8,4) NULL AFTER subtotal_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices' AND BINARY COLUMN_NAME = BINARY 'tax_amount'),
    'ALTER TABLE job_estimate_invoices ADD COLUMN tax_amount DECIMAL(12,2) NULL AFTER tax_rate',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices' AND BINARY COLUMN_NAME = BINARY 'source_estimate_id'),
    'ALTER TABLE job_estimate_invoices ADD COLUMN source_estimate_id BIGINT UNSIGNED NULL AFTER customer_note',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices')
    AND NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoices' AND BINARY INDEX_NAME = BINARY 'idx_job_estimate_invoices_source_estimate'),
    'ALTER TABLE job_estimate_invoices ADD INDEX idx_job_estimate_invoices_source_estimate (source_estimate_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @schema AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoices_source_estimate'),
    'SELECT 1',
    'ALTER TABLE job_estimate_invoices
     ADD CONSTRAINT fk_job_estimate_invoices_source_estimate
     FOREIGN KEY (source_estimate_id) REFERENCES job_estimate_invoices(id)
     ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- business item types
CREATE TABLE IF NOT EXISTS business_document_item_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    item_label VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_business_document_item_types_code (business_id, item_code),
    KEY idx_business_document_item_types_active (business_id, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @schema AND BINARY CONSTRAINT_NAME = BINARY 'fk_business_document_item_types_business'),
    'SELECT 1',
    'ALTER TABLE business_document_item_types
     ADD CONSTRAINT fk_business_document_item_types_business
     FOREIGN KEY (business_id) REFERENCES businesses(id)
     ON DELETE CASCADE ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- line items
CREATE TABLE IF NOT EXISTS job_estimate_invoice_line_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    item_type_id BIGINT UNSIGNED NULL,
    item_type_label VARCHAR(120) NOT NULL,
    item_description VARCHAR(255) NOT NULL,
    line_note VARCHAR(255) NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_taxable TINYINT(1) NOT NULL DEFAULT 1,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_job_estimate_invoice_line_items_doc (document_id, sort_order, id),
    KEY idx_job_estimate_invoice_line_items_job (job_id),
    KEY idx_job_estimate_invoice_line_items_type (item_type_id),
    KEY idx_job_estimate_invoice_line_items_created_by (created_by),
    KEY idx_job_estimate_invoice_line_items_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoice_line_items')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema AND BINARY TABLE_NAME = BINARY 'job_estimate_invoice_line_items' AND BINARY COLUMN_NAME = BINARY 'is_taxable'),
    'ALTER TABLE job_estimate_invoice_line_items ADD COLUMN is_taxable TINYINT(1) NOT NULL DEFAULT 1 AFTER unit_price',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @schema AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoice_line_items_doc'),
    'SELECT 1',
    'ALTER TABLE job_estimate_invoice_line_items
     ADD CONSTRAINT fk_job_estimate_invoice_line_items_doc
     FOREIGN KEY (document_id) REFERENCES job_estimate_invoices(id)
     ON DELETE CASCADE ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @schema AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoice_line_items_job'),
    'SELECT 1',
    'ALTER TABLE job_estimate_invoice_line_items
     ADD CONSTRAINT fk_job_estimate_invoice_line_items_job
     FOREIGN KEY (job_id) REFERENCES jobs(id)
     ON DELETE CASCADE ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @schema AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoice_line_items_type'),
    'SELECT 1',
    'ALTER TABLE job_estimate_invoice_line_items
     ADD CONSTRAINT fk_job_estimate_invoice_line_items_type
     FOREIGN KEY (item_type_id) REFERENCES business_document_item_types(id)
     ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @schema AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoice_line_items_created_by'),
    'SELECT 1',
    'ALTER TABLE job_estimate_invoice_line_items
     ADD CONSTRAINT fk_job_estimate_invoice_line_items_created_by
     FOREIGN KEY (created_by) REFERENCES users(id)
     ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @schema AND BINARY CONSTRAINT_NAME = BINARY 'fk_job_estimate_invoice_line_items_updated_by'),
    'SELECT 1',
    'ALTER TABLE job_estimate_invoice_line_items
     ADD CONSTRAINT fk_job_estimate_invoice_line_items_updated_by
     FOREIGN KEY (updated_by) REFERENCES users(id)
     ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- seed default item types for every business
INSERT INTO business_document_item_types (business_id, item_code, item_label, sort_order, is_active, created_at, updated_at)
SELECT b.id, s.item_code, s.item_label, s.sort_order, 1, NOW(), NOW()
FROM businesses b
JOIN (
    SELECT 'service' AS item_code, 'Service' AS item_label, 10 AS sort_order
    UNION ALL SELECT 'labor', 'Labor', 20
    UNION ALL SELECT 'tools', 'Tools / Equipment', 30
    UNION ALL SELECT 'supplies', 'Supplies', 40
    UNION ALL SELECT 'disposal', 'Disposal / Dump Fee', 50
    UNION ALL SELECT 'travel', 'Travel / Delivery', 60
    UNION ALL SELECT 'other', 'Other', 99
) s
WHERE NOT EXISTS (
    SELECT 1
    FROM business_document_item_types t
    WHERE t.business_id = b.id
      AND t.item_code = s.item_code
);

INSERT INTO schema_migrations (migration_key, checksum, applied_at)
SELECT '2026-02-23_beta_2.1.3_estimate_invoice_business_profile', NULL, NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM schema_migrations
    WHERE migration_key = '2026-02-23_beta_2.1.3_estimate_invoice_business_profile'
);
