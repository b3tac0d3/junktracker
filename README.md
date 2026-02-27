# JunkTracker v3 (Phase A / B)

Clean rebuild focused on multi-business isolation and core architecture.

## Current scope

- Auth (no 2FA)
- Site admin workspace switching
- Dashboard shell
- Clients index (search + standardized filter/list structure)
- Core module placeholders (Jobs, Tasks, Time Tracking, Billing)
- Core schema migration bundle

## Roles

- `punch_only`
- `general_user`
- `admin`
- `site_admin` (global)

## Migrations

Run in this order:

1. `database/migrations/2026-02-27_v3_phase_a_core.sql`
2. `database/migrations/2026-02-27_v3_phase_a_seed.sql` (optional local seed)
3. `database/migrations/2026-02-27_v3_phase_b_clients_index_seed.sql`
4. `database/migrations/2026-02-27_v3_phase_b_jobs_status_prospect.sql`
5. `database/migrations/2026-02-27_v3_phase_b_clients_profile_fields.sql`
6. `database/migrations/2026-02-27_v3_phase_b_client_contact_flags.sql`

## Local login (seed)

- Site admin: `siteadmin@junktracker.local` / `ChangeMe123!`
- Business admin: `admin@demojunk.com` / `ChangeMe123!`

## Notes

- Every business-owned table includes `business_id` and business-safe indexes.
- Runtime schema mutation is removed; schema is migration-driven.
- UI is intentionally minimal for clean Phase A iteration.
