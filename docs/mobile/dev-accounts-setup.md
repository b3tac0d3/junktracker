# Developer accounts & store setup

Checklist before TestFlight or Google Play internal testing.

## Bundle identifiers (pick once)

| Platform | Recommended ID |
|----------|----------------|
| iOS | `com.junkmetrix.app` |
| Android | `com.junkmetrix.app` |

Expo config: see [`mobile/app.json`](../../mobile/app.json).

## Accounts to register

| Service | Cost | URL |
|---------|------|-----|
| Apple Developer Program | $99/year | https://developer.apple.com/programs/ |
| Google Play Console | $25 one-time | https://play.google.com/console |
| Firebase (FCM push) | Free tier | https://console.firebase.google.com |

## Apple Developer

1. Enroll as Organization or Individual.
2. Create App ID `com.junkmetrix.app` with Push Notifications capability.
3. Create APNs key in Certificates, Identifiers & Profiles → Keys.
4. Upload APNs key to Firebase (Project Settings → Cloud Messaging → Apple app configuration).
5. Create App Store Connect app record matching bundle ID.

## Google Play

1. Create developer account and complete identity verification.
2. Create app → Internal testing track first.
3. Link Firebase Android app (`google-services.json`) via EAS or Expo config plugin.

## Firebase project

1. Create project `junkmetrix` (or similar).
2. Add iOS app (`com.junkmetrix.app`) and Android app (`com.junkmetrix.app`).
3. Copy **Server key** (legacy) or set up **FCM HTTP v1** service account for [`PushNotificationService.php`](../../app/Services/PushNotificationService.php).
4. Set on server: `JUNKMETRIX_FCM_SERVER_KEY=...` or `config/api.local.php`.

## Legal URLs (required for stores)

Host these on your marketing domain before public submission:

| Document | Suggested URL |
|----------|---------------|
| Privacy policy | `https://junkmetrix.com/privacy` |
| Terms of service | `https://junkmetrix.com/terms` |
| Support | `mailto:support@junkmetrix.com` or `/support` |

### Privacy policy must cover

- Account email and name
- Business/client contact data (names, phones, addresses)
- Job site locations and schedules
- Employee time punch timestamps
- Device push tokens
- Data retention and deletion request process

Template outline: [`privacy-policy-outline.md`](./privacy-policy-outline.md)

## OAuth (Google Calendar)

When mobile adds calendar sync (v2), register iOS/Android OAuth clients in Google Cloud Console with redirect URIs matching Expo auth session.

## Mac for builds

Optional if using **Expo EAS Build** (cloud). Local iOS builds require macOS + Xcode.

```bash
cd mobile
npm install
npx eas login
npx eas build:configure
npx eas build --platform ios --profile preview
npx eas build --platform android --profile preview
```
