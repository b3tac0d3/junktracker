# JunkMetrix Mobile API v1

REST JSON API for field crew mobile apps. All endpoints are prefixed with `/api/v1`.

## Authentication

Bearer tokens (opaque, stored hashed server-side). No CSRF on API routes.

```
POST /api/v1/auth/login
POST /api/v1/auth/refresh
POST /api/v1/auth/logout
GET  /api/v1/auth/me          (Authorization: Bearer …)
```

### Login request

```json
{
  "email": "user@example.com",
  "password": "secret",
  "device_name": "iPhone 15"
}
```

### Login response

```json
{
  "ok": true,
  "data": {
    "access_token": "...",
    "refresh_token": "...",
    "token_type": "Bearer",
    "expires_at": "2026-07-16 12:00:00",
    "user": { "id": 1, "email": "...", "display_name": "..." },
    "business": { "id": 1, "name": "...", "timezone": "America/New_York" },
    "workspace_role": "punch_only",
    "module_flags": { "estate_sales": true, "purchases": true },
    "label_job": "Job"
  }
}
```

## Field crew endpoints

| Method | Path | Roles |
|--------|------|-------|
| GET | `/api/v1/dashboard/today` | punch_only, general_user, admin |
| GET | `/api/v1/punch-board` | punch_only, general_user, admin |
| POST | `/api/v1/punch/in` | punch_only, general_user, admin |
| POST | `/api/v1/punch/out` | punch_only, general_user, admin |
| POST | `/api/v1/punch/switch` | punch_only, general_user, admin |
| GET | `/api/v1/jobs` | punch_only, general_user, admin |
| GET | `/api/v1/jobs/{id}` | punch_only, general_user, admin |
| POST | `/api/v1/jobs/{id}/status` | general_user, admin |
| GET | `/api/v1/events/feed?start=&end=` | punch_only, general_user, admin |
| GET | `/api/v1/notifications` | general_user, admin |
| GET | `/api/v1/search/jobs?q=` | punch_only, general_user, admin |
| GET | `/api/v1/search/clients?q=` | general_user, admin |

## Push notifications

```
POST /api/v1/device-tokens/register
POST /api/v1/device-tokens/unregister
```

Set `JUNKMETRIX_FCM_SERVER_KEY` or `config/api.local.php` → `fcm_server_key` for FCM delivery.

## Errors

```json
{ "ok": false, "error": "Message", "errors": { "field": "Detail" } }
```

HTTP status: `401` unauthorized, `403` forbidden, `422` validation, `503` migrations missing.

## CORS

Configure allowed origins in `config/api.local.php`:

```php
<?php
return [
    'cors_origins' => ['https://app.junkmetrix.com'],
];
```

Localhost dev allows `*` automatically when `app.env` is `local`.

## Migrations

Run in order on deploy:

1. `database/migrations/2026-06-16_api_tokens.sql`
2. `database/migrations/2026-06-16_device_tokens.sql`
3. `database/migrations/2026-06-16_business_module_flags.sql`
