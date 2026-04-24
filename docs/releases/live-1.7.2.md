# Live Release 1.7.2

Release date: 2026-04-24

## Summary

- **Admin form select values:** Deleting options (for example Job Type) now persists after refresh. Default seeding no longer re-inserts options the business has previously removed.
- **`form_select_values` schema:** Ensures soft-delete and audit columns exist where missing so deletes succeed on older databases.
- **Global search:** Fixes a 500 error on `/search` caused by an incorrect argument order when loading job results (`Job::indexList`).

## Database

- Run this migration for 1.7.2 (idempotent; safe if you already ran `2026-04-24_live_1.7.1.sql`):

  - `database/migrations/2026-04-24_live_1.7.2.sql`
