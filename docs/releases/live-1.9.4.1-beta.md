# Live Release 1.9.4.1-beta

Date: 2026-05-22

## Highlights

- **Login only:** Email/password fields on the login page use standard `autocomplete="username"` and `autocomplete="current-password"` so Dashlane and other password managers fill credentials there only.
- **App forms:** Authenticated pages suppress password-manager autofill on all other forms (search, clients, estate sales, etc.) via `autocomplete="off"` and manager-specific ignore attributes.

## Changed files

- `app/Views/auth/login.php`
- `app/Views/layouts/main.php`
- `public/assets/js/app.js`
- `config/app.php` (version `1.9.4.1-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.4.1-beta.md`

## Database migrations in this release

No new database migrations are required for 1.9.4.1-beta.

## Ops notes

- Hard refresh after deploy so updated `app.js` loads.
- Smoke test: login page — password manager offers saved credentials; client/estate sale forms — no credential autofill on name/email fields.
