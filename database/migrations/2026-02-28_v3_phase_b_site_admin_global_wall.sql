-- Ensure site_admin users remain global-only and are not tied to business memberships.

UPDATE business_user_memberships m
INNER JOIN users u ON u.id = m.user_id
SET
    m.is_active = 0,
    m.deleted_at = COALESCE(m.deleted_at, NOW()),
    m.updated_at = NOW()
WHERE u.role = 'site_admin'
  AND (m.deleted_at IS NULL OR COALESCE(m.is_active, 1) = 1);

