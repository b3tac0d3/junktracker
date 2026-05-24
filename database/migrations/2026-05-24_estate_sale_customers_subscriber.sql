-- Estate sale customer future-sales subscription preferences

SET @db_name = DATABASE();

SET @has_subscribes = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'estate_sale_customers'
      AND COLUMN_NAME = 'subscribes_to_future_sales'
);

SET @sql_subscribes = IF(
    @has_subscribes = 0,
    'ALTER TABLE estate_sale_customers ADD COLUMN subscribes_to_future_sales TINYINT(1) NOT NULL DEFAULT 0 AFTER state',
    'SELECT 1'
);
PREPARE stmt FROM @sql_subscribes;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_contact_method = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'estate_sale_customers'
      AND COLUMN_NAME = 'future_sales_contact_method'
);

SET @sql_contact_method = IF(
    @has_contact_method = 0,
    'ALTER TABLE estate_sale_customers ADD COLUMN future_sales_contact_method VARCHAR(20) NULL AFTER subscribes_to_future_sales',
    'SELECT 1'
);
PREPARE stmt FROM @sql_contact_method;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
