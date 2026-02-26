-- Beta 2.2.1 local dummy data seed
-- Purpose: populate local database with realistic multi-business records for testing.
-- Safe to run multiple times: seed rows are keyed by deterministic names/emails and inserted only if missing.

SET @seed_tag := 'seed_2026_02_25';
SET @seed_hash := '$2y$10$E3QwC9lPNRG9C3g2jeX78O7kt/s11rkrseyVJuSfmNXX3X0tf3VOi'; -- Password123!
SET @seed_now := NOW();

-- ------------------------------------------------------------
-- Businesses
-- ------------------------------------------------------------
INSERT INTO businesses (
    name, legal_name, email, phone, website,
    address_line1, city, state, postal_code, country,
    tax_id, invoice_default_tax_rate, timezone, is_active, created_at, updated_at
)
SELECT
    'Seed Alpha Services', 'Seed Alpha Services LLC', 'seed.alpha.business@example.com', '(401) 555-1101', 'https://alpha-seed.local',
    '101 Alpha Ave', 'Providence', 'RI', '02903', 'US',
    '11-1111111', 0.0700, 'America/New_York', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM businesses WHERE name = 'Seed Alpha Services');

INSERT INTO businesses (
    name, legal_name, email, phone, website,
    address_line1, city, state, postal_code, country,
    tax_id, invoice_default_tax_rate, timezone, is_active, created_at, updated_at
)
SELECT
    'Seed Bravo Hauling', 'Seed Bravo Hauling Inc', 'seed.bravo.business@example.com', '(401) 555-2202', 'https://bravo-seed.local',
    '202 Bravo Blvd', 'Cranston', 'RI', '02910', 'US',
    '22-2222222', 0.0700, 'America/New_York', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM businesses WHERE name = 'Seed Bravo Hauling');

INSERT INTO businesses (
    name, legal_name, email, phone, website,
    address_line1, city, state, postal_code, country,
    tax_id, invoice_default_tax_rate, timezone, is_active, created_at, updated_at
)
SELECT
    'Seed Charlie Cleanouts', 'Seed Charlie Cleanouts Co', 'seed.charlie.business@example.com', '(401) 555-3303', 'https://charlie-seed.local',
    '303 Charlie Ct', 'Warwick', 'RI', '02886', 'US',
    '33-3333333', 0.0625, 'America/New_York', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM businesses WHERE name = 'Seed Charlie Cleanouts');

SELECT @biz_alpha := id FROM businesses WHERE name = 'Seed Alpha Services' ORDER BY id DESC LIMIT 1;
SELECT @biz_bravo := id FROM businesses WHERE name = 'Seed Bravo Hauling' ORDER BY id DESC LIMIT 1;
SELECT @biz_charlie := id FROM businesses WHERE name = 'Seed Charlie Cleanouts' ORDER BY id DESC LIMIT 1;

-- ------------------------------------------------------------
-- Users
-- ------------------------------------------------------------
INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, updated_at
)
SELECT
    'seed.siteadmin.20260225@example.com', @seed_hash, 'Seed', 'SiteAdmin', 4,
    @biz_alpha, 1, @seed_now, 0,
    @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.siteadmin.20260225@example.com');

SELECT @site_admin_id := id FROM users WHERE email = 'seed.siteadmin.20260225@example.com' LIMIT 1;

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.alpha.admin.20260225@example.com', @seed_hash, 'Alice', 'AlphaAdmin', 3,
    @biz_alpha, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.alpha.admin.20260225@example.com');

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.alpha.manager.20260225@example.com', @seed_hash, 'Mason', 'AlphaManager', 2,
    @biz_alpha, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.alpha.manager.20260225@example.com');

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.alpha.user.20260225@example.com', @seed_hash, 'Uma', 'AlphaUser', 1,
    @biz_alpha, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.alpha.user.20260225@example.com');

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.alpha.punch.20260225@example.com', @seed_hash, 'Paul', 'AlphaPunch', 0,
    @biz_alpha, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.alpha.punch.20260225@example.com');

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.bravo.admin.20260225@example.com', @seed_hash, 'Ben', 'BravoAdmin', 3,
    @biz_bravo, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.bravo.admin.20260225@example.com');

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.bravo.manager.20260225@example.com', @seed_hash, 'Mia', 'BravoManager', 2,
    @biz_bravo, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.bravo.manager.20260225@example.com');

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.bravo.user.20260225@example.com', @seed_hash, 'Ulysses', 'BravoUser', 1,
    @biz_bravo, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.bravo.user.20260225@example.com');

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.bravo.punch.20260225@example.com', @seed_hash, 'Penny', 'BravoPunch', 0,
    @biz_bravo, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.bravo.punch.20260225@example.com');

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.charlie.admin.20260225@example.com', @seed_hash, 'Cara', 'CharlieAdmin', 3,
    @biz_charlie, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.charlie.admin.20260225@example.com');

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.charlie.manager.20260225@example.com', @seed_hash, 'Mark', 'CharlieManager', 2,
    @biz_charlie, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.charlie.manager.20260225@example.com');

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.charlie.user.20260225@example.com', @seed_hash, 'Uri', 'CharlieUser', 1,
    @biz_charlie, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.charlie.user.20260225@example.com');

INSERT INTO users (
    email, password_hash, first_name, last_name, role,
    business_id, is_active, email_verified_at, two_factor_enabled,
    created_at, created_by, updated_at, updated_by
)
SELECT
    'seed.charlie.punch.20260225@example.com', @seed_hash, 'Parker', 'CharliePunch', 0,
    @biz_charlie, 1, @seed_now, 0,
    @seed_now, @site_admin_id, @seed_now, @site_admin_id
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'seed.charlie.punch.20260225@example.com');

SELECT @alpha_admin_id := id FROM users WHERE email = 'seed.alpha.admin.20260225@example.com' LIMIT 1;
SELECT @alpha_manager_id := id FROM users WHERE email = 'seed.alpha.manager.20260225@example.com' LIMIT 1;
SELECT @alpha_user_id := id FROM users WHERE email = 'seed.alpha.user.20260225@example.com' LIMIT 1;
SELECT @alpha_punch_id := id FROM users WHERE email = 'seed.alpha.punch.20260225@example.com' LIMIT 1;

SELECT @bravo_admin_id := id FROM users WHERE email = 'seed.bravo.admin.20260225@example.com' LIMIT 1;
SELECT @bravo_manager_id := id FROM users WHERE email = 'seed.bravo.manager.20260225@example.com' LIMIT 1;
SELECT @bravo_user_id := id FROM users WHERE email = 'seed.bravo.user.20260225@example.com' LIMIT 1;
SELECT @bravo_punch_id := id FROM users WHERE email = 'seed.bravo.punch.20260225@example.com' LIMIT 1;

SELECT @charlie_admin_id := id FROM users WHERE email = 'seed.charlie.admin.20260225@example.com' LIMIT 1;
SELECT @charlie_manager_id := id FROM users WHERE email = 'seed.charlie.manager.20260225@example.com' LIMIT 1;
SELECT @charlie_user_id := id FROM users WHERE email = 'seed.charlie.user.20260225@example.com' LIMIT 1;
SELECT @charlie_punch_id := id FROM users WHERE email = 'seed.charlie.punch.20260225@example.com' LIMIT 1;

-- ------------------------------------------------------------
-- Settings + lookups
-- ------------------------------------------------------------
INSERT INTO app_settings (setting_key, setting_value, updated_by, updated_at)
VALUES
    ('seed.demo.last_run', DATE_FORMAT(@seed_now, '%Y-%m-%d %H:%i:%s'), @site_admin_id, @seed_now),
    ('seed.demo.mode', 'multi_business', @site_admin_id, @seed_now)
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    updated_by = VALUES(updated_by),
    updated_at = VALUES(updated_at);

INSERT INTO app_lookups (
    group_key, value_key, label, sort_order, active, created_by, updated_by, created_at, updated_at
)
VALUES
    ('seed_demo_priority', 'p1', 'Seed Priority 1', 10, 1, @site_admin_id, @site_admin_id, @seed_now, @seed_now),
    ('seed_demo_priority', 'p2', 'Seed Priority 2', 20, 1, @site_admin_id, @site_admin_id, @seed_now, @seed_now),
    ('seed_demo_priority', 'p3', 'Seed Priority 3', 30, 1, @site_admin_id, @site_admin_id, @seed_now, @seed_now)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    sort_order = VALUES(sort_order),
    active = VALUES(active),
    updated_by = VALUES(updated_by),
    updated_at = VALUES(updated_at);

-- ------------------------------------------------------------
-- Companies, clients, estates
-- ------------------------------------------------------------
INSERT INTO companies (
    business_id, name, note, phone, web_address, city, state, zip,
    created_by, updated_by, active, created_at, updated_at
)
SELECT @biz_alpha, 'Seed Alpha Realty', CONCAT(@seed_tag, ' company'), '(401) 555-4101', 'https://alpha-realty.local', 'Providence', 'RI', '02903',
       @alpha_admin_id, @alpha_admin_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE business_id = @biz_alpha AND name = 'Seed Alpha Realty');

INSERT INTO companies (
    business_id, name, note, phone, web_address, city, state, zip,
    created_by, updated_by, active, created_at, updated_at
)
SELECT @biz_alpha, 'Seed Alpha Partners', CONCAT(@seed_tag, ' company'), '(401) 555-4102', 'https://alpha-partners.local', 'Cranston', 'RI', '02910',
       @alpha_admin_id, @alpha_admin_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE business_id = @biz_alpha AND name = 'Seed Alpha Partners');

INSERT INTO companies (
    business_id, name, note, phone, web_address, city, state, zip,
    created_by, updated_by, active, created_at, updated_at
)
SELECT @biz_bravo, 'Seed Bravo Realty', CONCAT(@seed_tag, ' company'), '(401) 555-4201', 'https://bravo-realty.local', 'Warwick', 'RI', '02886',
       @bravo_admin_id, @bravo_admin_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE business_id = @biz_bravo AND name = 'Seed Bravo Realty');

INSERT INTO companies (
    business_id, name, note, phone, web_address, city, state, zip,
    created_by, updated_by, active, created_at, updated_at
)
SELECT @biz_bravo, 'Seed Bravo Logistics', CONCAT(@seed_tag, ' company'), '(401) 555-4202', 'https://bravo-logistics.local', 'Johnston', 'RI', '02919',
       @bravo_admin_id, @bravo_admin_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE business_id = @biz_bravo AND name = 'Seed Bravo Logistics');

INSERT INTO companies (
    business_id, name, note, phone, web_address, city, state, zip,
    created_by, updated_by, active, created_at, updated_at
)
SELECT @biz_charlie, 'Seed Charlie Realty', CONCAT(@seed_tag, ' company'), '(401) 555-4301', 'https://charlie-realty.local', 'Pawtucket', 'RI', '02860',
       @charlie_admin_id, @charlie_admin_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE business_id = @biz_charlie AND name = 'Seed Charlie Realty');

INSERT INTO companies (
    business_id, name, note, phone, web_address, city, state, zip,
    created_by, updated_by, active, created_at, updated_at
)
SELECT @biz_charlie, 'Seed Charlie Holdings', CONCAT(@seed_tag, ' company'), '(401) 555-4302', 'https://charlie-holdings.local', 'East Providence', 'RI', '02914',
       @charlie_admin_id, @charlie_admin_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE business_id = @biz_charlie AND name = 'Seed Charlie Holdings');

SELECT @alpha_company_1 := id FROM companies WHERE business_id = @biz_alpha AND name = 'Seed Alpha Realty' ORDER BY id DESC LIMIT 1;
SELECT @alpha_company_2 := id FROM companies WHERE business_id = @biz_alpha AND name = 'Seed Alpha Partners' ORDER BY id DESC LIMIT 1;
SELECT @bravo_company_1 := id FROM companies WHERE business_id = @biz_bravo AND name = 'Seed Bravo Realty' ORDER BY id DESC LIMIT 1;
SELECT @bravo_company_2 := id FROM companies WHERE business_id = @biz_bravo AND name = 'Seed Bravo Logistics' ORDER BY id DESC LIMIT 1;
SELECT @charlie_company_1 := id FROM companies WHERE business_id = @biz_charlie AND name = 'Seed Charlie Realty' ORDER BY id DESC LIMIT 1;
SELECT @charlie_company_2 := id FROM companies WHERE business_id = @biz_charlie AND name = 'Seed Charlie Holdings' ORDER BY id DESC LIMIT 1;

INSERT INTO clients (
    business_id, first_name, last_name, phone, can_text, email,
    address_1, city, state, zip, client_type, note, active,
    created_at, created_by, updated_at
)
SELECT @biz_alpha, 'Ava', 'Anderson', '(401) 555-5101', 1, 'seed.alpha.client1@example.com',
       '11 Oak St', 'Providence', 'RI', '02903', 'client', CONCAT(@seed_tag, ' primary client'), 1,
       @seed_now, @alpha_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE business_id = @biz_alpha AND email = 'seed.alpha.client1@example.com');

INSERT INTO clients (
    business_id, first_name, last_name, phone, can_text, email,
    address_1, city, state, zip, client_type, note, active,
    created_at, created_by, updated_at
)
SELECT @biz_alpha, 'Ryan', 'Realtor', '(401) 555-5102', 1, 'seed.alpha.client2@example.com',
       '12 Oak St', 'Providence', 'RI', '02904', 'realtor', CONCAT(@seed_tag, ' realtor lead'), 1,
       @seed_now, @alpha_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE business_id = @biz_alpha AND email = 'seed.alpha.client2@example.com');

INSERT INTO clients (
    business_id, first_name, last_name, phone, can_text, email,
    address_1, city, state, zip, client_type, note, active,
    created_at, created_by, updated_at
)
SELECT @biz_alpha, 'Otto', 'Other', '(401) 555-5103', 0, 'seed.alpha.client3@example.com',
       '13 Oak St', 'Cranston', 'RI', '02910', 'other', CONCAT(@seed_tag, ' other contact'), 1,
       @seed_now, @alpha_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE business_id = @biz_alpha AND email = 'seed.alpha.client3@example.com');

INSERT INTO clients (
    business_id, first_name, last_name, phone, can_text, email,
    address_1, city, state, zip, client_type, note, active,
    created_at, created_by, updated_at
)
SELECT @biz_bravo, 'Bianca', 'Baker', '(401) 555-5201', 1, 'seed.bravo.client1@example.com',
       '21 Main St', 'Warwick', 'RI', '02886', 'client', CONCAT(@seed_tag, ' primary client'), 1,
       @seed_now, @bravo_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE business_id = @biz_bravo AND email = 'seed.bravo.client1@example.com');

