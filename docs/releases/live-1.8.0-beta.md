# Live Release 1.8.0-beta

Date: 2026-04-28

## Highlights

- Added a new Networking module with create/list/edit/detail views and full CRUD flow.
- Updated Networking contacts to use `first_name` and `last_name` while preserving compatibility with legacy `name` rows.
- Added mobile-friendly tap-to-call links across key areas, including event quick view phone actions for client-linked events.
- Continued quote workflow refinement (service type/job type alignment, quote date handling, and cleaner quote form fields).

## Database migrations in this release

Run these once, in filename order:

1. `database/migrations/2026-04-27_networking_contacts.sql`
2. `database/migrations/2026-04-28_networking_contacts_first_last_name.sql`

## Ops notes

- After deploying files and migrations, verify:
  - Networking create/edit form shows `First Name`, `Last Name`, `Company` on the first row.
  - Networking records display full contact names correctly in list/detail pages.
  - Calendar event quick view shows clickable phone when a client phone is present.
