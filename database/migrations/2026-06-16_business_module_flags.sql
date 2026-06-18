-- Per-tenant module toggles and terminology (vertical-first SaaS)
ALTER TABLE businesses
    ADD COLUMN module_flags JSON NULL DEFAULT NULL AFTER is_active;

ALTER TABLE businesses
    ADD COLUMN label_job VARCHAR(50) NULL DEFAULT 'Job' AFTER module_flags;
