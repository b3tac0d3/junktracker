-- Estate sale customer queue numbers and check-in / check-out tracking
-- Date: 2026-05-21

START TRANSACTION;

SET @schema := DATABASE();

-- queue_number on estate_sale_customers
SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'queue_number'
    ),
    'SELECT 1',
    'ALTER TABLE estate_sale_customers ADD COLUMN queue_number INT UNSIGNED NOT NULL DEFAULT 0 AFTER estate_sale_id'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'checked_in_at'
    ),
    'SELECT 1',
    'ALTER TABLE estate_sale_customers ADD COLUMN checked_in_at DATETIME NULL AFTER notes'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND COLUMN_NAME = 'checked_out_at'
    ),
    'SELECT 1',
    'ALTER TABLE estate_sale_customers ADD COLUMN checked_out_at DATETIME NULL AFTER checked_in_at'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema
          AND TABLE_NAME = 'estate_sale_customers'
          AND INDEX_NAME = 'idx_estate_sale_customers_queue'
    ),
    'SELECT 1',
    'ALTER TABLE estate_sale_customers ADD INDEX idx_estate_sale_customers_queue (business_id, estate_sale_id, queue_number)'
);
PREPARE jt_stmt FROM @sql;
EXECUTE jt_stmt;
DEALLOCATE PREPARE jt_stmt;

-- Backfill queue numbers for existing customers (MySQL 8+ window functions)
UPDATE estate_sale_customers esc
INNER JOIN (
    SELECT
        id,
        ROW_NUMBER() OVER (PARTITION BY estate_sale_id ORDER BY created_at ASC, id ASC) AS rn
    FROM estate_sale_customers
    WHERE deleted_at IS NULL
) ranked ON ranked.id = esc.id
SET esc.queue_number = ranked.rn
WHERE esc.deleted_at IS NULL
  AND esc.queue_number = 0;

CREATE TABLE IF NOT EXISTS estate_sale_customer_visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    estate_sale_id BIGINT UNSIGNED NOT NULL,
    estate_sale_customer_id BIGINT UNSIGNED NOT NULL,
    checked_in_at DATETIME NOT NULL,
    checked_out_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estate_sale_customer_visits_customer (business_id, estate_sale_id, estate_sale_customer_id),
    KEY idx_estate_sale_customer_visits_checked_in (business_id, checked_in_at),
    CONSTRAINT fk_estate_sale_customer_visits_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_estate_sale_customer_visits_sale FOREIGN KEY (estate_sale_id) REFERENCES estate_sales(id) ON UPDATE CASCADE,
    CONSTRAINT fk_estate_sale_customer_visits_customer FOREIGN KEY (estate_sale_customer_id) REFERENCES estate_sale_customers(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
