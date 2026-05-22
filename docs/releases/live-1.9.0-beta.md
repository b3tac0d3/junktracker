# Live Release 1.9.0-beta

Date: 2026-05-21

## Highlights

### Estate Sales (new module)

- Full CRUD for estate sales with status, schedule, address, and notes.
- Link an estate sale to a **client** (owner / share recipient) with configurable **client percentage** and **split basis** (gross total vs net after expenses).
- **Customers** tab: standalone customer records per sale, paginated list, quick-add, and clickable customer detail pages.
- **Customer queue & check-in:** queue numbers per sale, check-in/check-out, live presence stats (Inside now / Waiting / Total seen), status filters, and visit log history.
- **Sales** tab: record transactions on an estate sale with customer linkage; edit uses the estate sale sale form (not the generic sales edit screen).
- **Expenses** tab: quick-add expenses linked to the sale with seeded expense categories.
- **Labor** tab: assign employees, punch in/out (single and bulk), and link time entries to the estate sale.

### Quotes

- **Actions** menu on quote detail: Edit, Convert to Job, Convert to Estate Sale, Convert to Purchase.
- Converting to estate sale or purchase deactivates linked estimates on the quote.

### Navigation & UI

- Sidebar reorganized into collapsible groups: **People**, **Work**, **Sales**, **Operations**, **Finance**, **Time Tracking**, plus top-level **Reports**.
- Active page auto-expands its parent group.
- Card header primary buttons keep white text; dropdown action menus use aligned icon columns.

### Other

- Time tracking form supports estate sale linkage.
- Events feed includes estate sale activity where applicable.

## Database migrations in this release

Run **one** of the following on the live database (not both):

### Option A — single bundle (recommended)

```text
database/migrations/2026-05-21_live_1.9.0-beta.sql
```

Idempotent. Safe to run once when upgrading from 1.8.x.

### Option B — individual files (same order)

1. `2026-05-21_estate_sales.sql`
2. `2026-05-21_estate_sale_financials.sql`
3. `2026-05-21_estate_sale_client_split_type.sql`
4. `2026-05-21_estate_sales_client_link.sql`
5. `2026-05-21_estate_sale_customers_standalone.sql`
6. `2026-05-21_estate_sale_labor.sql`
7. `2026-05-21_sales_estate_sale_links.sql`
8. `2026-05-21_estate_sale_customer_checkin.sql`
9. `2026-05-21_quotes_conversion_targets.sql`

**Requires MySQL 8+** for the queue-number backfill (`ROW_NUMBER()` window function) in step 8.

## Deploy bundle

Build output (sibling to this repo):

```text
../junktracker_live_releases/junktracker_beta_1.9.0-beta/
  upload/       ← rsync/scp to web root
  migrations/   ← run SQL here (not on the web server)
```

## Ops notes

1. Run the migration **before** or **immediately after** uploading files (either order works; migration is additive).
2. Merge `config/app.php` on the server — version should read `1.9.0-beta`.
3. Smoke test:
   - Sidebar groups expand/collapse; Estate Sales link opens under **Sales**.
   - Create an estate sale, add a customer, check in/out, record a sale.
   - Open a quote → **Actions** → Convert to Estate Sale (or Purchase).
   - Edit an estate sale transaction from the sale detail page.

## Rollback

Redeploy the prior upload bundle and previous `config/app.php` version string. Schema changes are forward-only; do not drop new tables/columns on production unless you have a deliberate rollback script.
