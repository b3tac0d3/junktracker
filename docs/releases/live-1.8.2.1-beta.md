# Live Release 1.8.2.1-beta

Date: 2026-05-01

## Highlights

- Fixed events calendar job feed to exclude deactivated jobs in both schema patterns:
  - `is_active = 0`
  - `status = 'inactive'`
- Added `scripts/new-beta-release.sh` to standardize one-command beta release prep.

## Database migrations in this release

No new database migrations are required for 1.8.2.1-beta.

## Ops notes

- Verify deactivated jobs are no longer visible on month/week/day/list calendar views.
- Verify the release helper works:
  - `./scripts/new-beta-release.sh 1.8.3-beta "short summary"`
