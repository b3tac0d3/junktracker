# Live Release 1.9.2.4-beta

Date: 2026-05-21

## Highlights

- **Estate sales:** Gross = total on-site sales; net = our share after the agreed client split. Dashboard, index, records, detail, and income report all use split-based net (shows **—** when split is not configured).
- **Dashboard:** Estate Sales KPI and monthly chart use split-based net totals instead of raw transaction sums.
- **UI:** Outline buttons on light card headers (Full reports, Add delivery, View all) now use dark readable text instead of white-on-light.

## Changed files

- `app/Controllers/EstateSalesController.php`
- `app/Models/DashboardSummary.php`
- `app/Models/EstateSale.php`
- `app/Models/ReportSummary.php`
- `app/Views/estate-sales/index.php`
- `app/Views/estate-sales/records.php`
- `app/Views/estate-sales/show.php`
- `app/Views/home/index.php`
- `app/Views/reports/income.php`
- `public/assets/css/jt-theme.css`
- `config/app.php` (version `1.9.2.4-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.2.4-beta.md`

## Database migrations in this release

No new database migrations are required for 1.9.2.4-beta.

## Ops notes

- Smoke test: Dashboard — estate sales KPI gross/net; outline header buttons readable on colored cards.
- Smoke test: Estate sale detail — financial summary shows Gross and Net; records list Net column uses split.
- Smoke test: Income report — Estate Sales section gross/net match dashboard logic.