INSERT INTO clients (
    business_id, first_name, last_name, phone, can_text, email,
    address_1, city, state, zip, client_type, note, active,
    created_at, created_by, updated_at
)
SELECT @biz_bravo, 'Rita', 'Realtor', '(401) 555-5202', 1, 'seed.bravo.client2@example.com',
       '22 Main St', 'Warwick', 'RI', '02888', 'realtor', CONCAT(@seed_tag, ' realtor lead'), 1,
       @seed_now, @bravo_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE business_id = @biz_bravo AND email = 'seed.bravo.client2@example.com');

INSERT INTO clients (
    business_id, first_name, last_name, phone, can_text, email,
    address_1, city, state, zip, client_type, note, active,
    created_at, created_by, updated_at
)
SELECT @biz_bravo, 'Oscar', 'Other', '(401) 555-5203', 0, 'seed.bravo.client3@example.com',
       '23 Main St', 'Johnston', 'RI', '02919', 'other', CONCAT(@seed_tag, ' other contact'), 1,
       @seed_now, @bravo_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE business_id = @biz_bravo AND email = 'seed.bravo.client3@example.com');

INSERT INTO clients (
    business_id, first_name, last_name, phone, can_text, email,
    address_1, city, state, zip, client_type, note, active,
    created_at, created_by, updated_at
)
SELECT @biz_charlie, 'Chloe', 'Carter', '(401) 555-5301', 1, 'seed.charlie.client1@example.com',
       '31 Park Ave', 'Pawtucket', 'RI', '02860', 'client', CONCAT(@seed_tag, ' primary client'), 1,
       @seed_now, @charlie_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE business_id = @biz_charlie AND email = 'seed.charlie.client1@example.com');

INSERT INTO clients (
    business_id, first_name, last_name, phone, can_text, email,
    address_1, city, state, zip, client_type, note, active,
    created_at, created_by, updated_at
)
SELECT @biz_charlie, 'Renee', 'Realtor', '(401) 555-5302', 1, 'seed.charlie.client2@example.com',
       '32 Park Ave', 'Pawtucket', 'RI', '02861', 'realtor', CONCAT(@seed_tag, ' realtor lead'), 1,
       @seed_now, @charlie_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE business_id = @biz_charlie AND email = 'seed.charlie.client2@example.com');

INSERT INTO clients (
    business_id, first_name, last_name, phone, can_text, email,
    address_1, city, state, zip, client_type, note, active,
    created_at, created_by, updated_at
)
SELECT @biz_charlie, 'Owen', 'Other', '(401) 555-5303', 0, 'seed.charlie.client3@example.com',
       '33 Park Ave', 'East Providence', 'RI', '02914', 'other', CONCAT(@seed_tag, ' other contact'), 1,
       @seed_now, @charlie_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE business_id = @biz_charlie AND email = 'seed.charlie.client3@example.com');

SELECT @alpha_client_1 := id FROM clients WHERE business_id = @biz_alpha AND email = 'seed.alpha.client1@example.com' ORDER BY id DESC LIMIT 1;
SELECT @alpha_client_2 := id FROM clients WHERE business_id = @biz_alpha AND email = 'seed.alpha.client2@example.com' ORDER BY id DESC LIMIT 1;
SELECT @alpha_client_3 := id FROM clients WHERE business_id = @biz_alpha AND email = 'seed.alpha.client3@example.com' ORDER BY id DESC LIMIT 1;

SELECT @bravo_client_1 := id FROM clients WHERE business_id = @biz_bravo AND email = 'seed.bravo.client1@example.com' ORDER BY id DESC LIMIT 1;
SELECT @bravo_client_2 := id FROM clients WHERE business_id = @biz_bravo AND email = 'seed.bravo.client2@example.com' ORDER BY id DESC LIMIT 1;
SELECT @bravo_client_3 := id FROM clients WHERE business_id = @biz_bravo AND email = 'seed.bravo.client3@example.com' ORDER BY id DESC LIMIT 1;

SELECT @charlie_client_1 := id FROM clients WHERE business_id = @biz_charlie AND email = 'seed.charlie.client1@example.com' ORDER BY id DESC LIMIT 1;
SELECT @charlie_client_2 := id FROM clients WHERE business_id = @biz_charlie AND email = 'seed.charlie.client2@example.com' ORDER BY id DESC LIMIT 1;
SELECT @charlie_client_3 := id FROM clients WHERE business_id = @biz_charlie AND email = 'seed.charlie.client3@example.com' ORDER BY id DESC LIMIT 1;

INSERT INTO companies_x_clients (company_id, client_id, active, created_by, updated_by, created_at, updated_at)
SELECT @alpha_company_1, @alpha_client_1, 1, @alpha_admin_id, @alpha_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM companies_x_clients WHERE company_id = @alpha_company_1 AND client_id = @alpha_client_1);

INSERT INTO companies_x_clients (company_id, client_id, active, created_by, updated_by, created_at, updated_at)
SELECT @bravo_company_1, @bravo_client_1, 1, @bravo_admin_id, @bravo_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM companies_x_clients WHERE company_id = @bravo_company_1 AND client_id = @bravo_client_1);

INSERT INTO companies_x_clients (company_id, client_id, active, created_by, updated_by, created_at, updated_at)
SELECT @charlie_company_1, @charlie_client_1, 1, @charlie_admin_id, @charlie_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM companies_x_clients WHERE company_id = @charlie_company_1 AND client_id = @charlie_client_1);

INSERT INTO estates (
    business_id, client_id, name, note, address_1, city, state, zip, phone, can_text, email, active, created_at, updated_at
)
SELECT @biz_alpha, @alpha_client_1, 'Seed Alpha Estate', CONCAT(@seed_tag, ' estate'), '99 Estate Way', 'Providence', 'RI', '02903', '(401) 555-6101', 1, 'seed.alpha.estate@example.com', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM estates WHERE business_id = @biz_alpha AND name = 'Seed Alpha Estate');

INSERT INTO estates (
    business_id, client_id, name, note, address_1, city, state, zip, phone, can_text, email, active, created_at, updated_at
)
SELECT @biz_bravo, @bravo_client_1, 'Seed Bravo Estate', CONCAT(@seed_tag, ' estate'), '88 Estate Way', 'Warwick', 'RI', '02886', '(401) 555-6201', 1, 'seed.bravo.estate@example.com', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM estates WHERE business_id = @biz_bravo AND name = 'Seed Bravo Estate');

INSERT INTO estates (
    business_id, client_id, name, note, address_1, city, state, zip, phone, can_text, email, active, created_at, updated_at
)
SELECT @biz_charlie, @charlie_client_1, 'Seed Charlie Estate', CONCAT(@seed_tag, ' estate'), '77 Estate Way', 'Pawtucket', 'RI', '02860', '(401) 555-6301', 1, 'seed.charlie.estate@example.com', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM estates WHERE business_id = @biz_charlie AND name = 'Seed Charlie Estate');

SELECT @alpha_estate_id := id FROM estates WHERE business_id = @biz_alpha AND name = 'Seed Alpha Estate' ORDER BY id DESC LIMIT 1;
SELECT @bravo_estate_id := id FROM estates WHERE business_id = @biz_bravo AND name = 'Seed Bravo Estate' ORDER BY id DESC LIMIT 1;
SELECT @charlie_estate_id := id FROM estates WHERE business_id = @biz_charlie AND name = 'Seed Charlie Estate' ORDER BY id DESC LIMIT 1;

INSERT INTO estates_x_clients (client_id, estate_id, note, created_by, updated_by, active, created_at, updated_at)
SELECT @alpha_client_2, @alpha_estate_id, CONCAT(@seed_tag, ' estate_x_client'), @alpha_admin_id, @alpha_admin_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM estates_x_clients WHERE client_id = @alpha_client_2 AND estate_id = @alpha_estate_id);

INSERT INTO estates_x_clients (client_id, estate_id, note, created_by, updated_by, active, created_at, updated_at)
SELECT @bravo_client_2, @bravo_estate_id, CONCAT(@seed_tag, ' estate_x_client'), @bravo_admin_id, @bravo_admin_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM estates_x_clients WHERE client_id = @bravo_client_2 AND estate_id = @bravo_estate_id);

INSERT INTO estates_x_clients (client_id, estate_id, note, created_by, updated_by, active, created_at, updated_at)
SELECT @charlie_client_2, @charlie_estate_id, CONCAT(@seed_tag, ' estate_x_client'), @charlie_admin_id, @charlie_admin_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM estates_x_clients WHERE client_id = @charlie_client_2 AND estate_id = @charlie_estate_id);

-- ------------------------------------------------------------
-- Employees and punch linkage
-- ------------------------------------------------------------
INSERT INTO employees (
    business_id, first_name, last_name, phone, email, user_id,
    hire_date, wage_rate, wage_type, hourly_rate, note, active, created_at, updated_at
)
SELECT @biz_alpha, 'Mason', 'AlphaManager', '(401) 555-7101', 'seed.alpha.employee.manager@example.com', @alpha_manager_id,
       '2025-01-15', 28.50, 'hourly', 28.50, CONCAT(@seed_tag, ' employee'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE user_id = @alpha_manager_id);

INSERT INTO employees (
    business_id, first_name, last_name, phone, email, user_id,
    hire_date, wage_rate, wage_type, hourly_rate, note, active, created_at, updated_at
)
SELECT @biz_alpha, 'Uma', 'AlphaUser', '(401) 555-7102', 'seed.alpha.employee.user@example.com', @alpha_user_id,
       '2025-02-01', 24.00, 'hourly', 24.00, CONCAT(@seed_tag, ' employee'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE user_id = @alpha_user_id);

INSERT INTO employees (
    business_id, first_name, last_name, phone, email, user_id,
    hire_date, wage_rate, wage_type, hourly_rate, note, active, created_at, updated_at
)
SELECT @biz_alpha, 'Paul', 'AlphaPunch', '(401) 555-7103', 'seed.alpha.employee.punch@example.com', @alpha_punch_id,
       '2025-03-01', 21.50, 'hourly', 21.50, CONCAT(@seed_tag, ' employee'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE user_id = @alpha_punch_id);

INSERT INTO employees (
    business_id, first_name, last_name, phone, email, user_id,
    hire_date, wage_rate, wage_type, hourly_rate, note, active, created_at, updated_at
)
SELECT @biz_bravo, 'Mia', 'BravoManager', '(401) 555-7201', 'seed.bravo.employee.manager@example.com', @bravo_manager_id,
       '2025-01-20', 27.00, 'hourly', 27.00, CONCAT(@seed_tag, ' employee'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE user_id = @bravo_manager_id);

INSERT INTO employees (
    business_id, first_name, last_name, phone, email, user_id,
    hire_date, wage_rate, wage_type, hourly_rate, note, active, created_at, updated_at
)
SELECT @biz_bravo, 'Ulysses', 'BravoUser', '(401) 555-7202', 'seed.bravo.employee.user@example.com', @bravo_user_id,
       '2025-02-08', 23.25, 'hourly', 23.25, CONCAT(@seed_tag, ' employee'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE user_id = @bravo_user_id);

INSERT INTO employees (
    business_id, first_name, last_name, phone, email, user_id,
    hire_date, wage_rate, wage_type, hourly_rate, note, active, created_at, updated_at
)
SELECT @biz_bravo, 'Penny', 'BravoPunch', '(401) 555-7203', 'seed.bravo.employee.punch@example.com', @bravo_punch_id,
       '2025-03-11', 20.50, 'hourly', 20.50, CONCAT(@seed_tag, ' employee'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE user_id = @bravo_punch_id);

INSERT INTO employees (
    business_id, first_name, last_name, phone, email, user_id,
    hire_date, wage_rate, wage_type, hourly_rate, note, active, created_at, updated_at
)
SELECT @biz_charlie, 'Mark', 'CharlieManager', '(401) 555-7301', 'seed.charlie.employee.manager@example.com', @charlie_manager_id,
       '2025-01-28', 26.75, 'hourly', 26.75, CONCAT(@seed_tag, ' employee'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE user_id = @charlie_manager_id);

INSERT INTO employees (
    business_id, first_name, last_name, phone, email, user_id,
    hire_date, wage_rate, wage_type, hourly_rate, note, active, created_at, updated_at
)
SELECT @biz_charlie, 'Uri', 'CharlieUser', '(401) 555-7302', 'seed.charlie.employee.user@example.com', @charlie_user_id,
       '2025-02-14', 22.75, 'hourly', 22.75, CONCAT(@seed_tag, ' employee'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE user_id = @charlie_user_id);

INSERT INTO employees (
    business_id, first_name, last_name, phone, email, user_id,
    hire_date, wage_rate, wage_type, hourly_rate, note, active, created_at, updated_at
)
SELECT @biz_charlie, 'Parker', 'CharliePunch', '(401) 555-7303', 'seed.charlie.employee.punch@example.com', @charlie_punch_id,
       '2025-03-15', 20.25, 'hourly', 20.25, CONCAT(@seed_tag, ' employee'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE user_id = @charlie_punch_id);

SELECT @alpha_emp_mgr := id FROM employees WHERE user_id = @alpha_manager_id LIMIT 1;
SELECT @alpha_emp_user := id FROM employees WHERE user_id = @alpha_user_id LIMIT 1;
SELECT @alpha_emp_punch := id FROM employees WHERE user_id = @alpha_punch_id LIMIT 1;

SELECT @bravo_emp_mgr := id FROM employees WHERE user_id = @bravo_manager_id LIMIT 1;
SELECT @bravo_emp_user := id FROM employees WHERE user_id = @bravo_user_id LIMIT 1;
SELECT @bravo_emp_punch := id FROM employees WHERE user_id = @bravo_punch_id LIMIT 1;

SELECT @charlie_emp_mgr := id FROM employees WHERE user_id = @charlie_manager_id LIMIT 1;
SELECT @charlie_emp_user := id FROM employees WHERE user_id = @charlie_user_id LIMIT 1;
SELECT @charlie_emp_punch := id FROM employees WHERE user_id = @charlie_punch_id LIMIT 1;

-- ------------------------------------------------------------
-- Ops setup tables
-- ------------------------------------------------------------
INSERT INTO disposal_locations (
    business_id, name, type, note, address_1, city, state, zip, phone, email, active, created_at, updated_at
)
SELECT @biz_alpha, 'Seed Alpha Scrap Yard', 'scrap', CONCAT(@seed_tag, ' scrap yard'), '401 Metal Rd', 'Providence', 'RI', '02905', '(401) 555-8101', 'alpha.scrap@example.com', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM disposal_locations WHERE business_id = @biz_alpha AND name = 'Seed Alpha Scrap Yard');

