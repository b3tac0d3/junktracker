# Live Release 1.11.0

Date: 2026-06-05

Patch on [1.10.4.0](./live-1.10.4.0.md).

## Highlights

- **Personal time:** Block calendar slots so appointments cannot be booked during personal time; visible on Events calendar with sync to Google.
- **Nav UI:** Modern quick-add menu and user account dropdown with colored icons and profile header.
- **Gmail notifications:** Clearer on/off switch for appointment Gmail updates in Settings (with status badge and migration warning).
- **Google Calendar:** Settings polish; keep `config/google.local.php` on server for OAuth secret (never commit secrets).

## Database migrations in this release

None new. If Gmail notify toggle does not save, ensure this is already applied from 1.10.4.0:

- `2026-06-06_gmail_appointment_notify.sql`

## Build live bundle

Delta from 1.10.4.0:

```bash
./scripts/build-live-release.sh live v1.10.4.0
```

Output: `junktracker_live_releases/live/upload/` (replaces any prior live drop).

## Ops notes

- **Google:** `config/google.local.php` with `client_secret` on live; add Google account as OAuth **test user** while app is in Testing.
- **Hard refresh** after deploy.
- **Smoke test:** Events → book personal time → try appointment in same slot (blocked); Settings → Gmail notify toggle saves On/Off.

## Related

- Prior release: [live-1.10.4.0.md](./live-1.10.4.0.md)
