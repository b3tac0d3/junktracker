# Mass SMS to estate customers (future build)

Saved for when you're ready to implement. Complements existing subscriber fields from **1.9.6.0**.

## Current state (already in app)

- **Opt-in:** `estate_sale_customers.subscribes_to_future_sales`
- **Channel preference:** `future_sales_contact_method` — `call` | `text` | `email`
- **Phone:** `phone` on customer profile (normalized for search/duplicates in `EstateSale`)
- **Email only today:** `Core\Mailer`, cron daily digest pattern — **no SMS provider or send log**

Registration captures *intent*; nothing sends texts yet.

---

## Phase 1 — Compliance & data (do first)

US mass texting is regulated ([TCPA](https://www.fcc.gov/consumers/guides/spam-text-messages-and-robocalls)). Before any sends:

1. **Explicit opt-in** at registration — checkbox + clear language, e.g.  
   *“I agree to receive text messages about estate sales. Msg & data rates may apply. Reply STOP to opt out.”*

2. **Consent metadata** (new columns or table):
   - `sms_opt_in_at`, `sms_opt_in_source` (e.g. `estate_sale_checkin:3`)
   - `sms_opt_out_at` (STOP reply or manual unsubscribe)
   - Optional: who recorded consent

3. **Send only when all true:**
   - `subscribes_to_future_sales = 1`
   - `future_sales_contact_method = 'text'`
   - Valid `phone`
   - Not opted out / not on suppression list

4. **STOP handling** — inbound webhook so “STOP” auto-unsubscribes (required).

---

## Phase 2 — SMS provider (Twilio recommended)

Standard choice for PHP: outbound SMS, inbound replies, delivery callbacks, Messaging Service for bulk.

**Twilio setup:**

1. Account, phone number or Messaging Service
2. **A2P 10DLC** brand + campaign registration (US business texting on local numbers)
3. Config (not in git): `config/sms.local.php` — account SID, auth token, from number / messaging service SID

**Rough cost:** ~$0.008/SMS segment + number fee; 10DLC campaign fees vary.

Alternatives: Vonage, Bandwidth, Plivo — same integration pattern.

---

## Phase 3 — App architecture

Mirror email (`Mailer` + cron):

| Piece | Purpose |
|--------|--------|
| `core/SmsClient.php` | Send one message; normalize phone to E.164 |
| `app/Models/SmsCampaign.php` | Draft, audience filter, schedule |
| `app/Models/SmsMessage.php` | Per-recipient log: pending / sent / failed / delivered / opted_out |
| Admin controller + views | Compose, preview recipient count, send |
| `POST /webhooks/twilio/sms` | Inbound STOP/HELP + delivery status |
| Cron or queue worker | Batch sends (e.g. 50/min) — never bulk-send in a web request |

**Audience filters (examples):**

- All text subscribers (`subscribes` + `text` + valid phone + not opted out)
- Subscribers from a past sale
- Customers on an upcoming estate sale
- Later: tags/categories (`sale_reminder`, `general_promo`)

**UI ideas:**

- **People → Estate Customers** — filter “Text subscribers”
- **Messaging → New campaign** — body, `{first_name}` / `{sale_title}` / `{sale_date}`, preview count
- **Campaign history** — sent/failed counts

**Reuse from codebase:**

- `EstateSale::normalizeCustomerPhone()` / phone digit SQL for audience queries
- Secured cron like `/cron/daily-digest` for outbound queue
- Activity audit log for “who sent campaign X”

---

## Phase 4 — Registration hook

When customer opts in with **text** preference:

1. Save opt-in + `sms_opt_in_at` + source
2. Optional immediate **confirmation SMS:**  
   *“You’re subscribed to [business] estate sale alerts. Reply STOP to unsubscribe.”*

Do **not** text people who only gave a phone without the subscriber checkbox.

---

## Phase 5 — Beyond sale announcements

Message types or tags so customers can subscribe to:

- New sale announcements
- Reminders (sale starts tomorrow)
- General promos

One global **STOP** should stop all marketing texts unless you implement granular opt-outs later.

---

## Suggested build order

1. Migration: consent fields + `sms_messages` (or campaign + message tables)
2. Twilio account + 10DLC + webhook route
3. `SmsClient` + admin “send test SMS” on one customer
4. Campaign UI + batch send via cron
5. STOP webhook + sync `sms_opt_out_at`
6. Message templates

**Effort (rough):** 2–4 days MVP (one business, manual campaigns); +1–2 days for templates, scheduling, reporting.

**Good first slice:** Twilio + consent fields + **“Text subscribers for this sale”** on estate sale detail — proves pipeline before full campaign manager.

---

## Caveats

- Email rules ≠ SMS — separate opt-in, cost, and opt-out flow
- 10DLC approval can take days/weeks — start early
- Webhooks need public HTTPS (live host is fine)
- Batch in background; browser request must not send hundreds of texts

---

## Related files (today)

- `app/Models/EstateSale.php` — subscriber fields, contact method options
- `database/migrations/2026-05-24_estate_sale_customers_subscriber.sql`
- `core/Mailer.php`, `app/Controllers/CronController.php` — patterns to copy
- `app/Views/estate-sales/customer_form.php`, `show.php` — opt-in UI
