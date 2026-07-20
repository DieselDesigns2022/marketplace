# Asset Moth

## Project Overview

Asset Moth is a custom PHP marketplace application for selling digital design products such as SVG cut files, fonts, and Canva templates. The current implementation includes public browsing, buyer accounts, designer applications, seller storefronts, product management, cart checkout, orders, protected downloads, and admin moderation.

## Current Project Status

- Development Status: active documentation and marketplace feature development.
- Current Phase: Phase 10.5 — Emails, Notifications & Waitlist.
- Default Branch: `main`.
- Source of Truth: GitHub.
- Current build/test URL: `https://marketplace.dieseldesigns.co`.
- Future planned domain after purchase/migration: `https://assetmoth.com`.

## Marketplace Purpose

The long-term product vision is a curated digital design marketplace where independent designers can apply to sell, manage branded storefronts, upload downloadable products, and earn revenue while buyers discover, purchase, wishlist, follow, and download digital products.

Features from the original blueprint that are not implemented in the current codebase are treated as **Planned / Future Phase** items and are not documented as currently working features.

## Current Implementation Status

These features represent the current implemented and tested functionality in the codebase.

### Implemented now

- Public homepage, browse page, category pages, product pages, store pages, static pages, and sell landing page.
- Buyer registration, login, account management, dashboard, wishlists, follows, purchases, order detail, and downloads.
- Designer application workflow with admin approval and denial.
- Approved seller dashboard, storefront settings, product creation/editing, preview images, downloadable files, tags, sales, referrals, and rank pages.
- Cart/order/download/manual-delivery foundation from Phase 9, now connected to Stripe Checkout and webhook-confirmed payment status in Phase 10.
- Phase 10.1 product cleanup tools for seller archive/hide, restore-as-draft, safe permanent delete, admin bulk cleanup, archived/deleted statuses, and completed-order delete protection.
- Admin dashboards for users, applications, designers, products, categories, orders, referrals, homepage features, and ads.
- Phase 4.5 codebase standardization for readability, plus restoration of public product previews and sell page regressions.

### Planned / Future Phase

- Stripe Checkout payment creation and webhook-driven payment status are implemented in Phase 10; production keys remain environment-only.
- Additional payout automation and reporting workflows; Stripe Tax for US checkout is implemented in Phase 10.3B, while international VAT/GST expansion remains future work.
- Full review workflow and review display polish.
- Advanced search, filtering, and recommendations.
- Advanced SEO iteration after launch data is available.
- Phase 10.5 implements the durable email queue, log transport, escaped templates, consent controls, promotional campaign foundation, waitlist workflows, and in-app notification center. Production-provider delivery, provider authentication, sender verification, bounce handling, and advanced production operations remain future work.
- More complete ad campaign management and analytics.


### Phase 7 launch polish

Phase 7 improves marketplace clarity and launch readiness across the shared layout, homepage, browse/category pages, product pages, storefronts, sell landing page, and buyer/seller/admin dashboards. The header expects a local logo at `public/assets/img/asset-moth-logo.png` and safely falls back to visible text if the image is missing. Phase 7 intentionally does not add the future full licensing system, advanced search, real payment processing, production emails, referral/rank systems, sponsored listings, or bundle events.

## Project Principles

- GitHub is the source of truth.
- VPS is used only for deployment and testing.
- All work is completed on feature branches.
- Every change requires a Pull Request.
- Every Pull Request is manually reviewed before merging.
- Production changes require backups before deployment.

## Quick Start

To run the project in a local or VPS environment:

1. Clone the repository from GitHub.
2. Configure `.env` with the required database connection settings and environment values.
3. Import `database/schema.sql` into MariaDB.
4. Configure Nginx so the document root points to the project `public/` directory.
5. Ensure public upload folders and protected upload folders exist and are writable by the web server/PHP runtime user.
6. Launch the application through the configured web server.
7. Verify the homepage, login/register pages, browse page, cart, dashboards, and protected download workflow as appropriate for the environment.

## Technology Stack

- PHP 8.3.
- MariaDB.
- Nginx.
- Ubuntu VPS.
- Server-rendered PHP.
- PDO.
- Session authentication.
- Custom MVC-style application structure.
- Plain CSS and JavaScript assets.

## Server / VPS Info

- Live URL: `https://marketplace.dieseldesigns.co`
- VPS application path: `/var/www/marketplace.dieseldesigns.co`
- Error log: `/var/log/nginx/marketplace.error.log`
- GitHub is the source of truth.
- VPS is the deployment and testing target.
- Codex is temporary and must not be treated as the source of truth.

## Repository Structure

```text
app/
    Controllers/
    Core/
    Views/

assets/
    css/
    js/

database/
    schema.sql
    migrations/

docs/

public/
```

### Important folders

- `app/`: Application source for controllers, core routing/database/helper classes, and server-rendered views.
- `assets/`: Public CSS and JavaScript assets used by the application.
- `database/`: Current baseline schema and phase migration files.
- `docs/`: Project documentation for architecture, database, deployment, testing, routes, security, SEO, workflows, and troubleshooting.
- `public/`: Web document root and front controller entrypoint.

