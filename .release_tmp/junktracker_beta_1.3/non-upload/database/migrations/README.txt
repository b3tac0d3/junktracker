JunkTracker 1.3 (beta) — database migrations for live

Back up your database first.

Run these SQL files in order on your MariaDB/MySQL database if they are not already applied
(check schema_migrations for migration_key, or run only missing pieces):

1. 2026-03-21_v3_client_newsletter_bolo.sql
   — Newsletter opt-in + BOLO profile tables (client_bolo_profiles, client_bolo_lines).

2. 2026-03-22_v3_bolo_profile_active.sql
   — BOLO profile is_active column (requires tables from #1).

If a migration was partially applied, use the conditional logic in each file or apply only the missing ALTER/CREATE statements.
