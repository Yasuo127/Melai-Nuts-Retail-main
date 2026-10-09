# Melai Nuts Admin (web)

The owner/staff web dashboard for the Melai Nuts platform. It is a Laravel 12 app that lives in
`admin/` next to the Flutter app and works on **the same Supabase database** the app uses.
See `../INTEGRATION.md` for how the pieces fit together.

## What it does

| Page | With `DATA_SOURCE=supabase` (real data) |
|---|---|
| Dashboard | Sales per branch vs. target, stock health per branch, deliveries, refunds, loyalty totals |
| Sales | Revenue per branch (today / 7 days / window), daily revenue, top products (owners); own branch (staff) |
| Products | Full catalog with variants, prices and stock; admins can hide/show a product in the app |
| Inventory | Stock per branch and variant, restock thresholds, recent stock movements |
| Deliveries | Delivery runs and stops created by staff in the app |
| Payments | All orders' payments, totals per method, **mark cash paid** |
| Refunds | Refund requests from the app: approve (cash: one step), mark online refunds completed with the HitPay reference, reject with a reason the customer sees |
| Loyalty | Members, balances, history; owners edit the earn/redeem rules and add/deduct points |
| Users | Website logins, access, activation, **link each login to its app account** |
| Activity Logs | This website's log + the app's append-only staff audit log |

Driver Tracking and Reports are placeholders (the app has no rider GPS or report exports yet).

`DATA_SOURCE=mock` keeps the original demo data so the site can be shown without a database
(the topbar then says **Demo data**). The automated tests use it.

## Setup

Requirements: PHP 8.2+ with `pdo_pgsql` and `pdo_sqlite`, Composer, Node 18+.

```bash
cd admin
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # Windows: type nul > database\database.sqlite
```

Fill in `.env` (see `.env.example`): `ADMIN_*`, `DATA_SOURCE=supabase` and the `SUPABASE_DB_*`
connection for the `melai_admin_portal` role (setup in `../INTEGRATION.md`, step 2). Then:

```bash
php artisan migrate --seed            # local tables + first admin (linked if ADMIN_STAFF_UID is set)
php artisan melai:link-staff admin@your-domain <owner firebase uid>   # if you did not set ADMIN_STAFF_UID
npm run build                         # or `npm run dev` while developing
php artisan serve                     # http://127.0.0.1:8000
```

## Accounts and permissions

* A website login (local `users` table) is only the key to the website.
* What it can **see and do with business data** is decided by Supabase: each login is linked
  (`users.staff_uid`) to an app account in `staff_members`, and every database call runs as that
  account. Owners see all branches; staff see only their branch and only have the permissions
  the owner gave them in the app.
* Links are verified with Supabase: the app account must be active, have the same email, and
  the right role (website admin = app owner, website staff = app staff). Drivers are not linked.
* Deactivating someone in the app's User Management cuts their website access to data
  immediately, even if the website login is still active.

## Tests

```bash
php artisan test                      # 50 mock-mode tests; the 15 Supabase tests are skipped
```

Supabase integration tests (real Postgres with the app's schema, local only):

```bash
PGADMIN_URL=postgresql://postgres@127.0.0.1:5432/postgres bash tests/Supabase/prepare-test-db.sh
SUPABASE_TEST_ADMIN_URL=postgresql://postgres@127.0.0.1:5432/melai_portal_test \
SUPABASE_TEST_PORTAL_URL=postgresql://melai_admin_portal:portal-test-pw@127.0.0.1:5432/melai_portal_test \
  php artisan test --group=supabase
```

## Where to change things

* Brand colors / login photo: `resources/css/melai.css`.
* Sales targets per branch, map positions, thresholds: `config/melai.php` (`branches` are matched
  to the app's branches by name).
* Loyalty rules: on the Loyalty page (owners) — they are stored in Supabase and used by the app.
