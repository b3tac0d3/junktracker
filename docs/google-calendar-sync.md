# Google Calendar & Gmail (appointments)

One-way sync: JunkTracker **Events** and **scheduled Jobs** → the connected user's Google Calendar.

Optional **Gmail** notifications: when enabled in Settings, **appointment** events trigger email sent through the connected Gmail account on create, update, cancel, and delete.

## Phase 1 (current)

- OAuth connect/disconnect in **Settings**
- Push on event create, update, move, cancel, delete
- Push on job create, update, status change; remove on deactivate or when schedule cleared
- Manual backfill: **Sync upcoming (90 days)** from today forward, or **Sync past (365 days)** for history before today (events + scheduled jobs)
- Link table maps JunkTracker events to Google event ids
- Gmail appointment notifications (type = `appointment` only)

## Google Cloud setup

1. Enable **Google Calendar API**
2. OAuth consent screen (External; add test users while in Testing)
3. OAuth Web client with redirect URIs:
   - Local: `http://localhost/junktracker/settings/google-calendar/callback`
   - Live: `https://junktracker.jimmysjunk.com/settings/google-calendar/callback`
4. Scopes under OAuth consent screen → Scopes (not only in code):
   - **Google Calendar API** → `.../auth/calendar`
   - **Gmail API** → `.../auth/gmail.send` (appointment email notifications)
   - User email (`userinfo.email`) if prompted

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

Run migrations:

- `database/migrations/2026-05-26_google_calendar_sync.sql`
- `database/migrations/2026-06-06_gmail_appointment_notify.sql` (Gmail notify preferences)

Tables:

- `user_google_calendar_connections` (includes `appointment_gmail_notify_*` columns)
- `google_calendar_event_links`

## Smoke test

1. Add client secret locally
2. Settings → Connect Google (Calendar + Gmail scopes)
3. Optional: **Sync past (365 days)** then **Sync upcoming (90 days)** to backfill Google Calendar
4. Enable **Send Gmail updates for appointments**; set recipient emails or leave blank for your Google address
5. Create an appointment in Events or a job with a schedule
6. Confirm it appears on Google Calendar (phone refresh)
7. Confirm Gmail notification arrives for create/update/cancel/delete
8. Edit time in JunkTracker → Google Calendar and Gmail update
9. Cancel/delete/deactivate → Google event removed; delete sends cancellation email

## Phase 2 (later)

- Optional: other feed types (deliveries, estate sales, quote follow-ups)

## Not planned yet

- Two-way sync (Google → JunkTracker)