INSERT INTO disposal_locations (
    business_id, name, type, note, address_1, city, state, zip, phone, email, active, created_at, updated_at
)
SELECT @biz_alpha, 'Seed Alpha Dump', 'dump', CONCAT(@seed_tag, ' dump'), '402 Landfill Ln', 'Cranston', 'RI', '02910', '(401) 555-8102', 'alpha.dump@example.com', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM disposal_locations WHERE business_id = @biz_alpha AND name = 'Seed Alpha Dump');

INSERT INTO disposal_locations (
    business_id, name, type, note, address_1, city, state, zip, phone, email, active, created_at, updated_at
)
SELECT @biz_bravo, 'Seed Bravo Scrap Yard', 'scrap', CONCAT(@seed_tag, ' scrap yard'), '501 Metal Rd', 'Warwick', 'RI', '02886', '(401) 555-8201', 'bravo.scrap@example.com', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM disposal_locations WHERE business_id = @biz_bravo AND name = 'Seed Bravo Scrap Yard');

INSERT INTO disposal_locations (
    business_id, name, type, note, address_1, city, state, zip, phone, email, active, created_at, updated_at
)
SELECT @biz_bravo, 'Seed Bravo Dump', 'dump', CONCAT(@seed_tag, ' dump'), '502 Landfill Ln', 'Johnston', 'RI', '02919', '(401) 555-8202', 'bravo.dump@example.com', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM disposal_locations WHERE business_id = @biz_bravo AND name = 'Seed Bravo Dump');

INSERT INTO disposal_locations (
    business_id, name, type, note, address_1, city, state, zip, phone, email, active, created_at, updated_at
)
SELECT @biz_charlie, 'Seed Charlie Scrap Yard', 'scrap', CONCAT(@seed_tag, ' scrap yard'), '601 Metal Rd', 'Pawtucket', 'RI', '02860', '(401) 555-8301', 'charlie.scrap@example.com', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM disposal_locations WHERE business_id = @biz_charlie AND name = 'Seed Charlie Scrap Yard');

INSERT INTO disposal_locations (
    business_id, name, type, note, address_1, city, state, zip, phone, email, active, created_at, updated_at
)
SELECT @biz_charlie, 'Seed Charlie Dump', 'dump', CONCAT(@seed_tag, ' dump'), '602 Landfill Ln', 'East Providence', 'RI', '02914', '(401) 555-8302', 'charlie.dump@example.com', 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM disposal_locations WHERE business_id = @biz_charlie AND name = 'Seed Charlie Dump');

SELECT @alpha_scrap_loc := id FROM disposal_locations WHERE business_id = @biz_alpha AND name = 'Seed Alpha Scrap Yard' ORDER BY id DESC LIMIT 1;
SELECT @alpha_dump_loc := id FROM disposal_locations WHERE business_id = @biz_alpha AND name = 'Seed Alpha Dump' ORDER BY id DESC LIMIT 1;
SELECT @bravo_scrap_loc := id FROM disposal_locations WHERE business_id = @biz_bravo AND name = 'Seed Bravo Scrap Yard' ORDER BY id DESC LIMIT 1;
SELECT @bravo_dump_loc := id FROM disposal_locations WHERE business_id = @biz_bravo AND name = 'Seed Bravo Dump' ORDER BY id DESC LIMIT 1;
SELECT @charlie_scrap_loc := id FROM disposal_locations WHERE business_id = @biz_charlie AND name = 'Seed Charlie Scrap Yard' ORDER BY id DESC LIMIT 1;
SELECT @charlie_dump_loc := id FROM disposal_locations WHERE business_id = @biz_charlie AND name = 'Seed Charlie Dump' ORDER BY id DESC LIMIT 1;

INSERT INTO expense_categories (business_id, name, note, active, created_at, updated_at)
SELECT @biz_alpha, 'Seed Alpha Fuel', CONCAT(@seed_tag, ' category'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE name = 'Seed Alpha Fuel');

INSERT INTO expense_categories (business_id, name, note, active, created_at, updated_at)
SELECT @biz_bravo, 'Seed Bravo Fees', CONCAT(@seed_tag, ' category'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE name = 'Seed Bravo Fees');

INSERT INTO expense_categories (business_id, name, note, active, created_at, updated_at)
SELECT @biz_charlie, 'Seed Charlie Supplies', CONCAT(@seed_tag, ' category'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE name = 'Seed Charlie Supplies');

SELECT @alpha_exp_cat := id FROM expense_categories WHERE name = 'Seed Alpha Fuel' LIMIT 1;
SELECT @bravo_exp_cat := id FROM expense_categories WHERE name = 'Seed Bravo Fees' LIMIT 1;
SELECT @charlie_exp_cat := id FROM expense_categories WHERE name = 'Seed Charlie Supplies' LIMIT 1;

INSERT INTO business_document_item_types (business_id, item_code, item_label, sort_order, is_active, created_at, updated_at)
SELECT @biz_alpha, 'ALPHA_LABOR', 'Labor', 10, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM business_document_item_types WHERE business_id = @biz_alpha AND item_code = 'ALPHA_LABOR');
INSERT INTO business_document_item_types (business_id, item_code, item_label, sort_order, is_active, created_at, updated_at)
SELECT @biz_alpha, 'ALPHA_DISPOSAL', 'Disposal', 20, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM business_document_item_types WHERE business_id = @biz_alpha AND item_code = 'ALPHA_DISPOSAL');

INSERT INTO business_document_item_types (business_id, item_code, item_label, sort_order, is_active, created_at, updated_at)
SELECT @biz_bravo, 'BRAVO_LABOR', 'Labor', 10, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM business_document_item_types WHERE business_id = @biz_bravo AND item_code = 'BRAVO_LABOR');
INSERT INTO business_document_item_types (business_id, item_code, item_label, sort_order, is_active, created_at, updated_at)
SELECT @biz_bravo, 'BRAVO_DISPOSAL', 'Disposal', 20, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM business_document_item_types WHERE business_id = @biz_bravo AND item_code = 'BRAVO_DISPOSAL');

INSERT INTO business_document_item_types (business_id, item_code, item_label, sort_order, is_active, created_at, updated_at)
SELECT @biz_charlie, 'CHARLIE_LABOR', 'Labor', 10, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM business_document_item_types WHERE business_id = @biz_charlie AND item_code = 'CHARLIE_LABOR');
INSERT INTO business_document_item_types (business_id, item_code, item_label, sort_order, is_active, created_at, updated_at)
SELECT @biz_charlie, 'CHARLIE_DISPOSAL', 'Disposal', 20, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM business_document_item_types WHERE business_id = @biz_charlie AND item_code = 'CHARLIE_DISPOSAL');

SELECT @alpha_item_labor := id FROM business_document_item_types WHERE business_id = @biz_alpha AND item_code = 'ALPHA_LABOR' LIMIT 1;
SELECT @bravo_item_labor := id FROM business_document_item_types WHERE business_id = @biz_bravo AND item_code = 'BRAVO_LABOR' LIMIT 1;
SELECT @charlie_item_labor := id FROM business_document_item_types WHERE business_id = @biz_charlie AND item_code = 'CHARLIE_LABOR' LIMIT 1;

-- ------------------------------------------------------------
-- Contacts + consignors
-- ------------------------------------------------------------
INSERT INTO contacts (
    business_id, contact_type, first_name, last_name, display_name, phone, email,
    city, state, company_id, linked_client_id, source_type, source_id, note,
    is_active, created_by, updated_by, created_at, updated_at
)
SELECT @biz_alpha, 'vendor', 'Victor', 'Vendor', 'Victor Vendor', '(401) 555-9101', 'seed.alpha.vendor@example.com',
       'Providence', 'RI', @alpha_company_1, @alpha_client_1, 'seed', @alpha_company_1, CONCAT(@seed_tag, ' network contact'),
       1, @alpha_admin_id, @alpha_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM contacts WHERE business_id = @biz_alpha AND email = 'seed.alpha.vendor@example.com');

INSERT INTO contacts (
    business_id, contact_type, first_name, last_name, display_name, phone, email,
    city, state, company_id, linked_client_id, source_type, source_id, note,
    is_active, created_by, updated_by, created_at, updated_at
)
SELECT @biz_bravo, 'specialist', 'Sam', 'Specialist', 'Sam Specialist', '(401) 555-9201', 'seed.bravo.specialist@example.com',
       'Warwick', 'RI', @bravo_company_1, @bravo_client_1, 'seed', @bravo_company_1, CONCAT(@seed_tag, ' network contact'),
       1, @bravo_admin_id, @bravo_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM contacts WHERE business_id = @biz_bravo AND email = 'seed.bravo.specialist@example.com');

INSERT INTO contacts (
    business_id, contact_type, first_name, last_name, display_name, phone, email,
    city, state, company_id, linked_client_id, source_type, source_id, note,
    is_active, created_by, updated_by, created_at, updated_at
)
SELECT @biz_charlie, 'buyer', 'Bria', 'Buyer', 'Bria Buyer', '(401) 555-9301', 'seed.charlie.buyer@example.com',
       'Pawtucket', 'RI', @charlie_company_1, @charlie_client_1, 'seed', @charlie_company_1, CONCAT(@seed_tag, ' network contact'),
       1, @charlie_admin_id, @charlie_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM contacts WHERE business_id = @biz_charlie AND email = 'seed.charlie.buyer@example.com');

INSERT INTO consignors (
    business_id, first_name, last_name, phone, email, city, state, zip,
    consignor_number, consignment_start_date, consignment_end_date,
    payment_schedule, next_payment_due_date,
    inventory_estimate_amount, inventory_description, note,
    active, created_by, updated_by, created_at, updated_at
)
SELECT @biz_alpha, 'Connie', 'Alpha', '(401) 555-9401', 'seed.alpha.consignor@example.com', 'Providence', 'RI', '02903',
       'SEED-ALPHA-001', '2026-01-01', '2026-12-31',
       'monthly', DATE_ADD(CURDATE(), INTERVAL 14 DAY),
       4200.00, 'Vintage tools and fixtures', CONCAT(@seed_tag, ' consignor'),
       1, @alpha_admin_id, @alpha_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM consignors WHERE consignor_number = 'SEED-ALPHA-001');

INSERT INTO consignors (
    business_id, first_name, last_name, phone, email, city, state, zip,
    consignor_number, consignment_start_date, consignment_end_date,
    payment_schedule, next_payment_due_date,
    inventory_estimate_amount, inventory_description, note,
    active, created_by, updated_by, created_at, updated_at
)
SELECT @biz_bravo, 'Connie', 'Bravo', '(401) 555-9402', 'seed.bravo.consignor@example.com', 'Warwick', 'RI', '02886',
       'SEED-BRAVO-001', '2026-01-01', '2026-12-31',
       'quarterly', DATE_ADD(CURDATE(), INTERVAL 21 DAY),
       5100.00, 'Furniture and collectibles', CONCAT(@seed_tag, ' consignor'),
       1, @bravo_admin_id, @bravo_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM consignors WHERE consignor_number = 'SEED-BRAVO-001');

INSERT INTO consignors (
    business_id, first_name, last_name, phone, email, city, state, zip,
    consignor_number, consignment_start_date, consignment_end_date,
    payment_schedule, next_payment_due_date,
    inventory_estimate_amount, inventory_description, note,
    active, created_by, updated_by, created_at, updated_at
)
SELECT @biz_charlie, 'Connie', 'Charlie', '(401) 555-9403', 'seed.charlie.consignor@example.com', 'Pawtucket', 'RI', '02860',
       'SEED-CHARLIE-001', '2026-01-01', '2026-12-31',
       'yearly', DATE_ADD(CURDATE(), INTERVAL 28 DAY),
       6100.00, 'Estate inventory rotation', CONCAT(@seed_tag, ' consignor'),
       1, @charlie_admin_id, @charlie_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM consignors WHERE consignor_number = 'SEED-CHARLIE-001');

SELECT @alpha_consignor := id FROM consignors WHERE consignor_number = 'SEED-ALPHA-001' LIMIT 1;
SELECT @bravo_consignor := id FROM consignors WHERE consignor_number = 'SEED-BRAVO-001' LIMIT 1;
SELECT @charlie_consignor := id FROM consignors WHERE consignor_number = 'SEED-CHARLIE-001' LIMIT 1;

INSERT INTO consignor_contacts (
    consignor_id, business_id, link_type, link_id, contact_method, direction, subject,
    notes, contacted_at, follow_up_at, active, created_by, updated_by, created_at, updated_at
)
SELECT @alpha_consignor, @biz_alpha, 'general', @alpha_consignor, 'call', 'outbound', 'Initial contract review',
       CONCAT(@seed_tag, ' consignor contact'), DATE_SUB(@seed_now, INTERVAL 3 DAY), DATE_ADD(@seed_now, INTERVAL 7 DAY), 1,
       @alpha_admin_id, @alpha_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM consignor_contacts
    WHERE consignor_id = @alpha_consignor AND subject = 'Initial contract review'
);

INSERT INTO consignor_contacts (
    consignor_id, business_id, link_type, link_id, contact_method, direction, subject,
    notes, contacted_at, follow_up_at, active, created_by, updated_by, created_at, updated_at
)
SELECT @bravo_consignor, @biz_bravo, 'general', @bravo_consignor, 'email', 'outbound', 'Quarterly payout prep',
       CONCAT(@seed_tag, ' consignor contact'), DATE_SUB(@seed_now, INTERVAL 2 DAY), DATE_ADD(@seed_now, INTERVAL 8 DAY), 1,
       @bravo_admin_id, @bravo_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM consignor_contacts
    WHERE consignor_id = @bravo_consignor AND subject = 'Quarterly payout prep'
);

INSERT INTO consignor_contacts (
    consignor_id, business_id, link_type, link_id, contact_method, direction, subject,
    notes, contacted_at, follow_up_at, active, created_by, updated_by, created_at, updated_at
)
SELECT @charlie_consignor, @biz_charlie, 'general', @charlie_consignor, 'text', 'outbound', 'Year-end inventory sync',
       CONCAT(@seed_tag, ' consignor contact'), DATE_SUB(@seed_now, INTERVAL 1 DAY), DATE_ADD(@seed_now, INTERVAL 9 DAY), 1,
       @charlie_admin_id, @charlie_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM consignor_contacts
    WHERE consignor_id = @charlie_consignor AND subject = 'Year-end inventory sync'
);

