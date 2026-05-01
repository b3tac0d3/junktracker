# Live Release 1.8.1-beta

Date: 2026-05-01

## Highlights

- Added quote scheduling time support via `Quote Date & Time` (`datetime-local`) on the quote form.
- Added quotes as first-class events on the Events calendar feed with their own source filter and dedicated color.
- Added quick status updates on quote detail pages, mirroring the jobs quick-status workflow.
- Expanded quote details with linked client context: clickable client name and clickable phone number.

## Database migrations in this release

No new database migrations are required for 1.8.1-beta.

## Ops notes

- Verify quote detail page quick-status dropdown changes status immediately.
- Verify quote date/time entries render on calendar and can be filtered with the new `Quotes` source toggle.
- Verify quote detail `Client` opens the client record and `Phone` opens dialer on mobile.
