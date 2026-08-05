# Architecture

## MVC-style structure

The application follows a lightweight MVC-style structure:

- Models are represented mainly by database tables and query logic inside controllers/helpers rather than separate model classes.
- Views live in `app/Views`.
- Controllers live in `app/Controllers`.
- Core framework-like behavior lives in `app/Core`.

## Entrypoint: `public/index.php`

`public/index.php` is the front controller. It loads bootstrap, imports controllers, registers all routes, and dispatches the current request through the router.

## Bootstrap: `app/bootstrap.php`

Bootstrap responsibilities:

- Register PSR-like `App\` autoloading.
- Load `.env` values into `$_ENV`.
- Configure session cookie settings.
- Start the `design_marketplace` session.
- Define `app_path()` and `public_path()` helpers.
- Alias `App\Core\Helpers` as `H`.

## Router

`app/Core/Router.php` stores route definitions and dispatches by HTTP method/path. It verifies CSRF on POST requests before matching and invokes controller methods with dynamic path parameters.

## Controllers

Controllers are grouped by workflow:

- Public visitor pages.
- Authentication/account pages.
- Buyer dashboard/workflows.
- Seller dashboard/workflows.
- Cart/checkout workflows.
- Admin workflows.

## Views

Views are PHP templates. `Helpers::view()` extracts controller data and loads `app/Views/layouts/app.php`, which then includes the requested page view.

## Layout system

The shared layout controls common page structure, flash messages, navigation, and view inclusion. Individual views are organized by area: public, auth, buyer, seller, admin, and static.

## Helpers

`Helpers` centralizes escaping, formatting, slug generation, CSRF, auth/role gates, flash messages, redirects, abort responses, and view rendering.

## Database class

`Database` wraps PDO and exposes:

- `pdo()` for the shared connection.
- `rows()` for multiple rows.
- `row()` for one row.
- `exec()` for write statements.
- Transaction helpers.

All new SQL should use prepared statements.

## Public vs protected storage

- Public assets and preview images may be served from web-accessible public upload folders.
- Product files for purchases must be protected from direct public access.
- Download routes should validate ownership/order access before serving product files.


## Phase 11 financial services

`CreditService` owns exact-cent balances and the append-only ledger; `ReferralService` owns immutable referrer attachment plus independent buyer/seller qualification; and `OrderFinalizationService` owns atomic tax, credit, coupon, earnings, commission, payout, fulfillment, and reward completion. `CartController` obtains the Stripe Tax Calculation before reserving credit. `StripeController` validates captured payment data and billing-location consistency, then invokes the finalizer inside the locked transaction. Receipt, sale, coupon, download, and referral communications run after commit with stable deduplication keys. Admin credit/referral workflows are isolated in `AdminCreditController`.

`PlatformCreditPayoutService` separately settles internally funded `platform_credit_hold` obligations. It revalidates and locks the payout and order, then uses the stored obligation amount, connected account, stable idempotency key, and order transfer group for a Stripe platform-balance transfer without a buyer source charge. Success and retryable failure outcomes are retained in the payout and immutable admin log.

### Phase 11 seller-referral lifetime commission
Seller-referral qualification permanently selects either referrer-only $5 store credit or, when the referrer is then an approved seller, an Asset Moth-funded 1% commission calculated per stored seller-payout item using integer-cent half-up rounding. Accruals and linked refund/recovery adjustments are append-only. Disabled, inactive, and deleted store states permanently stop new accrual without cancelling earned balances. Closed UTC-month platform-balance transfers reuse the seller's existing Stripe Connect account, omit `source_transaction`, and retain stable idempotency, processing leases, attempt history, and retryable failures. Active admins retry failed/not-ready batches only through CSRF-protected `POST /admin/seller-referral-payouts/{id}/retry`; immutable audit rows record the result. Unpaid prior-period amounts and post-payout recovery adjustments roll into the next positive batch, and no negative transfer is created.