INSERT INTO consignor_contracts (
    consignor_id, business_id, contract_title, original_file_name, stored_file_name,
    storage_path, mime_type, file_size, contract_signed_at, expires_at,
    notes, active, created_by, updated_by, created_at, updated_at
)
SELECT @alpha_consignor, @biz_alpha, 'Seed Alpha Consignment Contract', 'seed_alpha_contract.pdf', 'seed_alpha_contract.pdf',
       '/storage/consignor_contracts/seed_alpha_contract.pdf', 'application/pdf', 118920,
       CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), CONCAT(@seed_tag, ' contract'), 1,
       @alpha_admin_id, @alpha_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM consignor_contracts
    WHERE consignor_id = @alpha_consignor AND contract_title = 'Seed Alpha Consignment Contract'
);

INSERT INTO consignor_contracts (
    consignor_id, business_id, contract_title, original_file_name, stored_file_name,
    storage_path, mime_type, file_size, contract_signed_at, expires_at,
    notes, active, created_by, updated_by, created_at, updated_at
)
SELECT @bravo_consignor, @biz_bravo, 'Seed Bravo Consignment Contract', 'seed_bravo_contract.pdf', 'seed_bravo_contract.pdf',
       '/storage/consignor_contracts/seed_bravo_contract.pdf', 'application/pdf', 124500,
       CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), CONCAT(@seed_tag, ' contract'), 1,
       @bravo_admin_id, @bravo_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM consignor_contracts
    WHERE consignor_id = @bravo_consignor AND contract_title = 'Seed Bravo Consignment Contract'
);

INSERT INTO consignor_contracts (
    consignor_id, business_id, contract_title, original_file_name, stored_file_name,
    storage_path, mime_type, file_size, contract_signed_at, expires_at,
    notes, active, created_by, updated_by, created_at, updated_at
)
SELECT @charlie_consignor, @biz_charlie, 'Seed Charlie Consignment Contract', 'seed_charlie_contract.pdf', 'seed_charlie_contract.pdf',
       '/storage/consignor_contracts/seed_charlie_contract.pdf', 'application/pdf', 132440,
       CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), CONCAT(@seed_tag, ' contract'), 1,
       @charlie_admin_id, @charlie_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM consignor_contracts
    WHERE consignor_id = @charlie_consignor AND contract_title = 'Seed Charlie Consignment Contract'
);

INSERT INTO consignor_payouts (
    consignor_id, business_id, payout_date, amount, estimate_amount, payout_method,
    reference_no, status, notes, active, created_by, updated_by, created_at, updated_at
)
SELECT @alpha_consignor, @biz_alpha, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 860.00, 900.00, 'ach',
       'SEED-ALPHA-PAY-1', 'paid', CONCAT(@seed_tag, ' payout'), 1,
       @alpha_admin_id, @alpha_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM consignor_payouts
    WHERE consignor_id = @alpha_consignor AND reference_no = 'SEED-ALPHA-PAY-1'
);

INSERT INTO consignor_payouts (
    consignor_id, business_id, payout_date, amount, estimate_amount, payout_method,
    reference_no, status, notes, active, created_by, updated_by, created_at, updated_at
)
SELECT @bravo_consignor, @biz_bravo, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 1120.00, 1150.00, 'check',
       'SEED-BRAVO-PAY-1', 'paid', CONCAT(@seed_tag, ' payout'), 1,
       @bravo_admin_id, @bravo_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM consignor_payouts
    WHERE consignor_id = @bravo_consignor AND reference_no = 'SEED-BRAVO-PAY-1'
);

INSERT INTO consignor_payouts (
    consignor_id, business_id, payout_date, amount, estimate_amount, payout_method,
    reference_no, status, notes, active, created_by, updated_by, created_at, updated_at
)
SELECT @charlie_consignor, @biz_charlie, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 980.00, 1000.00, 'other',
       'SEED-CHARLIE-PAY-1', 'paid', CONCAT(@seed_tag, ' payout'), 1,
       @charlie_admin_id, @charlie_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM consignor_payouts
    WHERE consignor_id = @charlie_consignor AND reference_no = 'SEED-CHARLIE-PAY-1'
);

-- ------------------------------------------------------------
-- Prospects + jobs + schedules
-- ------------------------------------------------------------
INSERT INTO prospects (
    business_id, client_id, contacted_on, follow_up_on, status, priority_rating,
    next_step, note, active, created_at, created_by, updated_at, updated_by
)
SELECT @biz_alpha, @alpha_client_2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY),
       'active', 2, 'send quote', CONCAT(@seed_tag, ' prospect'), 1,
       @seed_now, @alpha_manager_id, @seed_now, @alpha_manager_id
WHERE NOT EXISTS (
    SELECT 1 FROM prospects WHERE business_id = @biz_alpha AND client_id = @alpha_client_2 AND next_step = 'send quote'
);

INSERT INTO prospects (
    business_id, client_id, contacted_on, follow_up_on, status, priority_rating,
    next_step, note, active, created_at, created_by, updated_at, updated_by
)
SELECT @biz_bravo, @bravo_client_2, DATE_SUB(CURDATE(), INTERVAL 6 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY),
       'active', 1, 'call', CONCAT(@seed_tag, ' prospect'), 1,
       @seed_now, @bravo_manager_id, @seed_now, @bravo_manager_id
WHERE NOT EXISTS (
    SELECT 1 FROM prospects WHERE business_id = @biz_bravo AND client_id = @bravo_client_2 AND next_step = 'call'
);

INSERT INTO prospects (
    business_id, client_id, contacted_on, follow_up_on, status, priority_rating,
    next_step, note, active, created_at, created_by, updated_at, updated_by
)
SELECT @biz_charlie, @charlie_client_2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY),
       'active', 3, 'make appointment', CONCAT(@seed_tag, ' prospect'), 1,
       @seed_now, @charlie_manager_id, @seed_now, @charlie_manager_id
WHERE NOT EXISTS (
    SELECT 1 FROM prospects WHERE business_id = @biz_charlie AND client_id = @charlie_client_2 AND next_step = 'make appointment'
);

INSERT INTO jobs (
    business_id, client_id, estate_id, job_owner_type, job_owner_id, contact_client_id,
    name, note, address_1, city, state, zip, phone, can_text, email,
    quote_date, scheduled_date, start_date, end_date,
    billed_date, paid_date, paid, total_quote, total_billed, job_status,
    active, created_at, created_by, updated_at,
    bill_to_name, bill_to_phone, bill_to_email, bill_to_address_1, bill_to_city, bill_to_state, bill_to_zip
)
SELECT
    @biz_alpha, @alpha_client_1, @alpha_estate_id, 'client', @alpha_client_1, @alpha_client_1,
    'Seed Alpha Pending Job', CONCAT(@seed_tag, ' pending job'), '11 Oak St', 'Providence', 'RI', '02903', '(401) 555-5101', 1, 'seed.alpha.client1@example.com',
    DATE_SUB(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_now, INTERVAL 2 DAY), NULL, NULL,
    NULL, NULL, 0, 1650.00, NULL, 'pending',
    1, @seed_now, @alpha_manager_id, @seed_now,
    'Ava Anderson', '(401) 555-5101', 'seed.alpha.client1@example.com', '11 Oak St', 'Providence', 'RI', '02903'
WHERE NOT EXISTS (SELECT 1 FROM jobs WHERE business_id = @biz_alpha AND name = 'Seed Alpha Pending Job');

INSERT INTO jobs (
    business_id, client_id, estate_id, job_owner_type, job_owner_id, contact_client_id,
    name, note, address_1, city, state, zip, phone, can_text, email,
    quote_date, scheduled_date, start_date, end_date,
    billed_date, paid_date, paid, total_quote, total_billed, job_status,
    active, created_at, created_by, updated_at,
    bill_to_name, bill_to_phone, bill_to_email, bill_to_address_1, bill_to_city, bill_to_state, bill_to_zip
)
SELECT
    @biz_alpha, @alpha_client_1, @alpha_estate_id, 'client', @alpha_client_1, @alpha_client_1,
    'Seed Alpha Active Job', CONCAT(@seed_tag, ' active job'), '15 Oak St', 'Providence', 'RI', '02904', '(401) 555-5101', 1, 'seed.alpha.client1@example.com',
    DATE_SUB(@seed_now, INTERVAL 10 DAY), DATE_SUB(@seed_now, INTERVAL 1 DAY), DATE_SUB(@seed_now, INTERVAL 1 DAY), NULL,
    NULL, NULL, 0, 2450.00, 2200.00, 'active',
    1, @seed_now, @alpha_manager_id, @seed_now,
    'Ava Anderson', '(401) 555-5101', 'seed.alpha.client1@example.com', '15 Oak St', 'Providence', 'RI', '02904'
WHERE NOT EXISTS (SELECT 1 FROM jobs WHERE business_id = @biz_alpha AND name = 'Seed Alpha Active Job');

INSERT INTO jobs (
    business_id, client_id, estate_id, job_owner_type, job_owner_id, contact_client_id,
    name, note, address_1, city, state, zip, phone, can_text, email,
    quote_date, scheduled_date, start_date, end_date,
    billed_date, paid_date, paid, total_quote, total_billed, job_status,
    active, created_at, created_by, updated_at,
    bill_to_name, bill_to_phone, bill_to_email, bill_to_address_1, bill_to_city, bill_to_state, bill_to_zip
)
SELECT
    @biz_alpha, @alpha_client_3, NULL, 'client', @alpha_client_3, @alpha_client_3,
    'Seed Alpha Complete Job', CONCAT(@seed_tag, ' complete job'), '17 Oak St', 'Cranston', 'RI', '02910', '(401) 555-5103', 0, 'seed.alpha.client3@example.com',
    DATE_SUB(@seed_now, INTERVAL 20 DAY), DATE_SUB(@seed_now, INTERVAL 16 DAY), DATE_SUB(@seed_now, INTERVAL 16 DAY), DATE_SUB(@seed_now, INTERVAL 15 DAY),
    DATE_SUB(@seed_now, INTERVAL 14 DAY), DATE_SUB(@seed_now, INTERVAL 12 DAY), 1, 1980.00, 1980.00, 'complete',
    1, @seed_now, @alpha_manager_id, @seed_now,
    'Otto Other', '(401) 555-5103', 'seed.alpha.client3@example.com', '17 Oak St', 'Cranston', 'RI', '02910'
WHERE NOT EXISTS (SELECT 1 FROM jobs WHERE business_id = @biz_alpha AND name = 'Seed Alpha Complete Job');

INSERT INTO jobs (
    business_id, client_id, estate_id, job_owner_type, job_owner_id, contact_client_id,
    name, note, address_1, city, state, zip, phone, can_text, email,
    quote_date, scheduled_date, start_date, end_date,
    billed_date, paid_date, paid, total_quote, total_billed, job_status,
    active, created_at, created_by, updated_at,
    bill_to_name, bill_to_phone, bill_to_email, bill_to_address_1, bill_to_city, bill_to_state, bill_to_zip
)
SELECT
    @biz_bravo, @bravo_client_1, @bravo_estate_id, 'client', @bravo_client_1, @bravo_client_1,
    'Seed Bravo Pending Job', CONCAT(@seed_tag, ' pending job'), '21 Main St', 'Warwick', 'RI', '02886', '(401) 555-5201', 1, 'seed.bravo.client1@example.com',
    DATE_SUB(@seed_now, INTERVAL 9 DAY), DATE_ADD(@seed_now, INTERVAL 1 DAY), NULL, NULL,
    NULL, NULL, 0, 1800.00, NULL, 'pending',
    1, @seed_now, @bravo_manager_id, @seed_now,
    'Bianca Baker', '(401) 555-5201', 'seed.bravo.client1@example.com', '21 Main St', 'Warwick', 'RI', '02886'
WHERE NOT EXISTS (SELECT 1 FROM jobs WHERE business_id = @biz_bravo AND name = 'Seed Bravo Pending Job');

INSERT INTO jobs (
    business_id, client_id, estate_id, job_owner_type, job_owner_id, contact_client_id,
    name, note, address_1, city, state, zip, phone, can_text, email,
    quote_date, scheduled_date, start_date, end_date,
    billed_date, paid_date, paid, total_quote, total_billed, job_status,
    active, created_at, created_by, updated_at,
    bill_to_name, bill_to_phone, bill_to_email, bill_to_address_1, bill_to_city, bill_to_state, bill_to_zip
)
SELECT
    @biz_bravo, @bravo_client_1, NULL, 'client', @bravo_client_1, @bravo_client_1,
    'Seed Bravo Active Job', CONCAT(@seed_tag, ' active job'), '24 Main St', 'Warwick', 'RI', '02888', '(401) 555-5201', 1, 'seed.bravo.client1@example.com',
    DATE_SUB(@seed_now, INTERVAL 11 DAY), DATE_SUB(@seed_now, INTERVAL 2 DAY), DATE_SUB(@seed_now, INTERVAL 2 DAY), NULL,
    NULL, NULL, 0, 2600.00, 1500.00, 'active',
    1, @seed_now, @bravo_manager_id, @seed_now,
    'Bianca Baker', '(401) 555-5201', 'seed.bravo.client1@example.com', '24 Main St', 'Warwick', 'RI', '02888'
WHERE NOT EXISTS (SELECT 1 FROM jobs WHERE business_id = @biz_bravo AND name = 'Seed Bravo Active Job');

INSERT INTO jobs (
    business_id, client_id, estate_id, job_owner_type, job_owner_id, contact_client_id,
    name, note, address_1, city, state, zip, phone, can_text, email,
    quote_date, scheduled_date, start_date, end_date,
    billed_date, paid_date, paid, total_quote, total_billed, job_status,
    active, created_at, created_by, updated_at,
    bill_to_name, bill_to_phone, bill_to_email, bill_to_address_1, bill_to_city, bill_to_state, bill_to_zip
)
SELECT
    @biz_bravo, @bravo_client_3, NULL, 'client', @bravo_client_3, @bravo_client_3,
    'Seed Bravo Complete Job', CONCAT(@seed_tag, ' complete job'), '27 Main St', 'Johnston', 'RI', '02919', '(401) 555-5203', 0, 'seed.bravo.client3@example.com',
    DATE_SUB(@seed_now, INTERVAL 22 DAY), DATE_SUB(@seed_now, INTERVAL 18 DAY), DATE_SUB(@seed_now, INTERVAL 18 DAY), DATE_SUB(@seed_now, INTERVAL 17 DAY),
    DATE_SUB(@seed_now, INTERVAL 16 DAY), DATE_SUB(@seed_now, INTERVAL 14 DAY), 1, 2100.00, 2100.00, 'complete',
    1, @seed_now, @bravo_manager_id, @seed_now,
    'Oscar Other', '(401) 555-5203', 'seed.bravo.client3@example.com', '27 Main St', 'Johnston', 'RI', '02919'
