# Live Release 1.8.2.2-beta

Date: 2026-05-01

## Highlights

- Updated job deactivation behavior to write soft-delete metadata on live schemas:
  - `deleted_at = NOW()`
  - `deleted_by = <actor>`
- Keeps compatibility behavior for other schemas (`status = 'inactive'`, `is_active = 0` where available).
- Ensures deactivated jobs are reliably excluded from calendar and index queries that already filter `deleted_at IS NULL`.

## Database migrations in this release

No new database migrations are required for 1.8.2.2-beta.

## Ops notes

- Deactivate a job and confirm the row gets `deleted_at` and `deleted_by` values.
- Verify the same job no longer appears on the Events calendar.
