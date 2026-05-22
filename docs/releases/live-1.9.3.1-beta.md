# Live Release 1.9.3.1-beta

Date: 2026-05-22

## Highlights

- **Beta-live = production:** Non-localhost hostnames auto-set `env` to `production` and `debug` to false — invite/billing mail works on live without manual config.
- **App URL:** Live `app.url` is derived from the current request (scheme, host, path) so invite emails contain the correct login link.
- **Mail fallback:** Real mail transport on any non-localhost host, even if env were overridden.
- **Local dev unchanged:** localhost / 127.0.0.1 still use log-only mail and debug mode.

## Changed files

- `config/app.php`
- `config/mail.php`
- `app/Controllers/AdminUsersController.php`
- `app/Views/admin/users/form.php`
- `app/Views/admin/users/index.php`
- `docs/deploy-checklist.md`
- `config/app.php` (version `1.9.3.1-beta`)
- `docs/releases/live-1.9.3.1-beta.md`

## Database migrations in this release

No new database migrations are required for 1.9.3.1-beta.

## Ops notes

- Smoke test: Admin → Users — log-only warning should be absent on live; present on localhost only.
- Smoke test: Create user on live — invite email should send with correct login URL.