WHERE NOT EXISTS (SELECT 1 FROM jobs WHERE business_id = @biz_bravo AND name = 'Seed Bravo Complete Job');

INSERT INTO jobs (
    business_id, client_id, estate_id, job_owner_type, job_owner_id, contact_client_id,
    name, note, address_1, city, state, zip, phone, can_text, email,
    quote_date, scheduled_date, start_date, end_date,
    billed_date, paid_date, paid, total_quote, total_billed, job_status,
    active, created_at, created_by, updated_at,
    bill_to_name, bill_to_phone, bill_to_email, bill_to_address_1, bill_to_city, bill_to_state, bill_to_zip
)
SELECT
    @biz_charlie, @charlie_client_1, @charlie_estate_id, 'client', @charlie_client_1, @charlie_client_1,
    'Seed Charlie Pending Job', CONCAT(@seed_tag, ' pending job'), '31 Park Ave', 'Pawtucket', 'RI', '02860', '(401) 555-5301', 1, 'seed.charlie.client1@example.com',
    DATE_SUB(@seed_now, INTERVAL 8 DAY), DATE_ADD(@seed_now, INTERVAL 3 DAY), NULL, NULL,
    NULL, NULL, 0, 1750.00, NULL, 'pending',
    1, @seed_now, @charlie_manager_id, @seed_now,
    'Chloe Carter', '(401) 555-5301', 'seed.charlie.client1@example.com', '31 Park Ave', 'Pawtucket', 'RI', '02860'
WHERE NOT EXISTS (SELECT 1 FROM jobs WHERE business_id = @biz_charlie AND name = 'Seed Charlie Pending Job');

INSERT INTO jobs (
    business_id, client_id, estate_id, job_owner_type, job_owner_id, contact_client_id,
    name, note, address_1, city, state, zip, phone, can_text, email,
    quote_date, scheduled_date, start_date, end_date,
    billed_date, paid_date, paid, total_quote, total_billed, job_status,
    active, created_at, created_by, updated_at,
    bill_to_name, bill_to_phone, bill_to_email, bill_to_address_1, bill_to_city, bill_to_state, bill_to_zip
)
SELECT
    @biz_charlie, @charlie_client_1, NULL, 'client', @charlie_client_1, @charlie_client_1,
    'Seed Charlie Active Job', CONCAT(@seed_tag, ' active job'), '34 Park Ave', 'Pawtucket', 'RI', '02861', '(401) 555-5301', 1, 'seed.charlie.client1@example.com',
    DATE_SUB(@seed_now, INTERVAL 12 DAY), DATE_SUB(@seed_now, INTERVAL 1 DAY), DATE_SUB(@seed_now, INTERVAL 1 DAY), NULL,
    NULL, NULL, 0, 2700.00, 1600.00, 'active',
    1, @seed_now, @charlie_manager_id, @seed_now,
    'Chloe Carter', '(401) 555-5301', 'seed.charlie.client1@example.com', '34 Park Ave', 'Pawtucket', 'RI', '02861'
WHERE NOT EXISTS (SELECT 1 FROM jobs WHERE business_id = @biz_charlie AND name = 'Seed Charlie Active Job');

INSERT INTO jobs (
    business_id, client_id, estate_id, job_owner_type, job_owner_id, contact_client_id,
    name, note, address_1, city, state, zip, phone, can_text, email,
    quote_date, scheduled_date, start_date, end_date,
    billed_date, paid_date, paid, total_quote, total_billed, job_status,
    active, created_at, created_by, updated_at,
    bill_to_name, bill_to_phone, bill_to_email, bill_to_address_1, bill_to_city, bill_to_state, bill_to_zip
)
SELECT
    @biz_charlie, @charlie_client_3, NULL, 'client', @charlie_client_3, @charlie_client_3,
    'Seed Charlie Complete Job', CONCAT(@seed_tag, ' complete job'), '37 Park Ave', 'East Providence', 'RI', '02914', '(401) 555-5303', 0, 'seed.charlie.client3@example.com',
    DATE_SUB(@seed_now, INTERVAL 24 DAY), DATE_SUB(@seed_now, INTERVAL 20 DAY), DATE_SUB(@seed_now, INTERVAL 20 DAY), DATE_SUB(@seed_now, INTERVAL 19 DAY),
    DATE_SUB(@seed_now, INTERVAL 18 DAY), DATE_SUB(@seed_now, INTERVAL 16 DAY), 1, 2250.00, 2250.00, 'complete',
    1, @seed_now, @charlie_manager_id, @seed_now,
    'Owen Other', '(401) 555-5303', 'seed.charlie.client3@example.com', '37 Park Ave', 'East Providence', 'RI', '02914'
WHERE NOT EXISTS (SELECT 1 FROM jobs WHERE business_id = @biz_charlie AND name = 'Seed Charlie Complete Job');

SELECT @alpha_job_pending := id FROM jobs WHERE business_id = @biz_alpha AND name = 'Seed Alpha Pending Job' ORDER BY id DESC LIMIT 1;
SELECT @alpha_job_active := id FROM jobs WHERE business_id = @biz_alpha AND name = 'Seed Alpha Active Job' ORDER BY id DESC LIMIT 1;
SELECT @alpha_job_complete := id FROM jobs WHERE business_id = @biz_alpha AND name = 'Seed Alpha Complete Job' ORDER BY id DESC LIMIT 1;

SELECT @bravo_job_pending := id FROM jobs WHERE business_id = @biz_bravo AND name = 'Seed Bravo Pending Job' ORDER BY id DESC LIMIT 1;
SELECT @bravo_job_active := id FROM jobs WHERE business_id = @biz_bravo AND name = 'Seed Bravo Active Job' ORDER BY id DESC LIMIT 1;
SELECT @bravo_job_complete := id FROM jobs WHERE business_id = @biz_bravo AND name = 'Seed Bravo Complete Job' ORDER BY id DESC LIMIT 1;

SELECT @charlie_job_pending := id FROM jobs WHERE business_id = @biz_charlie AND name = 'Seed Charlie Pending Job' ORDER BY id DESC LIMIT 1;
SELECT @charlie_job_active := id FROM jobs WHERE business_id = @biz_charlie AND name = 'Seed Charlie Active Job' ORDER BY id DESC LIMIT 1;
SELECT @charlie_job_complete := id FROM jobs WHERE business_id = @biz_charlie AND name = 'Seed Charlie Complete Job' ORDER BY id DESC LIMIT 1;

INSERT INTO job_schedule_windows (job_id, scheduled_start_at, scheduled_end_at, updated_by, updated_at)
SELECT @alpha_job_pending, DATE_ADD(@seed_now, INTERVAL 2 DAY), DATE_ADD(DATE_ADD(@seed_now, INTERVAL 2 DAY), INTERVAL 4 HOUR), @alpha_manager_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_schedule_windows WHERE job_id = @alpha_job_pending);

INSERT INTO job_schedule_windows (job_id, scheduled_start_at, scheduled_end_at, updated_by, updated_at)
SELECT @alpha_job_active, DATE_SUB(@seed_now, INTERVAL 1 DAY), DATE_ADD(@seed_now, INTERVAL 1 DAY), @alpha_manager_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_schedule_windows WHERE job_id = @alpha_job_active);

INSERT INTO job_schedule_windows (job_id, scheduled_start_at, scheduled_end_at, updated_by, updated_at)
SELECT @bravo_job_pending, DATE_ADD(@seed_now, INTERVAL 1 DAY), DATE_ADD(DATE_ADD(@seed_now, INTERVAL 1 DAY), INTERVAL 3 HOUR), @bravo_manager_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_schedule_windows WHERE job_id = @bravo_job_pending);

INSERT INTO job_schedule_windows (job_id, scheduled_start_at, scheduled_end_at, updated_by, updated_at)
SELECT @bravo_job_active, DATE_SUB(@seed_now, INTERVAL 2 DAY), DATE_ADD(@seed_now, INTERVAL 2 HOUR), @bravo_manager_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_schedule_windows WHERE job_id = @bravo_job_active);

INSERT INTO job_schedule_windows (job_id, scheduled_start_at, scheduled_end_at, updated_by, updated_at)
SELECT @charlie_job_pending, DATE_ADD(@seed_now, INTERVAL 3 DAY), DATE_ADD(DATE_ADD(@seed_now, INTERVAL 3 DAY), INTERVAL 5 HOUR), @charlie_manager_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_schedule_windows WHERE job_id = @charlie_job_pending);

INSERT INTO job_schedule_windows (job_id, scheduled_start_at, scheduled_end_at, updated_by, updated_at)
SELECT @charlie_job_active, DATE_SUB(@seed_now, INTERVAL 1 DAY), DATE_ADD(@seed_now, INTERVAL 5 HOUR), @charlie_manager_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_schedule_windows WHERE job_id = @charlie_job_active);

INSERT INTO job_crew (job_id, employee_id, active, created_at, updated_at)
SELECT @alpha_job_active, @alpha_emp_mgr, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_crew WHERE job_id = @alpha_job_active AND employee_id = @alpha_emp_mgr);
INSERT INTO job_crew (job_id, employee_id, active, created_at, updated_at)
SELECT @alpha_job_active, @alpha_emp_user, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_crew WHERE job_id = @alpha_job_active AND employee_id = @alpha_emp_user);

INSERT INTO job_crew (job_id, employee_id, active, created_at, updated_at)
SELECT @bravo_job_active, @bravo_emp_mgr, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_crew WHERE job_id = @bravo_job_active AND employee_id = @bravo_emp_mgr);
INSERT INTO job_crew (job_id, employee_id, active, created_at, updated_at)
SELECT @bravo_job_active, @bravo_emp_user, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_crew WHERE job_id = @bravo_job_active AND employee_id = @bravo_emp_user);

INSERT INTO job_crew (job_id, employee_id, active, created_at, updated_at)
SELECT @charlie_job_active, @charlie_emp_mgr, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_crew WHERE job_id = @charlie_job_active AND employee_id = @charlie_emp_mgr);
INSERT INTO job_crew (job_id, employee_id, active, created_at, updated_at)
SELECT @charlie_job_active, @charlie_emp_user, 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_crew WHERE job_id = @charlie_job_active AND employee_id = @charlie_emp_user);

-- ------------------------------------------------------------
-- Time entries, payments, expenses, disposals, sales
-- ------------------------------------------------------------
INSERT INTO employee_time_entries (
    business_id, employee_id, job_id, work_date, start_time, end_time,
    minutes_worked, pay_rate, total_paid, note, active, created_at, updated_at,
    punch_in_lat, punch_in_lng, punch_in_accuracy_m, punch_in_source, punch_in_captured_at,
    punch_out_lat, punch_out_lng, punch_out_accuracy_m, punch_out_source, punch_out_captured_at
)
SELECT
    @biz_alpha, @alpha_emp_mgr, @alpha_job_complete, DATE_SUB(CURDATE(), INTERVAL 16 DAY), '08:00:00', '12:00:00',
    240, 28.50, 114.00, CONCAT(@seed_tag, ' closed time'), 1, @seed_now, @seed_now,
    41.8240, -71.4128, 25.0, 'browser', DATE_SUB(@seed_now, INTERVAL 16 DAY),
    41.8250, -71.4132, 30.0, 'browser', DATE_ADD(DATE_SUB(@seed_now, INTERVAL 16 DAY), INTERVAL 4 HOUR)
WHERE NOT EXISTS (
    SELECT 1 FROM employee_time_entries
    WHERE business_id = @biz_alpha AND employee_id = @alpha_emp_mgr AND job_id = @alpha_job_complete AND work_date = DATE_SUB(CURDATE(), INTERVAL 16 DAY)
);

INSERT INTO employee_time_entries (
    business_id, employee_id, job_id, work_date, start_time, end_time,
    minutes_worked, pay_rate, total_paid, note, active, created_at, updated_at,
    punch_in_lat, punch_in_lng, punch_in_accuracy_m, punch_in_source, punch_in_captured_at
)
SELECT
    @biz_alpha, @alpha_emp_user, @alpha_job_active, CURDATE(), '08:30:00', NULL,
    NULL, 24.00, NULL, CONCAT(@seed_tag, ' open time'), 1, @seed_now, @seed_now,
    41.8261, -71.4139, 18.0, 'browser', @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM employee_time_entries
    WHERE business_id = @biz_alpha AND employee_id = @alpha_emp_user AND job_id = @alpha_job_active AND work_date = CURDATE() AND end_time IS NULL
);

INSERT INTO employee_time_entries (
    business_id, employee_id, job_id, work_date, start_time, end_time,
    minutes_worked, pay_rate, total_paid, note, active, created_at, updated_at,
    punch_in_lat, punch_in_lng, punch_in_accuracy_m, punch_in_source, punch_in_captured_at,
    punch_out_lat, punch_out_lng, punch_out_accuracy_m, punch_out_source, punch_out_captured_at
)
SELECT
    @biz_bravo, @bravo_emp_mgr, @bravo_job_complete, DATE_SUB(CURDATE(), INTERVAL 18 DAY), '09:00:00', '13:30:00',
    270, 27.00, 121.50, CONCAT(@seed_tag, ' closed time'), 1, @seed_now, @seed_now,
    41.7000, -71.4500, 22.0, 'browser', DATE_SUB(@seed_now, INTERVAL 18 DAY),
    41.7003, -71.4504, 20.0, 'browser', DATE_ADD(DATE_SUB(@seed_now, INTERVAL 18 DAY), INTERVAL 4 HOUR)
WHERE NOT EXISTS (
    SELECT 1 FROM employee_time_entries
    WHERE business_id = @biz_bravo AND employee_id = @bravo_emp_mgr AND job_id = @bravo_job_complete AND work_date = DATE_SUB(CURDATE(), INTERVAL 18 DAY)
);

INSERT INTO employee_time_entries (
    business_id, employee_id, job_id, work_date, start_time, end_time,
    minutes_worked, pay_rate, total_paid, note, active, created_at, updated_at,
    punch_in_lat, punch_in_lng, punch_in_accuracy_m, punch_in_source, punch_in_captured_at
)
SELECT
    @biz_bravo, @bravo_emp_user, @bravo_job_active, CURDATE(), '09:15:00', NULL,
    NULL, 23.25, NULL, CONCAT(@seed_tag, ' open time'), 1, @seed_now, @seed_now,
    41.7010, -71.4510, 12.0, 'browser', @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM employee_time_entries
    WHERE business_id = @biz_bravo AND employee_id = @bravo_emp_user AND job_id = @bravo_job_active AND work_date = CURDATE() AND end_time IS NULL
);

