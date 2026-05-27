# Google Calendar sync

One-way sync: JunkTracker **Events** and **scheduled Jobs** → the connected user's Google Calendar.

## Phase 1 (current)

- OAuth connect/disconnect in **Settings**
- Push on event create, update, move, cancel, delete
- Push on job create, update, status change; remove on deactivate or when schedule cleared
- Manual backfill for upcoming 90 days (events + jobs)
- Link table maps JunkTracker events to Google event ids

## Google Cloud setup

1. Enable **Google Calendar API**
2. OAuth consent screen (External; add test users while in Testing)
3. OAuth Web client with redirect URIs:
   - Local: `http://localhost/junktracker/settings/google-calendar/callback`
   - Live: `https://junktracker.jimmysjunk.com/settings/google-calendar/callback`
4. Scopes: add **Google Calendar API → `.../auth/calendar`** (and email if prompted) under OAuth consent screen → Scopes, not only in code.

## Server config

Create `config/google.local.php` (gitignored):

```php
<?php

return [
    'client_secret' => 'YOUR_CLIENT_SECRET',
    // Optional:
    // 'calendar_id' => 'primary',
    // 'token_encryption_key' => 'long-random-string',
];
```

`config/google.php` stores the public client id.

## Database

Run migration:

`database/migrations/2026-05-26_google_calendar_sync.sql`

Tables:

- `user_google_calendar_connections`
- `google_calendar_event_links`

## Smoke test

1. Add client secret locally
2. Settings → Connect Google Calendar
3. Create an appointment in Events or a job with a schedule
4. Confirm it appears on Google Calendar (phone refresh)
5. Edit time in JunkTracker → Google updates
6. Cancel/delete/deactivate → Google event removed

## Phase 2 (later)

- Optional: other feed types (deliveries, estate sales, quote follow-ups)

## Not planned yet

- Two-way sync (Google → JunkTracker)
