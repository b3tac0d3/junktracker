-- Optional seed for Phase A local testing

INSERT INTO businesses (id, name, legal_name, email, phone, is_active, created_at, updated_at)
VALUES (1, 'Demo Junk Removal', 'Demo Junk Removal LLC', 'admin@demojunk.com', '401-555-0101', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    legal_name = VALUES(legal_name),
    email = VALUES(email),
    phone = VALUES(phone),
    is_active = VALUES(is_active),
    updated_at = NOW();

INSERT INTO users (id, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
VALUES
    (1, 'siteadmin@junktracker.local', '$2y$10$IEN0s1iPichK/RqJ0jIQruvbVRepzNGFrTiSh7ODWj/jbXPK68sj6', 'Site', 'Admin', 'site_admin', 1, NOW(), NOW()),
    (2, 'admin@demojunk.com', '$2y$10$IEN0s1iPichK/RqJ0jIQruvbVRepzNGFrTiSh7ODWj/jbXPK68sj6', 'Business', 'Admin', 'general_user', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    role = VALUES(role),
    is_active = VALUES(is_active),
    updated_at = NOW();

INSERT IGNORE INTO business_user_memberships (
    business_id, user_id, role, is_active, created_at, updated_at
) VALUES
    (1, 1, 'admin', 1, NOW(), NOW()),
    (1, 2, 'admin', 1, NOW(), NOW());