INSERT INTO employee_time_entries (
    business_id, employee_id, job_id, work_date, start_time, end_time,
    minutes_worked, pay_rate, total_paid, note, active, created_at, updated_at,
    punch_in_lat, punch_in_lng, punch_in_accuracy_m, punch_in_source, punch_in_captured_at,
    punch_out_lat, punch_out_lng, punch_out_accuracy_m, punch_out_source, punch_out_captured_at
)
SELECT
    @biz_charlie, @charlie_emp_mgr, @charlie_job_complete, DATE_SUB(CURDATE(), INTERVAL 20 DAY), '07:45:00', '12:45:00',
    300, 26.75, 133.75, CONCAT(@seed_tag, ' closed time'), 1, @seed_now, @seed_now,
    41.8780, -71.3820, 24.0, 'browser', DATE_SUB(@seed_now, INTERVAL 20 DAY),
    41.8785, -71.3822, 22.0, 'browser', DATE_ADD(DATE_SUB(@seed_now, INTERVAL 20 DAY), INTERVAL 5 HOUR)
WHERE NOT EXISTS (
    SELECT 1 FROM employee_time_entries
    WHERE business_id = @biz_charlie AND employee_id = @charlie_emp_mgr AND job_id = @charlie_job_complete AND work_date = DATE_SUB(CURDATE(), INTERVAL 20 DAY)
);

INSERT INTO employee_time_entries (
    business_id, employee_id, job_id, work_date, start_time, end_time,
    minutes_worked, pay_rate, total_paid, note, active, created_at, updated_at,
    punch_in_lat, punch_in_lng, punch_in_accuracy_m, punch_in_source, punch_in_captured_at
)
SELECT
    @biz_charlie, @charlie_emp_user, @charlie_job_active, CURDATE(), '08:45:00', NULL,
    NULL, 22.75, NULL, CONCAT(@seed_tag, ' open time'), 1, @seed_now, @seed_now,
    41.8790, -71.3830, 16.0, 'browser', @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM employee_time_entries
    WHERE business_id = @biz_charlie AND employee_id = @charlie_emp_user AND job_id = @charlie_job_active AND work_date = CURDATE() AND end_time IS NULL
);

INSERT INTO expenses (
    business_id, job_id, disposal_location_id, expense_category_id,
    category, description, amount, expense_date, is_active, created_at, updated_at
)
SELECT @biz_alpha, @alpha_job_complete, @alpha_dump_loc, @alpha_exp_cat,
       'Fuel', CONCAT(@seed_tag, ' truck fuel'), 145.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 1, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM expenses
    WHERE business_id = @biz_alpha AND job_id = @alpha_job_complete AND amount = 145.00 AND expense_date = DATE_SUB(CURDATE(), INTERVAL 15 DAY)
);

INSERT INTO expenses (
    business_id, job_id, disposal_location_id, expense_category_id,
    category, description, amount, expense_date, is_active, created_at, updated_at
)
SELECT @biz_bravo, @bravo_job_complete, @bravo_dump_loc, @bravo_exp_cat,
       'Fees', CONCAT(@seed_tag, ' dump fee'), 210.00, DATE_SUB(CURDATE(), INTERVAL 17 DAY), 1, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM expenses
    WHERE business_id = @biz_bravo AND job_id = @bravo_job_complete AND amount = 210.00 AND expense_date = DATE_SUB(CURDATE(), INTERVAL 17 DAY)
);

INSERT INTO expenses (
    business_id, job_id, disposal_location_id, expense_category_id,
    category, description, amount, expense_date, is_active, created_at, updated_at
)
SELECT @biz_charlie, @charlie_job_complete, @charlie_dump_loc, @charlie_exp_cat,
       'Supplies', CONCAT(@seed_tag, ' packing supplies'), 180.00, DATE_SUB(CURDATE(), INTERVAL 19 DAY), 1, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM expenses
    WHERE business_id = @biz_charlie AND job_id = @charlie_job_complete AND amount = 180.00 AND expense_date = DATE_SUB(CURDATE(), INTERVAL 19 DAY)
);

INSERT INTO job_disposal_events (
    job_id, disposal_location_id, event_date, type, amount, note, active, created_at, updated_at
)
SELECT @alpha_job_complete, @alpha_dump_loc, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'dump', 85.00, CONCAT(@seed_tag, ' disposal event'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_disposal_events WHERE job_id = @alpha_job_complete AND event_date = DATE_SUB(CURDATE(), INTERVAL 15 DAY));

INSERT INTO job_disposal_events (
    job_id, disposal_location_id, event_date, type, amount, note, active, created_at, updated_at
)
SELECT @bravo_job_complete, @bravo_dump_loc, DATE_SUB(CURDATE(), INTERVAL 17 DAY), 'dump', 120.00, CONCAT(@seed_tag, ' disposal event'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_disposal_events WHERE job_id = @bravo_job_complete AND event_date = DATE_SUB(CURDATE(), INTERVAL 17 DAY));

INSERT INTO job_disposal_events (
    job_id, disposal_location_id, event_date, type, amount, note, active, created_at, updated_at
)
SELECT @charlie_job_complete, @charlie_dump_loc, DATE_SUB(CURDATE(), INTERVAL 19 DAY), 'dump', 95.00, CONCAT(@seed_tag, ' disposal event'), 1, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_disposal_events WHERE job_id = @charlie_job_complete AND event_date = DATE_SUB(CURDATE(), INTERVAL 19 DAY));

INSERT INTO sales (
    business_id, job_id, type, name, note, start_date, end_date,
    gross_amount, net_amount, disposal_location_id,
    active, created_at, created_by, updated_at
)
SELECT @biz_alpha, @alpha_job_complete, 'scrap', 'Seed Alpha Scrap Run', CONCAT(@seed_tag, ' sale'), DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_SUB(CURDATE(), INTERVAL 14 DAY),
       420.00, 390.00, @alpha_scrap_loc,
       1, @seed_now, @alpha_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM sales WHERE business_id = @biz_alpha AND name = 'Seed Alpha Scrap Run');

INSERT INTO sales (
    business_id, job_id, type, name, note, start_date, end_date,
    gross_amount, net_amount, disposal_location_id,
    active, created_at, created_by, updated_at
)
SELECT @biz_bravo, @bravo_job_complete, 'shop', 'Seed Bravo Shop Sale', CONCAT(@seed_tag, ' sale'), DATE_SUB(CURDATE(), INTERVAL 13 DAY), DATE_SUB(CURDATE(), INTERVAL 13 DAY),
       560.00, 560.00, NULL,
       1, @seed_now, @bravo_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM sales WHERE business_id = @biz_bravo AND name = 'Seed Bravo Shop Sale');

INSERT INTO sales (
    business_id, job_id, type, name, note, start_date, end_date,
    gross_amount, net_amount, disposal_location_id,
    active, created_at, created_by, updated_at
)
SELECT @biz_charlie, @charlie_job_complete, 'scrap', 'Seed Charlie Scrap Run', CONCAT(@seed_tag, ' sale'), DATE_SUB(CURDATE(), INTERVAL 12 DAY), DATE_SUB(CURDATE(), INTERVAL 12 DAY),
       610.00, 575.00, @charlie_scrap_loc,
       1, @seed_now, @charlie_admin_id, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM sales WHERE business_id = @biz_charlie AND name = 'Seed Charlie Scrap Run');

INSERT INTO job_payments (job_id, payment_date, amount, method, note, created_at, updated_at)
SELECT @alpha_job_complete, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 1980.00, 'card', CONCAT(@seed_tag, ' full payment'), @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_payments WHERE job_id = @alpha_job_complete AND amount = 1980.00);

INSERT INTO job_payments (job_id, payment_date, amount, method, note, created_at, updated_at)
SELECT @bravo_job_complete, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 2100.00, 'cash', CONCAT(@seed_tag, ' full payment'), @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_payments WHERE job_id = @bravo_job_complete AND amount = 2100.00);

INSERT INTO job_payments (job_id, payment_date, amount, method, note, created_at, updated_at)
SELECT @charlie_job_complete, DATE_SUB(CURDATE(), INTERVAL 16 DAY), 2250.00, 'check', CONCAT(@seed_tag, ' full payment'), @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM job_payments WHERE job_id = @charlie_job_complete AND amount = 2250.00);

INSERT INTO job_actions (business_id, job_id, action_type, action_at, amount, ref_table, ref_id, note, created_at)
SELECT @biz_alpha, @alpha_job_complete, 'payment_added', DATE_SUB(@seed_now, INTERVAL 12 DAY), 1980.00, 'job_payments', NULL, CONCAT(@seed_tag, ' payment logged'), @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM job_actions WHERE business_id = @biz_alpha AND job_id = @alpha_job_complete AND action_type = 'payment_added' AND DATE(action_at) = DATE_SUB(CURDATE(), INTERVAL 12 DAY)
);

INSERT INTO job_actions (business_id, job_id, action_type, action_at, amount, ref_table, ref_id, note, created_at)
SELECT @biz_bravo, @bravo_job_complete, 'payment_added', DATE_SUB(@seed_now, INTERVAL 14 DAY), 2100.00, 'job_payments', NULL, CONCAT(@seed_tag, ' payment logged'), @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM job_actions WHERE business_id = @biz_bravo AND job_id = @bravo_job_complete AND action_type = 'payment_added' AND DATE(action_at) = DATE_SUB(CURDATE(), INTERVAL 14 DAY)
);

INSERT INTO job_actions (business_id, job_id, action_type, action_at, amount, ref_table, ref_id, note, created_at)
SELECT @biz_charlie, @charlie_job_complete, 'payment_added', DATE_SUB(@seed_now, INTERVAL 16 DAY), 2250.00, 'job_payments', NULL, CONCAT(@seed_tag, ' payment logged'), @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM job_actions WHERE business_id = @biz_charlie AND job_id = @charlie_job_complete AND action_type = 'payment_added' AND DATE(action_at) = DATE_SUB(CURDATE(), INTERVAL 16 DAY)
);

-- ------------------------------------------------------------
-- Estimate/Invoice docs
-- ------------------------------------------------------------
INSERT INTO job_estimate_invoices (
    job_id, document_type, title, status, amount,
    issued_at, note, customer_note,
    created_at, updated_at, created_by, updated_by
)
SELECT
    @alpha_job_complete, 'estimate', 'Seed Alpha Estimate #1', 'approved', 1980.00,
    DATE_SUB(@seed_now, INTERVAL 18 DAY), CONCAT(@seed_tag, ' estimate'), 'Thank you for reviewing this estimate.',
    @seed_now, @seed_now, @alpha_manager_id, @alpha_manager_id
WHERE NOT EXISTS (
    SELECT 1 FROM job_estimate_invoices
    WHERE job_id = @alpha_job_complete AND title = 'Seed Alpha Estimate #1'
);

INSERT INTO job_estimate_invoices (
    job_id, document_type, title, status, amount,
    issued_at, paid_at, note, customer_note, source_estimate_id,
    created_at, updated_at, created_by, updated_by
)
SELECT
    @alpha_job_complete, 'invoice', 'Seed Alpha Invoice #1', 'paid', 1980.00,
    DATE_SUB(@seed_now, INTERVAL 14 DAY), DATE_SUB(@seed_now, INTERVAL 12 DAY), CONCAT(@seed_tag, ' invoice'), 'Invoice generated from approved estimate.',
    (SELECT id FROM job_estimate_invoices WHERE job_id = @alpha_job_complete AND title = 'Seed Alpha Estimate #1' ORDER BY id DESC LIMIT 1),
    @seed_now, @seed_now, @alpha_manager_id, @alpha_manager_id
WHERE NOT EXISTS (
    SELECT 1 FROM job_estimate_invoices
    WHERE job_id = @alpha_job_complete AND title = 'Seed Alpha Invoice #1'
);

SELECT @alpha_estimate_doc := id FROM job_estimate_invoices WHERE job_id = @alpha_job_complete AND title = 'Seed Alpha Estimate #1' ORDER BY id DESC LIMIT 1;
SELECT @alpha_invoice_doc := id FROM job_estimate_invoices WHERE job_id = @alpha_job_complete AND title = 'Seed Alpha Invoice #1' ORDER BY id DESC LIMIT 1;

INSERT INTO job_estimate_invoice_line_items (
    document_id, job_id, item_type_id, item_type_label, item_description, line_note,
    quantity, unit_price, line_total, sort_order, created_at, updated_at, created_by, updated_by
)
SELECT
    @alpha_estimate_doc, @alpha_job_complete, @alpha_item_labor, 'Labor', 'Crew labor (4 hours)', CONCAT(@seed_tag, ' line item'),
    4.00, 220.00, 880.00, 10, @seed_now, @seed_now, @alpha_manager_id, @alpha_manager_id
WHERE NOT EXISTS (
    SELECT 1 FROM job_estimate_invoice_line_items
    WHERE document_id = @alpha_estimate_doc AND item_description = 'Crew labor (4 hours)'
);

INSERT INTO job_estimate_invoice_line_items (
    document_id, job_id, item_type_id, item_type_label, item_description, line_note,
    quantity, unit_price, line_total, sort_order, created_at, updated_at, created_by, updated_by
)
SELECT
    @alpha_invoice_doc, @alpha_job_complete, @alpha_item_labor, 'Labor', 'Crew labor (4 hours)', CONCAT(@seed_tag, ' line item'),
    4.00, 220.00, 880.00, 10, @seed_now, @seed_now, @alpha_manager_id, @alpha_manager_id
WHERE NOT EXISTS (
    SELECT 1 FROM job_estimate_invoice_line_items
    WHERE document_id = @alpha_invoice_doc AND item_description = 'Crew labor (4 hours)'
);

INSERT INTO job_estimate_invoice_events (
    document_id, job_id, event_type, from_status, to_status, event_note, created_at, created_by
)
SELECT
    @alpha_estimate_doc, @alpha_job_complete, 'status_changed', 'draft', 'approved', CONCAT(@seed_tag, ' estimate approved'), @seed_now, @alpha_manager_id
WHERE NOT EXISTS (
    SELECT 1 FROM job_estimate_invoice_events
    WHERE document_id = @alpha_estimate_doc AND event_type = 'status_changed' AND to_status = 'approved'
);

