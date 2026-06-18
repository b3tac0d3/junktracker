# JunkMetrix Mobile (Expo)

Cross-platform field crew app for JunkMetrix / JunkTracker API v1.

## Setup

```bash
cd mobile
npm install
cp .env.example .env
# Edit EXPO_PUBLIC_API_URL to your server (HTTPS in production)
npm start
```

## Screens

- **Login** — token auth against `/api/v1/auth/login`
- **Today** — dashboard schedule + open punch
- **Punch** — punch in / out
- **Calendar** — 14-day event feed
- **Job detail** — tap-to-call and directions

## Environment

| Variable | Example |
|----------|---------|
| `EXPO_PUBLIC_API_URL` | `https://junkmetrix.com` or `http://192.168.1.10/junktracker` |

## Builds (EAS)

See [docs/mobile/dev-accounts-setup.md](../docs/mobile/dev-accounts-setup.md) and [docs/mobile/beta-distribution.md](../docs/mobile/beta-distribution.md).

```bash
npx eas build --platform all --profile preview
```

## Bundle IDs

- iOS: `com.junkmetrix.app`
- Android: `com.junkmetrix.app`