## Main Workflows

### Visitor Workflow

- Visit the homepage.
- Browse products.
- View categories.
- View product detail pages.
- View designer store pages.
- Read public static pages.
- Visit the sell landing page.

### Buyer Workflow

- Register or log in.
- Browse products.
- Wishlist products.
- Follow designers.
- Add products to cart.
- Purchase products through checkout.
- Download purchases.
- View order history.
- Manage account details.

### Seller Workflow

- Submit a designer application.
- Wait for admin approval.
- Manage storefront settings.
- Create and edit products.
- Upload preview images and protected product files.
- Submit products for admin review.
- View sales.
- Review referrals and creator rank.

### Admin Workflow

- Review marketplace dashboard.
- Manage users.
- Approve or deny designer applications.
- Manage designers.
- Moderate products.
- Manage categories.
- Review orders and order details.
- Manage homepage features and ads.
- Review referrals.

## Documentation Index

- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Testing](docs/TESTING.md)
- [Phase History](docs/PHASE_HISTORY.md)
- [Routes](docs/ROUTES.md)
- [Security](docs/SECURITY.md)
- [SEO](docs/SEO.md)
- [Codex Workflow](docs/CODEX_WORKFLOW.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [Development Guide](DEVELOPMENT.md)
- [Contributing](CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)

## Phase 6 SEO launch foundation

The application now includes Asset Moth public branding, shared metadata rendering, absolute canonicals, robots meta controls, browse filtered-URL noindex behavior, dynamic `/sitemap.xml`, `public/robots.txt`, conservative JSON-LD structured data, public launch copy, and internal links across key public pages.


## Historical feature section: Phase 10 — Stripe Payment Integration

### Phase 8.5 licensing capability

Phase 8.5 adds the marketplace licensing foundation with Personal always included/free and seller-enabled add-on permissions that may be free (`$0.00`) or paid. Sellers can enable Basic, Commercial, POD, Wholesale, Fabric with overseas printing, Fabric without overseas printing, VA, Reseller with credit required, Reseller with no credit required, and Extended Commercial licenses; buyers can select multiple permissions; guest carts persist until checkout/login; carts and orders store normalized selected license keys plus license price snapshots; and order/admin/buyer views show the selected license details clearly. The product page, cart, seller edit form, and Licensing Help page now use license detail tooltips/modals so long terms stay readable without cluttering the listing.

### Phase 8.75 marketplace protection and sharing
Asset Moth watermarks seller-uploaded public product preview images server-side. Watermarking is limited to public preview images; purchased/downloadable files are stored separately and are not watermarked or altered. Newly uploaded preview originals are retained privately for regeneration. The default watermark source path is `storage/app/private/branding/watermark.png`, with optional override via `WATERMARK_SOURCE_PATH`.

Product pages include buyer-friendly social sharing controls and Open Graph/Twitter preview metadata. Seller storefronts support optional validated website, Facebook, Instagram, TikTok, Pinterest, Etsy, and Shopify links rendered with safe external-link attributes.

## Phase 9 cart/order/download/manual-delivery foundation
Phase 9 added the foundation for carts, order records, downloadable delivery, and Google Drive/manual delivery. Phase 10 now connects that pending-payment foundation to Stripe Checkout and webhook-confirmed payment state. Google Drive delivery remains manual: buyers provide a Google Drive email, sellers grant access outside the app after payment clears, and sellers/admins update delivery status in Asset Moth.

## Phase 10 — Stripe Payment Integration
- Adds Stripe Checkout session creation for buyer checkout using server-side order snapshots and environment-only Stripe configuration.
- Stores Stripe Checkout Session, Payment Intent, customer/charge references when available, payment status, Stripe amount/currency, paid/failed/refunded timestamps, retry count, and manual review flags.
- Stripe webhooks are the source of truth for paid, failed, expired/canceled, refunded, and partially refunded status. The browser success redirect only shows a processing page and does not unlock access.
- Downloads unlock only after webhook-confirmed paid status. Google Drive/manual delivery becomes seller-ready only after payment clears; seller delivery visibility is blocked before payment clears.
- Failed, canceled, expired, and unpaid orders show retry/return-to-checkout options. `manual_review` is a payment safety lock and blocks buyer retry/unlock until admin review.
- Buyer order detail remains the persistent web receipt and payment record; Phase 10.5 additionally queues the purchase-receipt email only after webhook-confirmed payment.
- Adds duplicate webhook protection via `stripe_events.stripe_event_id`, Stripe signature verification, amount/currency/order metadata mismatch checks, payment transaction logs, and admin payment log visibility.
- Adds seller payout foundation with Stripe Connect account status fields, seller payout ledger records, and transfer attempts only when connected accounts are enabled. Missing onboarding leaves payouts pending without failing buyer payment.
- Buyer-facing “payment not completed/cancel” wording refers only to an incomplete Stripe payment before purchase access unlocked; buyers cannot self-cancel completed digital purchases.
- Phase 10 records/reflects webhook refund status when Stripe reports it, but does not build a buyer cancellation flow or seller refund-request approval workflow.
- Future intended seller refund/cancellation flow: seller requests refund/cancellation → admin reviews → admin approves or denies → Stripe refund/cancellation action happens only after admin approval.
- Phase 11 credits/referrals, international VAT/GST expansion, and seller refund/cancellation requests remain future work.

### Phase 10 Stripe marketplace payments and seller onboarding
Phase 10 includes buyer Stripe Checkout, Stripe webhook-controlled payment status, seller onboarding, seller Stripe Connect onboarding, and payout readiness. Asset Moth charges buyers on the platform Stripe account, keeps an 18% marketplace commission on each sale by default (`PLATFORM_COMMISSION_PERCENT=18`), and transfers only the seller payout portion to the seller's connected account when Stripe Connect onboarding is complete and payout-ready. Stripe/payment processing fees also apply and are separate from Asset Moth's 18% commission.

Sellers pay no startup fee, no monthly fee, and no listing fee; Asset Moth only earns when sellers sell. Buyer checkout can work before seller onboarding, but seller payouts remain pending until onboarding is complete. Refunds are Stripe-processed admin exceptions only; buyers cannot self-cancel completed digital purchases and sellers cannot issue instant refunds themselves.

#### Phase 10 payout math and Connect webhook note
Phase 10 calculates Asset Moth's commission from the gross sale amount and transfers the seller portion from that gross sale snapshot before separate Stripe fee reconciliation. Stripe/payment processing fees still apply separately; seller-facing copy must not claim sellers receive exactly 82% after all fees. `STRIPE_WEBHOOK_SECRET` is required, and `STRIPE_CONNECT_WEBHOOK_SECRET` is optional for a separate Connect webhook destination that uses a different signing secret.

#### Phase 10 source-transaction transfer reliability
Seller transfers use the original Stripe charge as `source_transaction` when `stripe_charge_id` is available. If the paid order is waiting for the charge id, payout records stay `pending_transfer` for a later webhook retry instead of being failed solely because Stripe balance timing is not ready.

## Phase 10.2 Coupons, Discounts & Commission Rules
- Added platform and seller coupon management with normalized unique coupon codes, active status, percent/fixed discounts, start/end dates, minimum eligible cart amount, total and per-user usage limits, and seller/product/category restrictions.
- Admins manage all coupons at `/admin/coupons`; approved sellers manage only their seller-scoped coupons at `/seller/coupons` and server-side ownership checks prevent cross-seller coupon/product access.
- Buyers can apply or remove coupon codes in cart/checkout. Invalid, inactive, expired, not-yet-started, over-limit, below-minimum, and non-applicable coupons are rejected server-side.
- Checkout totals sent to Stripe follow: subtotal minus coupon discount plus Stripe Tax returned at Checkout minus the existing Phase 11 credits placeholder equals the final captured total. International VAT/GST remains future work; credits/referrals remain Phase 11.
- Coupon snapshots are stored on orders and order items. Coupon usage is recorded only after Stripe confirms a successful paid order, with an order-level uniqueness guard to avoid webhook/retry double counting.
- Platform commission, seller earnings, and payout ledger amounts are calculated from discounted order item totals after coupon discounts are allocated across eligible items.
- Coupons that reduce checkout to `$0.00` are intentionally blocked until a dedicated free-order checkout flow exists.

### Phase 10.3B Stripe Tax and tax compliance
Phase 10.3B uses Stripe Tax in Stripe Checkout for automatic sales-tax calculation. Asset Moth is US-only at launch, sellers are US-only at launch, products are digital files only, and there is no shipping. Sellers do not enter tax rates or manual sales-tax settings; tax is returned by Stripe after checkout confirmation and is excluded from seller payouts and marketplace commission math. International VAT/GST is future work. 1099 reporting is handled through Stripe Connect and Stripe tax forms setup rather than homemade IRS form generation.

Delivery unlock requires webhook-confirmed payment and a complete Stripe Tax status for tax-enabled Checkout Sessions.

### Phase 10.4 — Advisory IP Risk Warning

Asset Moth includes an advisory IP-risk warning workflow for seller product metadata. It scans saved product title, short/full description, tags, SEO title, SEO description, and stored original downloadable product-file names; preview-image filenames are not scanned. There is no separate seller product keyword field in the current schema, so Phase 10.4 does not invent one against admin-managed terms and aliases. Matches warn sellers and require the exact rights-confirmation checkbox before submitting flagged products for review. The scanner is not legal advice, does not determine infringement, and does not scan file contents, images, OCR, audio, video, private paths, or external trademark databases. Automated matching can produce false positives and false negatives. Starter advisory terms are incomplete and are not comprehensive trademark, copyright, celebrity, franchise, or protected-content coverage.

## Phase 10.5 — emails, notifications, and launch waitlist
Phase 10.5 provides authenticated notifications, a consent-aware public waitlist, admin waitlist/campaign tools, and a durable email queue. Development uses `MAIL_TRANSPORT=log`; run `php scripts/process_email_queue.php 50`. See [the operational guide](docs/EMAILS_NOTIFICATIONS_WAITLIST.md).
