# Live Release 1.9.4.2-beta

Date: 2026-05-21

## Highlights

- **Estate sale sales:** Customer is optional (recommended) when adding or editing a sale — walk-up or unknown buyers no longer block saving a transaction.
- If a customer is selected, validation still ensures they belong to that estate sale.

## Changed files

- `app/Controllers/EstateSalesController.php`
- `app/Views/estate-sales/sale_form.php`
- `config/app.php` (version `1.9.4.2-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.4.2-beta.md`

## Database migrations in this release

No new database migrations are required for 1.9.4.2-beta.

## Ops notes

- Smoke test: add an estate sale sale with no customer selected — should save successfully.
- Smoke test: add a sale with a linked customer — should still save and show customer on the sale.
