# Live Release 1.9.2.3-beta

Date: 2026-05-21

## Highlights

- **Dashboard:** Payments Due and Profit YTD KPI cards get distinct colors (cyan and green) instead of dark navy.
- **Dashboard:** Profit YTD card matches the same grid size as other KPI cards (no longer full-width).
- **Dashboard:** Semantic color classes for all eight KPI cards so colors stay correct after Estate Sales was added.

## Changed files

- `app/Views/home/index.php`
- `public/assets/css/jt-theme.css`
- `config/app.php` (version `1.9.2.3-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.2.3-beta.md`
- `patches/live-1.9.2.3-kpi-colors.patch` (optional hotfix apply)

## Database migrations in this release

No new database migrations are required for 1.9.2.3-beta.

## Patch-only deploy (FTP / manual upload)

From the project root:

`patch -p1 < patches/live-1.9.2.3-kpi-colors.patch`

Or copy the files listed above from this bundle’s `upload/` tree.

## Ops notes

- Smoke test: Dashboard — confirm all KPI cards show correct colors; Profit YTD is same width as Payments Due in the grid.
