# Live Release 1.9.6.1

Date: 2026-05-24

## Highlights

- **Metrics — employee labor (patch):** Ensures `metrics_tab.php` is deployed so the Metrics tab shows **Employee labor** (total owed per employee, daily labor totals, per-day breakdown). Sites that applied the 1.9.6.0 delta from before 1.9.5.1-beta were missing this view file.

## Changed files

- `app/Views/estate-sales/metrics_tab.php` — employee labor tables (included in upload bundle for this patch)
- `config/app.php` (version `1.9.6.1`)

## Database migrations in this release

None.

## Ops notes

- Upload **`app/Views/estate-sales/metrics_tab.php`** and merge **`config/app.php`** (version `1.9.6.1`).
- **Smoke test:** Estate sale → **Metrics** — confirm **Employee labor** section appears between profit summary and charts (when time entries exist).

## Related

- Full 1.9.6.0 feature set: [live-1.9.6.0.md](./live-1.9.6.0.md)
