ALTER TABLE users
    ADD COLUMN invited_at DATETIME NULL AFTER must_change_password,
    ADD COLUMN invitation_expires_at DATETIME NULL AFTER invited_at,
    ADD COLUMN invitation_accepted_at DATETIME NULL AFTER invitation_expires_at;

UPDATE users
SET invited_at = COALESCE(invited_at, NOW()),
    invitation_expires_at = NULL,
    invitation_accepted_at = COALESCE(invitation_accepted_at, NOW())
WHERE deleted_at IS NULL;
