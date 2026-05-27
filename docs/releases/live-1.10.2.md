# Live Release 1.10.2

Date: 2026-05-26

Release after [1.10.1.1](./live-1.10.1.1.md). Google Calendar sync, event editing, and calendar history for converted quotes.

## Highlights

- **Google Calendar sync (one-way):** Connect in Settings; push appointments and scheduled jobs to Google Calendar; backfill upcoming 90 days.
- **Events:** Edit and delete from the event detail page; changes sync to Google when connected.
- **Jobs:** Scheduled jobs sync to Google on create, update, status change; removed on deactivate.
- **Calendar history:** Converted quotes and purchase quotes stay on the calendar; cancelled jobs hidden. Follow-up dates preserved on conversion.

## Changed files

- Google Calendar: `config/google.php`, OAuth controllers/models/services, `core/GoogleCalendarApi.php`, `core/TokenCipher.php`
- `app/Controllers/EventsController.php`, `JobsController.php`, `SettingsController.php`, `GoogleCalendarController.php`
- `app/Models/EventFeed.php`, `Job.php`, `Quote.php`, `PurchaseQuote.php`, Google Calendar models
- `app/Views/events/show.php`, `events/form.php`, `settings/edit.php`
- `app/helpers.php`, `routes/web.php`, `.gitignore`
- `config/app.php` (version `1.10.2`)
- `docs/google-calendar-sync.md`

## Database migrations in this release

Run on live **before** or immediately after upload:

`database/migrations/2026-05-26_google_calendar_sync.sql`

## Server config (required for Google Calendar)

Create `config/google.local.php` on the server (not in git):

```php
<?php

return [
    'client_secret' => 'YOUR_CLIENT_SECRET',
];
```

Add OAuth redirect URI in Google Cloud: `https://junktracker.jimmysjunk.com/settings/google-calendar/callback`

## Build live bundle

Delta from 1.10.1.1:

```bash
./scripts/build-live-release.sh junktracker_live_1.10.2 v1.10.1.1
```

Output: `junktracker_live_releases/junktracker_live_1.10.2/upload/` and `…/migrations/`.

## Ops notes

- **Hard refresh** after deploy.
- **Migration** required (Google Calendar tables).
- **Smoke test:** Settings → Connect Google Calendar; create/edit event; create scheduled job; convert a quote — original appointment stays on calendar.
- Purchases and sales are not on the calendar (by design).

## Related

- Plan: [google-calendar-sync.md](../google-calendar-sync.md)
- Prior release: [live-1.10.1.1.md](./live-1.10.1.1.md)