INSERT INTO job_estimate_invoice_events (
    document_id, job_id, event_type, from_status, to_status, event_note, created_at, created_by
)
SELECT
    @alpha_invoice_doc, @alpha_job_complete, 'status_changed', 'sent', 'paid', CONCAT(@seed_tag, ' invoice paid'), @seed_now, @alpha_manager_id
WHERE NOT EXISTS (
    SELECT 1 FROM job_estimate_invoice_events
    WHERE document_id = @alpha_invoice_doc AND event_type = 'status_changed' AND to_status = 'paid'
);

-- ------------------------------------------------------------
-- Attachments + client comms
-- ------------------------------------------------------------
INSERT INTO attachments (
    business_id, link_type, link_id, tag, original_name, stored_name,
    storage_path, mime_type, file_size, note, created_by, updated_by, created_at, updated_at
)
SELECT
    @biz_alpha, 'job', @alpha_job_complete, 'photo', 'seed_alpha_before.jpg', 'seed_alpha_before.jpg',
    '/storage/attachments/seed_alpha_before.jpg', 'image/jpeg', 188442, CONCAT(@seed_tag, ' attachment'), @alpha_manager_id, @alpha_manager_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM attachments WHERE business_id = @biz_alpha AND link_type = 'job' AND link_id = @alpha_job_complete AND original_name = 'seed_alpha_before.jpg'
);

INSERT INTO attachments (
    business_id, link_type, link_id, tag, original_name, stored_name,
    storage_path, mime_type, file_size, note, created_by, updated_by, created_at, updated_at
)
SELECT
    @biz_bravo, 'client', @bravo_client_1, 'contract', 'seed_bravo_client_agreement.pdf', 'seed_bravo_client_agreement.pdf',
    '/storage/attachments/seed_bravo_client_agreement.pdf', 'application/pdf', 99580, CONCAT(@seed_tag, ' attachment'), @bravo_manager_id, @bravo_manager_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM attachments WHERE business_id = @biz_bravo AND link_type = 'client' AND link_id = @bravo_client_1 AND original_name = 'seed_bravo_client_agreement.pdf'
);

INSERT INTO attachments (
    business_id, link_type, link_id, tag, original_name, stored_name,
    storage_path, mime_type, file_size, note, created_by, updated_by, created_at, updated_at
)
SELECT
    @biz_charlie, 'sale',
    (SELECT id FROM sales WHERE business_id = @biz_charlie AND name = 'Seed Charlie Scrap Run' ORDER BY id DESC LIMIT 1),
    'receipt', 'seed_charlie_scrap_receipt.pdf', 'seed_charlie_scrap_receipt.pdf',
    '/storage/attachments/seed_charlie_scrap_receipt.pdf', 'application/pdf', 85220, CONCAT(@seed_tag, ' attachment'), @charlie_manager_id, @charlie_manager_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM attachments WHERE business_id = @biz_charlie AND link_type = 'sale' AND original_name = 'seed_charlie_scrap_receipt.pdf'
);

INSERT INTO client_contacts (
    business_id, client_id, link_type, link_id, contact_method, direction,
    subject, notes, contacted_at, follow_up_at,
    created_by, updated_by, active, created_at, updated_at
)
SELECT
    @biz_alpha, @alpha_client_1, 'job', @alpha_job_active, 'call', 'outbound',
    'Schedule confirmation', CONCAT(@seed_tag, ' contact log'), DATE_SUB(@seed_now, INTERVAL 1 DAY), DATE_ADD(@seed_now, INTERVAL 1 DAY),
    @alpha_manager_id, @alpha_manager_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM client_contacts WHERE business_id = @biz_alpha AND client_id = @alpha_client_1 AND subject = 'Schedule confirmation'
);

INSERT INTO client_contacts (
    business_id, client_id, link_type, link_id, contact_method, direction,
    subject, notes, contacted_at, follow_up_at,
    created_by, updated_by, active, created_at, updated_at
)
SELECT
    @biz_bravo, @bravo_client_1, 'job', @bravo_job_active, 'text', 'outbound',
    'Arrival update', CONCAT(@seed_tag, ' contact log'), DATE_SUB(@seed_now, INTERVAL 1 DAY), DATE_ADD(@seed_now, INTERVAL 2 DAY),
    @bravo_manager_id, @bravo_manager_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM client_contacts WHERE business_id = @biz_bravo AND client_id = @bravo_client_1 AND subject = 'Arrival update'
);

INSERT INTO client_contacts (
    business_id, client_id, link_type, link_id, contact_method, direction,
    subject, notes, contacted_at, follow_up_at,
    created_by, updated_by, active, created_at, updated_at
)
SELECT
    @biz_charlie, @charlie_client_1, 'job', @charlie_job_active, 'email', 'outbound',
    'Work summary sent', CONCAT(@seed_tag, ' contact log'), DATE_SUB(@seed_now, INTERVAL 1 DAY), DATE_ADD(@seed_now, INTERVAL 2 DAY),
    @charlie_manager_id, @charlie_manager_id, 1, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM client_contacts WHERE business_id = @biz_charlie AND client_id = @charlie_client_1 AND subject = 'Work summary sent'
);

INSERT INTO client_reminders (
    client_id, title, reminder_at, note, status, created_by, created_at, updated_at
)
SELECT
    @alpha_client_1, 'Seed alpha follow-up reminder', DATE_ADD(@seed_now, INTERVAL 2 DAY), CONCAT(@seed_tag, ' reminder'), 'open', @alpha_manager_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM client_reminders WHERE client_id = @alpha_client_1 AND title = 'Seed alpha follow-up reminder'
);

INSERT INTO client_reminders (
    client_id, title, reminder_at, note, status, created_by, created_at, updated_at
)
SELECT
    @bravo_client_1, 'Seed bravo follow-up reminder', DATE_ADD(@seed_now, INTERVAL 3 DAY), CONCAT(@seed_tag, ' reminder'), 'open', @bravo_manager_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM client_reminders WHERE client_id = @bravo_client_1 AND title = 'Seed bravo follow-up reminder'
);

INSERT INTO client_reminders (
    client_id, title, reminder_at, note, status, created_by, created_at, updated_at
)
SELECT
    @charlie_client_1, 'Seed charlie follow-up reminder', DATE_ADD(@seed_now, INTERVAL 4 DAY), CONCAT(@seed_tag, ' reminder'), 'open', @charlie_manager_id, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM client_reminders WHERE client_id = @charlie_client_1 AND title = 'Seed charlie follow-up reminder'
);

-- ------------------------------------------------------------
-- Tasks, notifications, activity/audit
-- ------------------------------------------------------------
INSERT INTO todos (
    business_id, title, body, link_type, link_id,
    assigned_user_id, assignment_status,
    created_by, updated_by, importance, status,
    due_at, created_at, updated_at
)
SELECT
    @biz_alpha, 'Seed Alpha task: call client', CONCAT(@seed_tag, ' todo body'), 'job', @alpha_job_active,
    @alpha_user_id, 'accepted',
    @alpha_manager_id, @alpha_manager_id, 2, 'open',
    DATE_ADD(@seed_now, INTERVAL 1 DAY), @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM todos WHERE business_id = @biz_alpha AND title = 'Seed Alpha task: call client'
);

INSERT INTO todos (
    business_id, title, body, link_type, link_id,
    assigned_user_id, assignment_status,
    created_by, updated_by, importance, status,
    due_at, created_at, updated_at
)
SELECT
    @biz_bravo, 'Seed Bravo task: confirm invoice', CONCAT(@seed_tag, ' todo body'), 'job', @bravo_job_complete,
    @bravo_user_id, 'accepted',
    @bravo_manager_id, @bravo_manager_id, 3, 'in_progress',
    DATE_ADD(@seed_now, INTERVAL 2 DAY), @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM todos WHERE business_id = @biz_bravo AND title = 'Seed Bravo task: confirm invoice'
);

INSERT INTO todos (
    business_id, title, body, link_type, link_id,
    assigned_user_id, assignment_status,
    created_by, updated_by, importance, status,
    due_at, completed_at, created_at, updated_at
)
SELECT
    @biz_charlie, 'Seed Charlie task: archive docs', CONCAT(@seed_tag, ' todo body'), 'job', @charlie_job_complete,
    @charlie_user_id, 'accepted',
    @charlie_manager_id, @charlie_manager_id, 4, 'closed',
    DATE_SUB(@seed_now, INTERVAL 2 DAY), DATE_SUB(@seed_now, INTERVAL 1 DAY), @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM todos WHERE business_id = @biz_charlie AND title = 'Seed Charlie task: archive docs'
);

INSERT INTO user_actions (
    user_id, business_id, action_key, entity_table, entity_id,
    summary, details, ip_address, created_at
)
SELECT
    @alpha_manager_id, @biz_alpha, 'job_created', 'jobs', @alpha_job_active,
    'Created Seed Alpha Active Job', CONCAT(@seed_tag, ' user action'), '127.0.0.1', @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM user_actions WHERE user_id = @alpha_manager_id AND action_key = 'job_created' AND entity_id = @alpha_job_active
);

INSERT INTO user_actions (
    user_id, business_id, action_key, entity_table, entity_id,
    summary, details, ip_address, created_at
)
SELECT
    @bravo_manager_id, @biz_bravo, 'expense_added', 'expenses',
    (SELECT id FROM expenses WHERE business_id = @biz_bravo AND job_id = @bravo_job_complete ORDER BY id DESC LIMIT 1),
    'Logged disposal fee expense', CONCAT(@seed_tag, ' user action'), '127.0.0.1', @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM user_actions WHERE user_id = @bravo_manager_id AND action_key = 'expense_added' AND summary = 'Logged disposal fee expense'
);

INSERT INTO user_actions (
    user_id, business_id, action_key, entity_table, entity_id,
    summary, details, ip_address, created_at
)
SELECT
    @charlie_manager_id, @biz_charlie, 'sale_added', 'sales',
    (SELECT id FROM sales WHERE business_id = @biz_charlie AND name = 'Seed Charlie Scrap Run' ORDER BY id DESC LIMIT 1),
    'Recorded Seed Charlie Scrap Run', CONCAT(@seed_tag, ' user action'), '127.0.0.1', @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM user_actions WHERE user_id = @charlie_manager_id AND action_key = 'sale_added' AND summary = 'Recorded Seed Charlie Scrap Run'
);

INSERT INTO user_login_records (
    user_id, business_id, login_method, ip_address, user_agent,
    browser_name, browser_version, os_name, device_type, logged_in_at
)
SELECT
    @alpha_admin_id, @biz_alpha, 'password', '127.0.0.1', 'SeedAgent/1.0',
    'Chrome', '122', 'macOS', 'desktop', DATE_SUB(@seed_now, INTERVAL 1 DAY)
WHERE NOT EXISTS (
    SELECT 1 FROM user_login_records WHERE user_id = @alpha_admin_id AND DATE(logged_in_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
);

INSERT INTO user_login_records (
    user_id, business_id, login_method, ip_address, user_agent,
    browser_name, browser_version, os_name, device_type, logged_in_at
)
SELECT
    @bravo_admin_id, @biz_bravo, 'password', '127.0.0.1', 'SeedAgent/1.0',
    'Safari', '17', 'iOS', 'mobile', DATE_SUB(@seed_now, INTERVAL 1 DAY)
WHERE NOT EXISTS (
    SELECT 1 FROM user_login_records WHERE user_id = @bravo_admin_id AND DATE(logged_in_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
);

INSERT INTO user_login_records (
    user_id, business_id, login_method, ip_address, user_agent,
    browser_name, browser_version, os_name, device_type, logged_in_at
)
SELECT
    @charlie_admin_id, @biz_charlie, 'password', '127.0.0.1', 'SeedAgent/1.0',
    'Firefox', '124', 'Windows', 'desktop', DATE_SUB(@seed_now, INTERVAL 1 DAY)
WHERE NOT EXISTS (
    SELECT 1 FROM user_login_records WHERE user_id = @charlie_admin_id AND DATE(logged_in_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
);

INSERT INTO auth_login_attempts (
    email, user_id, ip_address, status, reason, user_agent, attempted_at
)
SELECT
    'seed.alpha.user.20260225@example.com', @alpha_user_id, '127.0.0.1', 'failed', 'bad_password', 'SeedAgent/1.0', DATE_SUB(@seed_now, INTERVAL 6 HOUR)
WHERE NOT EXISTS (
    SELECT 1 FROM auth_login_attempts WHERE email = 'seed.alpha.user.20260225@example.com' AND status = 'failed'
);

INSERT INTO auth_login_attempts (
    email, user_id, ip_address, status, reason, user_agent, attempted_at
)
SELECT
    'seed.bravo.user.20260225@example.com', @bravo_user_id, '127.0.0.1', 'blocked', 'locked', 'SeedAgent/1.0', DATE_SUB(@seed_now, INTERVAL 5 HOUR)
WHERE NOT EXISTS (
    SELECT 1 FROM auth_login_attempts WHERE email = 'seed.bravo.user.20260225@example.com' AND status = 'blocked'
);

INSERT INTO auth_login_attempts (
    email, user_id, ip_address, status, reason, user_agent, attempted_at
)
SELECT
    'seed.charlie.user.20260225@example.com', @charlie_user_id, '127.0.0.1', 'success', 'ok', 'SeedAgent/1.0', DATE_SUB(@seed_now, INTERVAL 4 HOUR)
WHERE NOT EXISTS (
    SELECT 1 FROM auth_login_attempts WHERE email = 'seed.charlie.user.20260225@example.com' AND status = 'success'
);

INSERT INTO user_notification_states (
    user_id, business_id, notification_key, is_read, read_at, dismissed_at, created_at, updated_at
)
SELECT
    @alpha_admin_id, @biz_alpha, 'seed.notification.alpha.pending', 0, NULL, NULL, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM user_notification_states
    WHERE user_id = @alpha_admin_id AND business_id = @biz_alpha AND notification_key = 'seed.notification.alpha.pending'
);

INSERT INTO user_notification_states (
    user_id, business_id, notification_key, is_read, read_at, dismissed_at, created_at, updated_at
)
SELECT
    @bravo_admin_id, @biz_bravo, 'seed.notification.bravo.pending', 1, DATE_SUB(@seed_now, INTERVAL 1 DAY), NULL, @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM user_notification_states
    WHERE user_id = @bravo_admin_id AND business_id = @biz_bravo AND notification_key = 'seed.notification.bravo.pending'
);

INSERT INTO user_notification_states (
    user_id, business_id, notification_key, is_read, read_at, dismissed_at, created_at, updated_at
)
SELECT
    @charlie_admin_id, @biz_charlie, 'seed.notification.charlie.pending', 1, DATE_SUB(@seed_now, INTERVAL 2 DAY), DATE_SUB(@seed_now, INTERVAL 1 DAY), @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM user_notification_states
    WHERE user_id = @charlie_admin_id AND business_id = @biz_charlie AND notification_key = 'seed.notification.charlie.pending'
);

