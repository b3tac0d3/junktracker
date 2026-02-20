# JunkTracker Release Checklist

Use this checklist for each beta/live deployment.

## 1) Pre-Deploy
- Confirm branch and commit hash (`git branch --show-current`, `git rev-parse --short HEAD`).
- Confirm app version in `config/app.php`.
- Run PHP lint on changed files.
- Export a fresh DB backup from source and target environments.

## 1.5) Build Release Bundle
- Full bundle:
  - `scripts/build_release_bundle.sh --version beta_1.3.0`
- Delta bundle (only changed/added files since a ref):
  - `scripts/build_release_bundle.sh --version beta_1.3.0_patch --mode delta --from origin/main`
- Live profile with DB override file generation:
  - `JT_LIVE_DB_HOST=127.0.0.1 JT_LIVE_DB_NAME=... JT_LIVE_DB_USER=... JT_LIVE_DB_PASS=... scripts/build_release_bundle.sh --version beta_1.3.0 --profile live`
- Upload output:
  - `junktracker_live_releases/junktracker_<version>/upload`
- Non-upload docs/sql:
  - `junktracker_live_releases/junktracker_<version>/non-upload`
- If `deleted_paths.txt` has entries in delta mode, remove those paths on live.

## 2) Database Migrations
- In phpMyAdmin, run pending scripts from `database/migrations/` in order.
- Verify `schema_migrations` exists and has the latest migration keys.
- Spot-check new tables/columns in `users`, `user_login_records`, and auth/audit tables.

## 3) Storage and Runtime
- Ensure writable paths:
  - `storage/sessions`
  - `storage/logs`
  - `storage/consignor_contracts`
- Confirm timezone and environment config in `config/app.php` and live env vars.
- Confirm mail mode/host in Admin Settings.

## 4) Smoke Tests
- Login success with and without remember-me.
- Failed login lockout behavior after repeated bad attempts.
- Logout works and redirects to login.
- User show page displays last login and failed login metadata.
- Admin:
  - Audit filters + CSV export
  - System Health statuses
  - Permissions/settings pages load
- Dashboard loads without PHP errors.

## 5) Post-Deploy
- Tag release in git (example: `beta-1.1.3`).
- Capture rollback point (commit hash + DB backup filename).
- Monitor `storage/logs` and auth behavior for 24h.
