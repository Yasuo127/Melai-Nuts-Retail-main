# Melai Nuts platform: mobile app + admin website

This repository now holds both parts of the platform:

```
melai-nuts/
├── lib/ android/ ios/ web/ ...   Flutter app (customers, staff, owner)       ← unchanged structure
├── supabase/                     Shared database: schema, migrations, tests   ← THE source of truth
│   ├── migrations/20261009000000_admin_portal.sql    (new)
│   └── tests/admin_portal_test.sql, local_supabase_stub.sql (new)
├── functions/                    Firebase Cloud Functions (sign-in policy, role claim)
├── firestore.rules               Firestore rules (account/role records only)
└── admin/                        Laravel 12 admin website (was melai-admin.zip)
```

## 1. How the two apps work together

```
 Flutter app ──(Firebase ID token)──►  Supabase API (PostgREST)  ─┐
                                                                   ├─► Supabase Postgres
 Admin website ──(melai_admin_portal role, SET ROLE authenticated   │   (orders, payments, refunds,
                  + linked staff UID)──► Postgres directly ─────────┘    stock, products, deliveries,
                                                                         loyalty, staff, audit log)
```

* **One database, one set of rules.** Before, the admin website read made-up data and saved its
  own copies of refund decisions, COD confirmations and loyalty points in a local SQLite file —
  a second source of truth. Now every business read and write goes to the app's Supabase
  database through the **same SQL functions and Row Level Security** the app uses
  (`staff_confirm_payment`, `staff_review_refund`, `staff_set_product_active`, …) plus a few new
  ones for the website. So a change made on either side is the same row the other side reads.
  Nothing is synchronised or copied.
* **Who the website acts as.** Each website login is linked to the person's account in the app
  (`staff_members.firebase_uid`). Every request runs inside a transaction that sets that UID as
  the JWT `sub` and switches to the `authenticated` role — exactly what Supabase does for the
  app. Owners see all branches; staff see only their branch and permissions. The database login
  the website uses (`melai_admin_portal`) has **no privileges of its own**.
* **What stays local to the website** (its SQLite/MySQL database): website logins, sessions,
  access/activation flags, the link to the app account, and the website's own activity log.
* **Duplicates and races** are prevented by the database: rows are locked, state machines refuse
  invalid moves (e.g. paying twice, approving a refund twice), and loyalty adjustments carry a
  per-form request key so a double-submit applies once.

### Services you run

| Service | What | Where it runs |
|---|---|---|
| Supabase project | Postgres + API + Edge Functions (HitPay) | Supabase cloud (already in use) |
| Firebase | Sign-in, role records, Cloud Functions | Firebase (already in use) |
| Flutter app | Android/iOS/web build | Devices |
| **Admin website** | `admin/` Laravel app | Any PHP 8.2 host (or `php artisan serve` locally) |

The website does not talk to Firebase and needs no Firebase keys, service-account file,
Supabase `service_role` key or JWT secret.

## 2. Setup

### A. Database (once)

1. Run the new migration in the Supabase SQL editor (after all earlier ones):
   `supabase/migrations/20261009000000_admin_portal.sql`. It is idempotent and does not change
   or delete existing data. Default loyalty earn rate stays 1 point per ₱50.
2. Give the website's database login a password (SQL editor; keep it out of Git):
   ```sql
   alter role melai_admin_portal with login password '<long random password>';
   ```
3. Connection string: Supabase dashboard → **Connect** → *Session pooler*. Use user
   `melai_admin_portal.<project-ref>` with the password above (or the direct connection with
   user `melai_admin_portal` if your host supports IPv6).
4. Make sure the owner exists in `staff_members` (the existing one-time bootstrap in
   `supabase/migrations/20260930000000_staff_backend.sql`), and note the owner's Firebase UID.

### B. Admin website

```bash
cd admin
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
# edit .env: ADMIN_EMAIL (= the owner's email in the app), ADMIN_PASSWORD, ADMIN_STAFF_UID,
#            DATA_SOURCE=supabase, SUPABASE_DB_* from step A3
php artisan migrate --seed       # creates the first admin and links it to the owner
npm run build
php artisan serve                # http://127.0.0.1:8000
```

Then, on **Users**, create website logins for staff (same email as their app account) and press
**Link**. Staff must first be registered in the app's User Management.

### C. Flutter app

No new configuration. Rebuild as before:
```bash
flutter run --dart-define-from-file=env/firebase.json --dart-define-from-file=env/supabase.json
```

## 3. What is integrated

