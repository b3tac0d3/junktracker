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
7. `database/migrations/2026-02-27_v3_phase_b_client_phone_flags.sql`
8. `database/migrations/2026-03-21_v3_roadmap_features.sql` (client portal tokens, job close-out columns, bank deposits)

Live release rule:

- Build bundles with `scripts/build-live-release.sh` (see script header). **Default is a delta bundle** (files changed since a git tag/ref, e.g. `v1.3.5`). Use **`full`** only when you need the entire codebase uploaded.
- Default output location is **`htdocs/junktracker_live_releases/<release_name>/upload`** (sibling of this repo). Set `JUNKTRACKER_LIVE_RELEASE_ROOT` to use a different parent folder.
- Release bundles should include only **new/current migrations** needed for that release.
- Do not re-copy older migrations that were already shipped in previous live releases.

## Local login (seed)

- Site admin: `siteadmin@junktracker.local` / `ChangeMe123!`
- Business admin: `admin@demojunk.com` / `ChangeMe123!`

## Notes

- Every business-owned table includes `business_id` and business-safe indexes.
- Runtime schema mutation is removed; schema is migration-driven.
- UI is intentionally minimal for clean Phase A iteration.

## Roadmap

See **[docs/roadmap.md](docs/roadmap.md)** for the full prioritized themes (client-facing PDF/portal/email, field workflow, money & reconciliation, admin/quality, technical deploy hygiene). Older one-line ideas (subcontracted jobs, inventory lots) are folded into that doc under **Money & reconciliation**.

Deploy steps: **[docs/deploy-checklist.md](docs/deploy-checklist.md)**.
