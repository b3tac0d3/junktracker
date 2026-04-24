# Live Release 1.7.0

Release date: 2026-04-24

## Summary

- Added a dedicated Quotes workflow (separate from Jobs) with its own index, form, detail page, and convert-to-job action.
- Added quote-aware estimates: billing records can link to a quote and convert approved estimates to invoices from a single action.
- Improved mobile/operator workflow updates:
  - Mobile header search dropdown in top nav.
  - Client create "Next Step" selector to jump directly to Add Job or Add Quote.
  - Clickable addresses on key detail pages opening Google Maps directions.

## Database

- Run exactly one migration for this release:
  - `database/migrations/2026-04-24_live_1.7.0.sql`

