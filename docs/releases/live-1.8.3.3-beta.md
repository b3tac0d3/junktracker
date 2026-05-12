# Live Release 1.8.3.3-beta

Date: 2026-05-12

## Highlights

- **Navigation:** **Add Quote** in the header quick-add (+) menu (`/quotes/create`).
- **Client record:** **Add Quote** in the client **Actions** menu, with `client_id` prefilled.

## Changed files

- `app/Views/layouts/main.php`
- `app/Views/clients/show.php`
- `config/app.php` (version `1.8.3.3-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.8.3.3-beta.md`
- `patches/live-1.8.3.3-add-quote-menus.patch` (optional hotfix apply)

## Database migrations in this release

No new database migrations are required for 1.8.3.3-beta.

## Patch-only deploy (FTP / manual upload)

From the project root:

`patch -p1 < patches/live-1.8.3.3-add-quote-menus.patch`

Or copy the files listed above from this bundle’s `upload/` tree.

## Ops notes

- Smoke test: header **+** menu and client **Actions** — **Add Quote** opens the quote form; use the client menu for a prefilled `client_id`.
