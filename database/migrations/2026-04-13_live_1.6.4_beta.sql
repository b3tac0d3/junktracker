-- JunkTracker 1.6.4 beta — quote job type visibility and outstanding quotes dashboard panel.
-- No ALTER statements; records release in schema_migrations for deploy tracking.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key, applied_at)
VALUES ('2026-04-13_live_1.6.4_beta', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
