# Google Calendar sync (locked phases)

Phased delivery for JunkTracker ↔ Google Calendar. **Phase 1 is the only scope until it ships.**

## Phase 1 — JunkTracker → Google (one-way push)

- User connects Google (OAuth).
- App creates/updates/deletes **Calendar API events** when corresponding JunkTracker events change.
- Store **Google event IDs** (and minimal sync metadata) on JT rows so updates target the right remote event.
- **Out of scope for Phase 1:** importing from Google, webhooks, conflict resolution beyond “JT wins on write.”

## Phase 2 — Google → JunkTracker (pull)

- Cron (or similar) uses Calendar API **incremental sync** (`syncToken` / `updatedMin`) to apply Google-side changes into JunkTracker.
- Clear rules for deletes vs cancels and for events created only in Google.

## Phase 3 — Optional real-time

- **Push notifications** (Google watch channels) to an HTTPS webhook + channel renewal, if polling is not enough.

---

# Phase 1 — Start here: auth & API keys

Use this checklist to get **OAuth credentials** and **local env** ready before wiring PHP. Implementation will use the official [Google Calendar API](https://developers.google.com/calendar/api/guides/overview) (REST) via the [Google API PHP Client](https://github.com/googleapis/google-api-php-client) (add in a later coding step).

## 1. Google Cloud project

1. Open [Google Cloud Console](https://console.cloud.google.com/).
2. Create a **project** (or pick an existing one), e.g. `junktracker-calendar`.
3. **APIs & Services → Library** — enable **Google Calendar API**.

## 2. OAuth consent screen

1. **APIs & Services → OAuth consent screen**.
2. Choose **External** (unless you only use Workspace and want Internal).
3. Fill **App name**, **User support email**, **Developer contact**.
4. **Scopes** (add for Phase 1; you can start minimal and add later):
   - `https://www.googleapis.com/auth/calendar.events` — create/update/delete events on calendars the user selects.
   - (Optional later) `https://www.googleapis.com/auth/calendar.readonly` if you need read-only listing before Phase 2.
5. **Test users** — while app is in “Testing,” add every Google account that will connect during development.

## 3. OAuth 2.0 Client ID (Web application)

1. **APIs & Services → Credentials → Create credentials → OAuth client ID**.
2. Application type: **Web application**.
3. **Authorized JavaScript origins** (if you use a JS redirect flow; optional for server-only flow):
   - `https://your-production-domain.com`
   - `http://localhost` (and port if needed for local dev).
4. **Authorized redirect URIs** — must match **exactly** what the app will send (scheme, host, path, no trailing slash mismatch):
   - Production example: `https://your-production-domain.com/google/oauth/callback` (final path TBD when routes exist).
   - Local example: `http://localhost/junktracker/google/oauth/callback` (match your MAMP base URL).
5. Save and copy **Client ID** and **Client secret** (never commit them to git).

## 4. Environment variables (app config)

Define these in **server env** or a **local-only** file that is gitignored (same pattern as `config/database.local.php`):

| Variable | Purpose |
|----------|--------|
| `GOOGLE_OAUTH_CLIENT_ID` | OAuth client ID |
| `GOOGLE_OAUTH_CLIENT_SECRET` | OAuth client secret |
| `GOOGLE_OAUTH_REDIRECT_URI` | Single redirect URI string that matches Google Console (optional if hard-coded per env in config) |

**Security:** Treat Client Secret like a password. Production must use **HTTPS** for OAuth redirects.

## 5. Scopes to request in code (Phase 1)

- Minimum for push: **`calendar.events`** (full path above).
- Request **refresh token** on first authorization (`access_type=offline`, `prompt=consent` when you need to re-issue a refresh token during dev).

## 6. What you’ll store after OAuth (later implementation)

- **Per user (or per business + user):** encrypted **refresh token**, optional **access token** + expiry.
- **Per JunkTracker event row:** `google_calendar_id` (which calendar), `google_event_id`, `google_synced_at` / etag if needed.

## 7. Calendar target for Phase 1

- Decide whether to sync into the user’s **primary** calendar or a **dedicated** sub-calendar (“JunkTracker”) created via API once — reduces clutter and accidental edits to personal events. Phase 1 code can default to a chosen calendar ID stored after first connection.

## 8. Composer (when coding starts)

- Add `google/apiclient` to `composer.json` and use **Calendar** service for `events.insert` / `patch` / `delete`.

## 9. Verification before coding

- [ ] Calendar API enabled  
- [ ] OAuth client created with correct **redirect URIs** for dev and prod  
- [ ] Test users added (if app is in Testing)  
- [ ] Client ID / secret available only via env or local untracked config  
- [ ] HTTPS on production  

---

*Next implementation steps (not part of “keys only”): DB migration for tokens + `google_event_id`, OAuth routes (`/google/oauth/start`, `/google/oauth/callback`), and hooks on `Event::create` / `update` / delete for Phase 1 push.*
