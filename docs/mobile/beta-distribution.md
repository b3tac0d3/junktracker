# Beta distribution (TestFlight & Play Internal)

Ship to your crew before public App Store / Play Store launch.

## Prerequisites

- [ ] API v1 deployed on HTTPS staging or production
- [ ] Migrations applied (`api_tokens`, `device_tokens`, `business_module_flags`)
- [ ] Apple Developer + Google Play accounts ([dev-accounts-setup.md](./dev-accounts-setup.md))
- [ ] Expo app builds successfully (`mobile/`)

## iOS — TestFlight

1. **Build**
   ```bash
   cd mobile
   npx eas build --platform ios --profile preview
   ```
2. **Submit to App Store Connect**
   ```bash
   npx eas submit --platform ios
   ```
3. App Store Connect → TestFlight → Internal Testing
4. Add testers (Apple ID emails, up to 100 internal)
5. Testers install **TestFlight** app → accept invite → install JunkMetrix

### Internal test checklist

- [ ] Login with punch_only crew account
- [ ] Punch in / out / switch job
- [ ] Today schedule loads
- [ ] Job detail → tap phone / directions
- [ ] Calendar feed (14-day window)
- [ ] Logout and token refresh

## Android — Internal testing

1. **Build**
   ```bash
   npx eas build --platform android --profile preview
   ```
2. **Upload AAB**
   ```bash
   npx eas submit --platform android
   ```
   Or upload manually in Play Console → Release → Internal testing.
3. Create internal tester list (email addresses)
4. Share opt-in link with crew

## Staging API URL

Set in `mobile/constants/config.ts` or `.env`:

```
EXPO_PUBLIC_API_URL=https://staging.junkmetrix.com
```

For local MAMP on device/simulator:

```
EXPO_PUBLIC_API_URL=http://YOUR_LAN_IP/junktracker
```

## Feedback loop

1. Crew uses app for 1–2 weeks on real jobs
2. Collect: login friction, punch UX, offline gaps, wrong job list
3. Fix API/app → increment `version` in `mobile/app.json` → new build
4. Repeat until stable → promote to **External TestFlight** (up to 10,000) or **Play closed testing**

## Public release (after beta)

- App Store: complete metadata, screenshots, privacy URL, review notes explaining field-crew use
- Play Store: content rating questionnaire, data safety form
- Avoid “website wrapper” rejection — native navigation in Expo app satisfies this

## Versioning

- Mobile app version: `mobile/app.json` → `expo.version`
- API: backward-compatible changes only within `/api/v1`; breaking changes → `/api/v2`
