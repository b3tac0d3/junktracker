# Live Release 1.13.0

Date: 2026-06-16

Release on [1.12.3](./live-1.12.3.md).

## Highlights

- **Release history:** In-app `/releases` page — last 10 builds with updates, bug fixes, and patches parsed from `docs/releases/`; click version in footer or user menu.
- **Business locations:** Admin → Business Details → Locations — stores, warehouses, terminals; employee default location on profiles.
- **Client follow-ups:** Dashboard reminders for clients needing follow-up; dismiss/snooze support.
- **Dev tracker:** Submission log timeline, company bug reports and update requests with screenshots, image lightbox, quick status actions.
- **Site admin:** Platform dashboard with business stats; internal context bar when switching workspace vs dev area.
- **Clients:** Quick-add flow, improved duplicate/search match types, follow-up reminder integration.
- **Mobile API v1:** Bearer-token REST API for field crew (punch, jobs, calendar, dashboard) under `/api/v1/*`.
- **Mobile web / PWA:** Installable manifest, service worker shell cache, punch board mobile polish.
- **Company submissions:** Bug reports and update requests workflow for business admins (feeds dev tracker).
- **Employees / admin:** Location defaults on employee forms; user and employee admin polish.
- **Google Calendar:** Address verification and sync improvements.

## Database migrations in this release

Run in filename order on live after deploy:

1. `2026-06-14_client_follow_up_reminders.sql`
2. `2026-06-14_client_follow_up_reminders_dismissed.sql`
3. `2026-06-14_dev_tracker_submissions_log.sql`
4. `2026-06-15_business_locations.sql`
5. `2026-06-16_api_tokens.sql`
6. `2026-06-16_device_tokens.sql`
7. `2026-06-16_business_module_flags.sql`

## Build live bundle

Delta from 1.12.3:

```bash
./scripts/build-live-release.sh live v1.12.3
```

Output: `junktracker_live_releases/live/upload/` and `…/migrations/`.

## Ops notes

- Run all seven migrations before using locations, follow-up reminders, dev submission log, or mobile API.
- **Hard refresh** after deploy (PWA manifest, CSS, JS cache bust via version).
- Mobile API is optional for web-only users; set `JUNKMETRIX_FCM_SERVER_KEY` only when enabling push.
- **Smoke test:** `/releases` loads; Admin → Locations; client follow-up on dashboard; punch board on phone; `POST /api/v1/auth/login` if using mobile app.

## Related

- Prior release: [live-1.12.3.md](./live-1.12.3.md)
- Mobile API: [docs/mobile/api-v1.md](../mobile/api-v1.md)
