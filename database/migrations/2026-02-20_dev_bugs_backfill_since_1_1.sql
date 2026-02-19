-- JunkTracker: backfill fixed bug history since Beta 1.1
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS dev_bugs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    details TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'new',
    severity TINYINT UNSIGNED NOT NULL DEFAULT 3,
    environment VARCHAR(16) NOT NULL DEFAULT 'local',
    module_key VARCHAR(80) NULL,
    route_path VARCHAR(255) NULL,
    reported_by BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    fixed_at DATETIME NULL,
    fixed_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_dev_bugs_status_updated (status, updated_at),
    KEY idx_dev_bugs_severity_status (severity, status),
    KEY idx_dev_bugs_environment_status (environment, status),
    KEY idx_dev_bugs_assigned (assigned_user_id),
    KEY idx_dev_bugs_reported (reported_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(190) NOT NULL,
    checksum VARCHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_schema_migrations_key (migration_key),
    KEY idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TEMPORARY TABLE tmp_dev_bugs_backfill_1_1 (
    title VARCHAR(255) NOT NULL,
    details TEXT NULL,
    severity TINYINT UNSIGNED NOT NULL,
    environment VARCHAR(16) NOT NULL,
    module_key VARCHAR(80) NULL,
    route_path VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_dev_bugs_backfill_1_1 (title, details, severity, environment, module_key, route_path) VALUES
('Login redirected back to login without error after submit', 'Added clear login error handling and flash messaging for failed authentication attempts.', 4, 'both', 'users', '/login'),
('Session expired error unless Remember Me selected', 'Fixed session/csrf behavior so login works correctly with or without Remember Me.', 5, 'both', 'users', '/login'),
('Logout action failed for active sessions', 'Resolved logout/session cleanup flow so users can reliably end sessions.', 4, 'both', 'users', '/logout'),
('Newly invited users could not sign in after setting password', 'Fixed setup-token password flow and login checks for invited accounts.', 5, 'both', 'users', '/set-password'),
('Login route produced too many redirects', 'Resolved authentication redirect loop causing ERR_TOO_MANY_REDIRECTS.', 5, 'local', 'users', '/login'),
('UsersController parse error from invalid token sequence', 'Removed malformed token causing controller parse failure.', 4, 'local', 'users', '/users'),
('Prospect model parse error on cancel flow', 'Removed malformed token causing prospect add/cancel parse failure.', 4, 'local', 'prospects', '/prospects/new'),
('Job soft delete failed on missing updated_by column', 'Aligned delete query with actual schema so soft delete succeeds.', 4, 'local', 'jobs', '/jobs/{id}/delete'),
('Add job submitted then returned session expired without save', 'Fixed job form CSRF/session handling to save reliably.', 4, 'both', 'jobs', '/jobs/new'),
('Job contact lookup returned companies instead of clients', 'Corrected contact data source to clients table in job forms.', 3, 'both', 'jobs', '/jobs/{id}/edit'),
('Second time-entry save failed with session expired', 'Fixed repeated submit token/session behavior on time tracking create.', 4, 'both', 'time_tracking', '/time-tracking/new'),
('Time entry saved but did not appear in job hours summary', 'Fixed job hours query to include valid active time entries.', 3, 'both', 'time_tracking', '/jobs/{id}'),
('Disposal fees on jobs did not persist', 'Corrected disposal fee save path and action logging.', 3, 'both', 'jobs', '/jobs/{id}'),
('Expense save failed when job_id was 0', 'Handled non-job expenses correctly by storing NULL job_id and validating input.', 5, 'local', 'expenses', '/expenses/new'),
('Employee quick punch-in forced job selection', 'Updated quick punch flow to allow non-job punch-ins.', 3, 'both', 'employees', '/employees/{id}'),
('Job view showed Punch In after employee already punched in', 'Fixed stale punch-state refresh logic after navigation.', 3, 'both', 'time_tracking', '/jobs/{id}'),
('User self-deactivation was allowed for non-dev users', 'Added guard so only dev users can deactivate their own account.', 4, 'both', 'users', '/users/{id}'),
('Employee-link autosuggest dropdown clipped inside card', 'Adjusted overflow and stacking so autosuggest renders fully above card boundaries.', 2, 'both', 'users', '/users/{id}'),
('Time entries displayed with incorrect timezone offset', 'Standardized app timezone handling to Eastern Time for punch and display consistency.', 3, 'both', 'time_tracking', '/time-tracking'),
('Dashboard punch-in only supported non-job flow', 'Added modal flow to choose Job Time vs Non-Job Time at punch-in.', 2, 'local', 'time_tracking', '/');

INSERT INTO dev_bugs (
    title,
    details,
    status,
    severity,
    environment,
    module_key,
    route_path,
    fixed_at,
    updated_at,
    created_at
)
SELECT
    t.title,
    t.details,
    'fixed',
    t.severity,
    t.environment,
    t.module_key,
    t.route_path,
    NOW(),
    NOW(),
    NOW()
FROM tmp_dev_bugs_backfill_1_1 t
LEFT JOIN dev_bugs b
    ON b.title = t.title
   AND b.deleted_at IS NULL
WHERE b.id IS NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_dev_bugs_backfill_1_1;

INSERT IGNORE INTO schema_migrations (migration_key, checksum, applied_at)
VALUES ('2026-02-20_dev_bugs_backfill_since_1_1', 'dev-bugs-backfill-1.1-v1', NOW());
