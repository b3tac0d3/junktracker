-- Per-sale customer attendance (reuse one customer profile across estate sales)
-- Date: 2026-05-24

START TRANSACTION;

SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS estate_sale_customer_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    estate_sale_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    queue_number INT UNSIGNED NOT NULL DEFAULT 0,
    checked_in_at DATETIME NULL,
    checked_out_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_estate_sale_customer_membership (business_id, estate_sale_id, customer_id),
    KEY idx_estate_sale_customer_memberships_sale (business_id, estate_sale_id, deleted_at),
    KEY idx_estate_sale_customer_memberships_customer (business_id, customer_id, deleted_at),
    KEY idx_estate_sale_customer_memberships_queue (business_id, estate_sale_id, queue_number),
    CONSTRAINT fk_escm_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON UPDATE CASCADE,
    CONSTRAINT fk_escm_sale FOREIGN KEY (estate_sale_id) REFERENCES estate_sales(id) ON UPDATE CASCADE,
    CONSTRAINT fk_escm_customer FOREIGN KEY (customer_id) REFERENCES estate_sale_customers(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill memberships from existing per-sale customer rows
INSERT INTO estate_sale_customer_memberships (
    business_id,
    estate_sale_id,
    customer_id,
    queue_number,
    checked_in_at,
    checked_out_at,
    created_by,
    updated_by,
    created_at,
    updated_at
)
SELECT
    esc.business_id,
    esc.estate_sale_id,
    esc.id,
    COALESCE(esc.queue_number, 0),
    esc.checked_in_at,
    esc.checked_out_at,
    esc.created_by,
    esc.updated_by,
    esc.created_at,
    esc.updated_at
FROM estate_sale_customers esc
WHERE esc.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM estate_sale_customer_memberships m
      WHERE m.business_id = esc.business_id
        AND m.estate_sale_id = esc.estate_sale_id
        AND m.customer_id = esc.id
        AND m.deleted_at IS NULL
  );

COMMIT;
