# Live Release 1.9.3.0-beta

Date: 2026-05-21

## Highlights

- **User invites:** Admin flash messages now distinguish log-only mail from real delivery failures (no more false “invite email sent” when nothing was emailed).
- **Mail config:** Support `config/mail.local.php` and `JUNKTRACKER_MAIL_TRANSPORT` env override so production mail settings survive deploys without editing committed files.
- **Admin UI:** Warning banner on Users pages when mail transport is log-only (dev mode).
- **Deliverability:** PHP `mail()` sends with `-f` from address when configured.

## Server setup (required for invite emails)

Invite emails only send when mail transport is `mail`, not `log`. On the live server, either:

1. Set `'env' => 'production'` in `config/app.php` (and correct `url`), **or**
2. Create `config/mail.local.php`:
   ```php
   <?php
   return ['transport' => 'mail'];
   ```

Check `storage/logs/mail-*.log` after creating a user to confirm delivery attempts.

## Changed files

- `app/Controllers/AdminUsersController.php`
- `app/Views/admin/users/form.php`
- `app/Views/admin/users/index.php`
- `config/mail.php`
- `core/Mailer.php`
- `.gitignore`
- `scripts/build-live-release.sh`
- `config/app.php` (version `1.9.3.0-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.3.0-beta.md`

## Database migrations in this release

No new database migrations are required for 1.9.3.0-beta.

## Ops notes

- Smoke test: Admin → Users — confirm warning absent when `env=production` or `mail.local.php` is set.
- Smoke test: Create user — flash should say invite sent (production) or explain log-only mode (dev).
- Ensure live `config/app.php` has correct `url` so invite emails contain the right login link.
