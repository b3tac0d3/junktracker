ALTER TABLE users
    ADD COLUMN password_reset_token_hash CHAR(64) NULL,
    ADD COLUMN password_reset_sent_at DATETIME NULL,
    ADD COLUMN password_reset_expires_at DATETIME NULL;
