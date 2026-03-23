-- v3 Phase B: Business details profile fields for estimate/invoice output
-- Date: 2026-03-01

START TRANSACTION;

ALTER TABLE businesses
    ADD COLUMN primary_contact_name VARCHAR(190) NULL AFTER phone,
    ADD COLUMN website_url VARCHAR(255) NULL AFTER primary_contact_name,
    ADD COLUMN ein_number VARCHAR(40) NULL AFTER website_url,
    ADD COLUMN mailing_same_as_physical TINYINT(1) NOT NULL DEFAULT 1 AFTER country,
    ADD COLUMN mailing_address_line1 VARCHAR(190) NULL AFTER mailing_same_as_physical,
    ADD COLUMN mailing_address_line2 VARCHAR(190) NULL AFTER mailing_address_line1,
    ADD COLUMN mailing_city VARCHAR(120) NULL AFTER mailing_address_line2,
    ADD COLUMN mailing_state VARCHAR(60) NULL AFTER mailing_city,
    ADD COLUMN mailing_postal_code VARCHAR(30) NULL AFTER mailing_state,
    ADD COLUMN mailing_country VARCHAR(80) NULL DEFAULT 'US' AFTER mailing_postal_code;

UPDATE businesses
SET
    mailing_address_line1 = COALESCE(NULLIF(mailing_address_line1, ''), address_line1),
    mailing_address_line2 = COALESCE(NULLIF(mailing_address_line2, ''), address_line2),
    mailing_city = COALESCE(NULLIF(mailing_city, ''), city),
    mailing_state = COALESCE(NULLIF(mailing_state, ''), state),
    mailing_postal_code = COALESCE(NULLIF(mailing_postal_code, ''), postal_code),
    mailing_country = COALESCE(NULLIF(mailing_country, ''), country),
    mailing_same_as_physical = 1
WHERE deleted_at IS NULL;

COMMIT;