INSERT INTO report_presets (
    user_id, name, report_key, start_date, end_date, filters_json, created_at, updated_at
)
SELECT
    @alpha_admin_id, 'Seed Alpha Profitability', 'job_profitability', DATE_SUB(CURDATE(), INTERVAL 30 DAY), CURDATE(),
    JSON_OBJECT('status', 'all', 'business_id', @biz_alpha), @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM report_presets WHERE user_id = @alpha_admin_id AND name = 'Seed Alpha Profitability'
);

INSERT INTO report_presets (
    user_id, name, report_key, start_date, end_date, filters_json, created_at, updated_at
)
SELECT
    @bravo_admin_id, 'Seed Bravo Disposal', 'disposal_spend_vs_scrap', DATE_SUB(CURDATE(), INTERVAL 30 DAY), CURDATE(),
    JSON_OBJECT('status', 'all', 'business_id', @biz_bravo), @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM report_presets WHERE user_id = @bravo_admin_id AND name = 'Seed Bravo Disposal'
);

INSERT INTO report_presets (
    user_id, name, report_key, start_date, end_date, filters_json, created_at, updated_at
)
SELECT
    @charlie_admin_id, 'Seed Charlie Labor', 'employee_labor_cost', DATE_SUB(CURDATE(), INTERVAL 30 DAY), CURDATE(),
    JSON_OBJECT('status', 'all', 'business_id', @biz_charlie), @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM report_presets WHERE user_id = @charlie_admin_id AND name = 'Seed Charlie Labor'
);

INSERT INTO user_filter_presets (
    user_id, module_key, preset_name, filters_json, created_at, updated_at
)
SELECT
    @alpha_admin_id, 'jobs.index', 'Seed Dispatch View', JSON_OBJECT('status', 'dispatch', 'q', 'Seed'), @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM user_filter_presets WHERE user_id = @alpha_admin_id AND module_key = 'jobs.index' AND preset_name = 'Seed Dispatch View'
);

INSERT INTO user_filter_presets (
    user_id, module_key, preset_name, filters_json, created_at, updated_at
)
SELECT
    @bravo_admin_id, 'sales.index', 'Seed Current Month', JSON_OBJECT('range', 'month', 'type', 'all'), @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM user_filter_presets WHERE user_id = @bravo_admin_id AND module_key = 'sales.index' AND preset_name = 'Seed Current Month'
);

INSERT INTO user_filter_presets (
    user_id, module_key, preset_name, filters_json, created_at, updated_at
)
SELECT
    @charlie_admin_id, 'tasks.index', 'Seed Overdue Focus', JSON_OBJECT('status', 'open', 'bucket', 'overdue'), @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM user_filter_presets WHERE user_id = @charlie_admin_id AND module_key = 'tasks.index' AND preset_name = 'Seed Overdue Focus'
);

INSERT INTO dashboard_kpi_snapshots (
    business_id, snapshot_date, metrics_json, created_at, updated_at
)
SELECT
    @biz_alpha, CURDATE(),
    JSON_OBJECT('active_jobs', 1, 'pending_jobs', 1, 'open_tasks', 1, 'sales_mtd', 420.00, 'expenses_mtd', 145.00),
    @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM dashboard_kpi_snapshots WHERE business_id = @biz_alpha AND snapshot_date = CURDATE()
);

INSERT INTO dashboard_kpi_snapshots (
    business_id, snapshot_date, metrics_json, created_at, updated_at
)
SELECT
    @biz_bravo, CURDATE(),
    JSON_OBJECT('active_jobs', 1, 'pending_jobs', 1, 'open_tasks', 1, 'sales_mtd', 560.00, 'expenses_mtd', 210.00),
    @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM dashboard_kpi_snapshots WHERE business_id = @biz_bravo AND snapshot_date = CURDATE()
);

INSERT INTO dashboard_kpi_snapshots (
    business_id, snapshot_date, metrics_json, created_at, updated_at
)
SELECT
    @biz_charlie, CURDATE(),
    JSON_OBJECT('active_jobs', 1, 'pending_jobs', 1, 'open_tasks', 1, 'sales_mtd', 610.00, 'expenses_mtd', 180.00),
    @seed_now, @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM dashboard_kpi_snapshots WHERE business_id = @biz_charlie AND snapshot_date = CURDATE()
);

-- ------------------------------------------------------------
-- Dev bugs + site admin support queue
-- ------------------------------------------------------------
INSERT INTO dev_bugs (
    title, details, status, severity, environment, module_key, route_path,
    reported_by, assigned_user_id, created_at, updated_at, created_by, updated_by
)
SELECT
    'Seed bug: schedule drag refresh', CONCAT(@seed_tag, ' reproduce by dragging job to calendar then returning to board.'),
    'confirmed', 2, 'local', 'schedule_board', '/schedule-board',
    @alpha_admin_id, @site_admin_id, @seed_now, @seed_now, @alpha_admin_id, @alpha_admin_id
WHERE NOT EXISTS (SELECT 1 FROM dev_bugs WHERE title = 'Seed bug: schedule drag refresh');

INSERT INTO dev_bugs (
    title, details, status, severity, environment, module_key, route_path,
    reported_by, assigned_user_id, created_at, updated_at, created_by, updated_by
)
SELECT
    'Seed bug: client autosuggest overlay', CONCAT(@seed_tag, ' autosuggest appears behind container in add job.'),
    'working', 3, 'live', 'jobs', '/jobs/create',
    @bravo_admin_id, @site_admin_id, @seed_now, @seed_now, @bravo_admin_id, @bravo_admin_id
WHERE NOT EXISTS (SELECT 1 FROM dev_bugs WHERE title = 'Seed bug: client autosuggest overlay');

INSERT INTO dev_bugs (
    title, details, status, severity, environment, module_key, route_path,
    reported_by, assigned_user_id, fixed_at, fixed_by, created_at, updated_at, created_by, updated_by
)
SELECT
    'Seed bug: delayed toast after business switch', CONCAT(@seed_tag, ' delayed flash/notification after context switch.'),
    'fixed_closed', 1, 'live', 'site_admin', '/site-admin',
    @charlie_admin_id, @site_admin_id, DATE_SUB(@seed_now, INTERVAL 1 DAY), @site_admin_id,
    @seed_now, @seed_now, @charlie_admin_id, @charlie_admin_id
WHERE NOT EXISTS (SELECT 1 FROM dev_bugs WHERE title = 'Seed bug: delayed toast after business switch');

SELECT @seed_bug_1 := id FROM dev_bugs WHERE title = 'Seed bug: schedule drag refresh' ORDER BY id DESC LIMIT 1;
SELECT @seed_bug_2 := id FROM dev_bugs WHERE title = 'Seed bug: client autosuggest overlay' ORDER BY id DESC LIMIT 1;
SELECT @seed_bug_3 := id FROM dev_bugs WHERE title = 'Seed bug: delayed toast after business switch' ORDER BY id DESC LIMIT 1;

INSERT INTO dev_bug_notes (bug_id, note, created_by, created_at, updated_at)
SELECT @seed_bug_1, 'seed_2026_02_25 triaged and reproduced.', @site_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM dev_bug_notes WHERE bug_id = @seed_bug_1 AND note = 'seed_2026_02_25 triaged and reproduced.');

INSERT INTO dev_bug_notes (bug_id, note, created_by, created_at, updated_at)
SELECT @seed_bug_2, 'seed_2026_02_25 CSS z-index patch in progress.', @site_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM dev_bug_notes WHERE bug_id = @seed_bug_2 AND note = 'seed_2026_02_25 CSS z-index patch in progress.');

INSERT INTO dev_bug_notes (bug_id, note, created_by, created_at, updated_at)
SELECT @seed_bug_3, 'seed_2026_02_25 fixed and verified on local.', @site_admin_id, @seed_now, @seed_now
WHERE NOT EXISTS (SELECT 1 FROM dev_bug_notes WHERE bug_id = @seed_bug_3 AND note = 'seed_2026_02_25 fixed and verified on local.');

INSERT INTO site_admin_tickets (
    business_id, submitted_by_user_id, submitted_by_email,
    category, subject, message, status, priority,
    assigned_to_user_id, opened_at, created_at, updated_at,
    created_by, updated_by
)
SELECT
    @biz_alpha, @alpha_user_id, 'seed.alpha.user.20260225@example.com',
    'bug', 'Seed support ticket: photo upload flow', CONCAT(@seed_tag, ' multiple upload redirects unexpectedly.'), 'working', 2,
    @site_admin_id, DATE_SUB(@seed_now, INTERVAL 1 DAY), @seed_now, @seed_now,
    @alpha_user_id, @site_admin_id
WHERE NOT EXISTS (
    SELECT 1 FROM site_admin_tickets WHERE subject = 'Seed support ticket: photo upload flow'
);

INSERT INTO site_admin_tickets (
    business_id, submitted_by_user_id, submitted_by_email,
    category, subject, message, status, priority,
    assigned_to_user_id, opened_at, created_at, updated_at,
    created_by, updated_by
)
SELECT
    @biz_bravo, @bravo_user_id, 'seed.bravo.user.20260225@example.com',
    'suggestion', 'Seed support ticket: quick add shortcut', CONCAT(@seed_tag, ' request quick-add from every page.'), 'pending', 3,
    @site_admin_id, DATE_SUB(@seed_now, INTERVAL 6 HOUR), @seed_now, @seed_now,
    @bravo_user_id, @site_admin_id
WHERE NOT EXISTS (
    SELECT 1 FROM site_admin_tickets WHERE subject = 'Seed support ticket: quick add shortcut'
);

INSERT INTO site_admin_tickets (
    business_id, submitted_by_user_id, submitted_by_email,
    category, subject, message, status, priority,
    assigned_to_user_id, created_at, updated_at,
    created_by, updated_by
)
SELECT
    @biz_charlie, @charlie_user_id, 'seed.charlie.user.20260225@example.com',
    'question', 'Seed support ticket: estimate tax defaults', CONCAT(@seed_tag, ' asking how tax defaults apply per line item.'), 'unopened', 4,
    NULL, @seed_now, @seed_now,
    @charlie_user_id, @charlie_user_id
WHERE NOT EXISTS (
    SELECT 1 FROM site_admin_tickets WHERE subject = 'Seed support ticket: estimate tax defaults'
);

SELECT @seed_ticket_1 := id FROM site_admin_tickets WHERE subject = 'Seed support ticket: photo upload flow' ORDER BY id DESC LIMIT 1;
SELECT @seed_ticket_2 := id FROM site_admin_tickets WHERE subject = 'Seed support ticket: quick add shortcut' ORDER BY id DESC LIMIT 1;
SELECT @seed_ticket_3 := id FROM site_admin_tickets WHERE subject = 'Seed support ticket: estimate tax defaults' ORDER BY id DESC LIMIT 1;

INSERT INTO site_admin_ticket_notes (ticket_id, user_id, visibility, note, created_at, updated_at, created_by)
SELECT @seed_ticket_1, @site_admin_id, 'customer', 'seed_2026_02_25 we reproduced and queued a fix.', @seed_now, @seed_now, @site_admin_id
WHERE NOT EXISTS (
    SELECT 1 FROM site_admin_ticket_notes WHERE ticket_id = @seed_ticket_1 AND note = 'seed_2026_02_25 we reproduced and queued a fix.'
);

INSERT INTO site_admin_ticket_notes (ticket_id, user_id, visibility, note, created_at, updated_at, created_by)
SELECT @seed_ticket_2, @site_admin_id, 'internal', 'seed_2026_02_25 evaluate for 2.3 milestone.', @seed_now, @seed_now, @site_admin_id
WHERE NOT EXISTS (
    SELECT 1 FROM site_admin_ticket_notes WHERE ticket_id = @seed_ticket_2 AND note = 'seed_2026_02_25 evaluate for 2.3 milestone.'
);

INSERT INTO site_admin_ticket_notes (ticket_id, user_id, visibility, note, created_at, updated_at, created_by)
SELECT @seed_ticket_3, @site_admin_id, 'customer', 'seed_2026_02_25 tax can be edited before save on estimate/invoice form.', @seed_now, @seed_now, @site_admin_id
WHERE NOT EXISTS (
    SELECT 1 FROM site_admin_ticket_notes WHERE ticket_id = @seed_ticket_3 AND note = 'seed_2026_02_25 tax can be edited before save on estimate/invoice form.'
);

-- ------------------------------------------------------------
-- Role permissions (seed marker module)
-- ------------------------------------------------------------
INSERT INTO role_permissions (
    role_value, module_key, can_view, can_create, can_edit, can_delete, updated_by, updated_at
)
VALUES
    (0, 'seed_demo', 1, 0, 0, 0, @site_admin_id, @seed_now),
    (1, 'seed_demo', 1, 1, 1, 0, @site_admin_id, @seed_now),
    (2, 'seed_demo', 1, 1, 1, 1, @site_admin_id, @seed_now),
    (3, 'seed_demo', 1, 1, 1, 1, @site_admin_id, @seed_now),
    (4, 'seed_demo', 1, 1, 1, 1, @site_admin_id, @seed_now)
ON DUPLICATE KEY UPDATE
    can_view = VALUES(can_view),
    can_create = VALUES(can_create),
    can_edit = VALUES(can_edit),
    can_delete = VALUES(can_delete),
    updated_by = VALUES(updated_by),
    updated_at = VALUES(updated_at);

-- ------------------------------------------------------------
-- Final summary markers
-- ------------------------------------------------------------
INSERT INTO user_actions (
    user_id, business_id, action_key, entity_table, entity_id,
    summary, details, ip_address, created_at
)
SELECT
    @site_admin_id, @biz_alpha, 'seed_loaded', 'businesses', @biz_alpha,
    'Dummy seed migration loaded', CONCAT(@seed_tag, ' completed for businesses: ', @biz_alpha, ',', @biz_bravo, ',', @biz_charlie), '127.0.0.1', @seed_now
WHERE NOT EXISTS (
    SELECT 1 FROM user_actions
    WHERE user_id = @site_admin_id AND action_key = 'seed_loaded' AND DATE(created_at) = CURDATE()
);
