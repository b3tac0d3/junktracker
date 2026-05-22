# Live Release 1.9.4.3-beta

Date: 2026-05-21

## Highlights

- **Activity log:** New **Admin → Activity Log** page for workspace admins — paginated, searchable history of user actions.
- **Filters:** By user, single day, record type (clients, jobs, invoices, etc.), plus free-text search.
- **Quick views:** Today, full log for a date, or one user's complete history.
- **Auditing:** Adds/edits/deletes now logged across clients, jobs, estate sales, sales, expenses, purchases, tasks, events, deliveries, networking, billing, quotes, deposits, time entries, and admin settings (users, employees, business details, form options, invoice item types). Login/logout and existing billing audit entries continue to appear.

## Changed files

- `app/Models/AuditLog.php` — list/count/query methods
- `app/helpers.php` — `audit()`, labels, entity URLs
- `app/Controllers/AdminActivityLogController.php` (new)
- `app/Views/admin/activity-log/index.php` (new)
- `app/Views/admin/index.php`, `routes/web.php`, `public/assets/css/jt-theme.css`
- CRUD controllers (clients, jobs, estate sales, sales, expenses, purchases, tasks, events, deliveries, networking, admin*)
- `config/app.php` (version `1.9.4.3-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.4.3-beta.md`

## Database migrations in this release

No new database migrations are required for 1.9.4.3-beta. Uses existing `activity_logs` table.

## Ops notes

- Admin-only: general users do not see Activity Log.
- Smoke test: make a change as a non-admin user, then review it in **Admin → Activity Log** filtered by that user or today's date.
- Only actions after deploy are captured; prior history includes billing/quotes/login entries already logged.
