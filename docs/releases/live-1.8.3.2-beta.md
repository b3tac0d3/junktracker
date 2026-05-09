# Live Release 1.8.3.2-beta

Date: 2026-05-09

## Highlights

- **Billing:** Estimates created from a quote now load `quote_id` when editing. Previously `Invoice::findForBusiness()` omitted `quote_id` from the query, so the edit form showed empty Job/Quote and the yellow “linkage” warning even though the row was saved correctly.

## Changed files

- `app/Models/Invoice.php` — include `quote_id` in `findForBusiness()` when the `invoices.quote_id` column exists.

## Database migrations in this release

No new database migrations are required for 1.8.3.2-beta.

## Patch-only deploy (FTP / manual upload)

If you deploy only this hotfix without a full bundle:

1. Replace `app/Models/Invoice.php` on the server with the version from this release, **or**
2. From the project root, apply:  
   `patch -p1 < patches/live-1.8.3.2-invoice-quote-id.patch`

## Ops notes

- Smoke test: open a quote-linked estimate → **Edit** — Quote should display and the linkage warning should not appear (for rows that already have `quote_id` in the database).