| Area | Status | Notes |
|---|---|---|
| Orders & payments | ✅ Live | Payments page lists real orders; **Mark as paid** for cash calls `staff_confirm_payment` (customer notified). Online payments stay automatic via HitPay. |
| Refunds | ✅ Live | Cash: approve = completed in one step. Online: approve → refund in HitPay dashboard → **Mark refunded** with reference. Reject needs a reason, sent to the customer. Stock, points clawback and payment status are handled by the existing triggers. Amount comes from the customer's request (cannot be changed on the website). |
| Loyalty | ✅ Live | Members/balances/history from the ledger; owners change earn rate, redeem rate and max discount (used by checkout immediately) and add/deduct points (appended to the ledger, customer notified, audited). |
| Dashboard | ✅ Live | Sales vs. target per branch, stock health, deliveries, refunds, loyalty totals. |
| Sales | ✅ Live | `owner_sales_summary` for owners; own branch for staff. |
| Products | ✅ Live | Full catalog + stock; admins hide/show products (`staff_set_product_active`). Editing details stays in the app. |
| Inventory | ✅ Live (read) | Stock per branch/variant, thresholds, stock movements. Receiving/adjusting/transfers stay in the app (batch/FEFO screens). |
| Deliveries | ✅ Live (read) | Runs and stops created/dispatched in the app. |
| Users & roles | ✅ | Website logins linked to app accounts and verified against Supabase (active, same email, admin↔owner, staff↔staff). Deactivating in the app blocks the website at once. |
| Activity logs | ✅ | Website log + the app's append-only `staff_audit_logs`. |
| Driver map | ⚠️ Branch pins only | The app does not record rider GPS. |
| Reports page | ⚠️ Placeholder | Use Sales. |
| Points expiry | ❌ Not available | The app has no expiry; the setting was removed in live mode rather than faked. |
| PayMongo | Mock mode only | The app uses HitPay; PayMongo code is kept for `DATA_SOURCE=mock`. |
| Customer app | ✅ | "Earn 1 pt per ₱X" now shows the owner's setting (`get_loyalty_earn_rate`). |

`DATA_SOURCE=mock` still runs the original demo (labelled **Demo data** in the top bar).

## 4. Database changes (`20261009000000_admin_portal.sql`)

* `loyalty_cart_settings.earn_pesos_per_point` (default 50); `compute_points_earned()` reads it.
* `loyalty_transactions.source` also accepts `admin_adjust` (constraint widened, nothing removed).
* Read functions: `admin_portal_orders`, `admin_portal_stock`, `admin_portal_products`,
  `admin_portal_stock_movements`, `admin_portal_loyalty_summary`, `admin_portal_loyalty_members`,
  `admin_portal_loyalty_member`, `admin_portal_loyalty_settings`, `admin_portal_audit_logs`,
  `admin_portal_staff_directory`, `get_loyalty_earn_rate` (public).
* Writes: `admin_portal_review_refund` (wraps `staff_review_refund`), `owner_adjust_loyalty_points`,
  `owner_update_loyalty_settings`. All check the caller's staff row, lock rows and write the audit log.
* Role `melai_admin_portal` (NOINHERIT, member of `authenticated` only).

Also fixed: `supabase/schema.sql` repeated the delivery module, which references `staff_members`
before it exists, so a fresh install stopped with an error. The block now lives only in
`migrations/20261002010000_delivery_module.sql` (identical content). Existing databases are unaffected.

## 5. Website routes added

`GET /s/{sales|products|inventory|deliveries}` (live pages), `POST /products/{id}/toggle-active`,
`POST /admin/refunds/{order}/complete`, `POST /admin/users/{user}/link|unlink`. Artisan:
`php artisan melai:link-staff {email} {uid} [--unlink]`. The PayMongo webhook returns 404 in live mode.

## 6. Tests run

| Suite | Result |
|---|---|
| `supabase/tests/admin_portal_test.sql` (new) | 69 / 69 passed |
| Existing SQL suites: customer RLS, order integrity, product search, staff cross-branch, staff foundation, staff identity | 134, 82, 9, 126, 52, 44 — all passed |
| `concurrency_test.sh`, `staff_idempotency_concurrency_test.sh` | 16/16, all passed |
| Laravel, mock mode (`php artisan test`) | 50 / 50 passed |
| Laravel, Supabase integration (`--group=supabase`, 15 end-to-end tests over HTTP) | 15 / 15 passed |

All database tests ran on a local PostgreSQL 16 with the schema, every migration, and a stub for
the Supabase-only parts (`supabase/tests/local_supabase_stub.sql`), built from scratch.

**Not run:** `flutter analyze` / `flutter test` (the Flutter SDK could not be downloaded in the
build environment) — the Dart change is three small edits; please run both before releasing.
Nothing was run against your real Supabase project.

## 7. Still to do by hand

1. Run the migration and set the `melai_admin_portal` password (2A). If `grant authenticated to
   melai_admin_portal` is refused, run that line as the `postgres` user in the SQL editor.
2. Run `flutter analyze` and `flutter test`.
3. Online refunds: send the money back in the HitPay dashboard (no HitPay refund API is wired).
4. Website and app logins are separate passwords. Signing in to the website with Firebase is a
   possible next step.
5. Rotate the keys flagged in `SECURITY.md` (unchanged by this work).
6. Leaflet map tiles load from the internet (unpkg/OpenStreetMap).
