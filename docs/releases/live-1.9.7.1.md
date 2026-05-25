# Live Release 1.9.7.1

Date: 2026-05-25

Dev checkpoint — not deployed to live yet.

## Highlights

- **Purchase Quotes:** New pipeline (`/purchase-quotes`) with offer history, contact log, convert to purchase / mark lost, and calendar follow-ups.
- **Calendar:** Click a time slot in day/week view to pick appointment type and open the create form with date/time pre-filled; new `/events/create` form for appointments.
- **Events nav:** Top-level sidebar link directly under Dashboard.
- **Deliveries from calendar:** Defaults to **Scheduled** with the clicked time when opened from the calendar.
- **Client details:** **Last Contact** summary on the Details tab.

## Database migrations in this release

Run before deploy (idempotent):

1. `database/migrations/2026-05-25_purchase_quotes.sql`

## Related

- Prior live release: [live-1.9.7.0.md](./live-1.9.7.0.md)
