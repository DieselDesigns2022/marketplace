# Routes

## Phase 12.4 seller CSV import
- `GET|POST /seller/products/import` — choose a source, upload a UTF-8 product CSV, and optionally review automatic mapping.
- `POST /seller/products/import/map` — apply fallback column mapping.
- `GET /seller/products/import/history` — seller-scoped history.
- `GET /seller/products/import/payhip-template` — download the Payhip-oriented template.
- `GET /seller/products/import/{id}/preview` — owned, paginated preview.
- `POST /seller/products/import/{id}/confirm` — durably save the exact selection and start its draft batch.
- `GET /seller/products/import/{id}/progress` — show seller-scoped live counts.
- `POST /seller/products/import/{id}/process` — process one bounded, idempotent, CSRF-protected import work unit: either one draft creation or one remote image job.
- `GET /seller/products/import/{id}/summary` — owned results.

Routes are registered in `public/index.php`.

## Public routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET | `/` | `PublicController::home` | Public |
| GET | `/browse` | `PublicController::browse` | Public |
| GET | `/sell` | `PublicController::sell` | Public |
| GET | `/category/{slug}` | `PublicController::category` | Public |
| GET | `/product/{slug}` | `PublicController::product` | Public |
| GET | `/store/{slug}` | `PublicController::store` | Public |
| GET | `/about` | `PublicController::static` | Public |
| GET | `/contact` | `PublicController::static` | Public |
| GET | `/terms` | `PublicController::static` | Public |
| GET | `/privacy` | `PublicController::static` | Public |
| GET | `/licensing-help` | `PublicController::static` | Public |
| GET | `/seller-faq` | `PublicController::static` | Public |
| GET | `/buyer-faq` | `PublicController::static` | Public |

## Auth routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET/POST | `/register` | `AuthController::register` | Public |
| GET/POST | `/login` | `AuthController::login` | Public |
| POST | `/logout` | `AuthController::logout` | Logged-in session action |
| GET | `/forgot-password` | `AuthController::forgot` | Public |
| GET/POST | `/account` | `AuthController::account` | Protected |

## Buyer routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET | `/dashboard` | `BuyerController::home` | Protected |
| GET | `/dashboard/purchases` | `BuyerController::purchases` | Protected |
| GET | `/dashboard/downloads` | `BuyerController::downloads` | Protected |
| GET | `/dashboard/order/{id}` | `BuyerController::order` | Protected |
| GET | `/dashboard/wishlist` | `BuyerController::wishlist` | Protected |
| GET | `/dashboard/following` | `BuyerController::following` | Protected |
| GET | `/dashboard/referrals` | `BuyerController::referrals` | Protected |
| POST | `/product/{id}/wishlist` | `BuyerController::toggleWishlist` | Protected |
| POST | `/store/{id}/follow` | `BuyerController::toggleFollow` | Protected |

## Seller routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET/POST | `/apply` | `SellerController::apply` | Logged-in application workflow |
| GET | `/seller` | `SellerController::home` | Seller/admin protected |
| GET/POST | `/seller/store` | `SellerController::storeSettings` | Seller/admin protected |
| GET | `/seller/products` | `SellerController::products` | Seller/admin protected |
| POST | `/seller/products/bulk-delete` | `SellerController::bulkDeleteProducts` | Seller/admin protected, ownership-scoped, CSRF |
| GET/POST | `/seller/product/new` | `SellerController::editProduct` | Seller/admin protected |
| GET/POST | `/seller/product/{id}` | `SellerController::editProduct` | Seller/admin protected |
| POST | `/seller/product/{id}/submit` | `SellerController::submitProduct` | Seller/admin protected |
| POST | `/seller/product/{id}/duplicate` | `SellerController::duplicateProduct` | Seller/admin protected |
| POST | `/seller/product/{id}/disable` | `SellerController::disableProduct` | Seller/admin protected |
| GET | `/seller/sales` | `SellerController::sales` | Seller/admin protected |
| GET | `/seller/referrals` | `SellerController::referrals` | Seller/admin protected |
| GET | `/seller/rank` | `SellerController::rank` | Seller/admin protected |

## Admin routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET | `/admin` | `AdminController::home` | Admin protected |
| GET/POST | `/admin/users` | `AdminController::users` | Admin protected |
| GET/POST | `/admin/applications` | `AdminController::applications` | Admin protected |
| GET/POST | `/admin/applications/{id}` | `AdminController::applications` | Admin protected |
| GET/POST | `/admin/designers` | `AdminController::designers` | Admin protected |
| GET/POST | `/admin/products` | `AdminController::products` | Admin protected |
| GET/POST | `/admin/products/{id}` | `AdminController::productDetail` | Admin protected |
| GET/POST | `/admin/categories` | `AdminController::categories` | Admin protected |
| GET | `/admin/orders` | `AdminController::orders` | Admin protected |
| GET/POST | `/admin/order/{id}` | `AdminController::orderDetail` | Admin protected + CSRF on POST |
| GET | `/admin/referrals` | `AdminCreditController::index` | Admin protected |
| GET | `/admin/credits` | `AdminCreditController::index` | Admin protected alias |
| POST | `/admin/credits/adjust` | `AdminCreditController::adjust` | Admin protected + CSRF |
| GET/POST | `/admin/homepage` | `AdminController::homepage` | Admin protected |
| GET/POST | `/admin/ads` | `AdminController::ads` | Admin protected |

## Cart, checkout, and download routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET | `/cart` | `CartController::show` | Protected buyer/cart workflow |
| POST | `/cart/add/{id}` | `CartController::add` | Protected |
| POST | `/cart/remove/{id}` | `CartController::remove` | Protected |
| POST | `/cart/update` | `CartController::update` | Protected |
| GET/POST | `/checkout` | `CartController::checkout` | Protected |
| GET | `/download/{file}` | `BuyerController::download` | Protected purchased-file access |

## SEO and indexing routes

| Method | Route | Controller | Protection | Indexing |
|---|---|---|---|---|
| GET | `/sitemap.xml` | `PublicController::sitemap` | Public | XML sitemap of public indexable URLs only |

Filtered `/browse` URLs are public but render `noindex,follow` and canonicalize to `/browse`. Private auth, account, dashboard, seller, admin, cart, checkout, apply, and download routes are treated as noindex/private workflow routes.


## Phase 7 route note

No new routes were added for Phase 7. Header and footer polish use existing routes only: `/`, `/browse`, `/sell`, `/about`, `/contact`, `/terms`, `/privacy`, `/buyer-faq`, `/seller-faq`, and `/licensing-help`.

## Phase 8 browse/search query parameters

`GET /browse` and `GET /category/{slug}` support SQL-backed public browsing of approved products only. Supported query parameters are:

- `q`: keyword search. Multi-word terms are split and matched partially across title, short description, description, tags, category, file types, and creator/store fields.
- `category`: category slug on `/browse`; category route paths keep their category in the path.
- `creator`: approved designer `store_slug`.
- `min_price` / `max_price`: numeric price range. Inverted ranges return an empty result safely.
- `ai`: one of the existing product AI disclosure values.
- `pod`: `1` for POD allowed or `0` for no POD.
- `featured`: `1` for featured products.
- `new`: `1` for products created in the last 30 days.
- `file_type`: matches existing `products.file_types` text.
- `commercial`: `1` for the existing `commercial_license_enabled` product flag.
- `sort`: `relevance`, `newest`, `oldest`, `price_asc`, `price_desc`, `title_asc`, `title_desc`, or `featured`.
- `page`: one-based pagination page.

Filtered or paginated browse/category URLs remain public but should render `noindex,follow` with canonical URLs to the base browse/category route.

Legacy PNG/Sublimation category normalization is implemented for the consolidated PNG category. The canonical category slug is `png-files`, and the canonical visible category label is `PNG Files`.

- `GET /category/sublimation` redirects to `/category/png-files`.
- `GET /category/png` redirects to `/category/png-files`.
- `GET /browse?category=sublimation` redirects/normalizes to `/browse?category=png-files` while preserving any other browse query parameters.
- `GET /browse?category=png` redirects/normalizes to `/browse?category=png-files` while preserving any other browse query parameters.

## Phase 8.5 licensing behavior
- `GET|POST /seller/product/new` and `GET|POST /seller/product/{id}` include seller license configuration fields for enabled licenses and add-on prices. Personal is always included/free; sellers enable or disable Basic, Commercial, POD, Wholesale, Fabric, VA, Reseller, and Extended Commercial add-on permissions and may price any add-on at `$0.00` or higher.
- `GET /product/{slug}` displays enabled license options, including selected add-on prices where applicable, and posts one or more selected license keys to `POST /cart/add/{id}`.
- `POST /cart/update` can update a cart item's selected licenses; cart storage uses normalized selected license keys.
- Checkout validates every selected license server-side, recalculates totals using product base price plus selected paid add-on license prices, and order items snapshot selected licenses, names/descriptions, and paid add-on price snapshots where applicable.
- `GET /dashboard/order/{id}` and `GET /admin/order/{id}` show purchased license snapshot details and selected paid add-on pricing where applicable.
- `GET|POST /admin/products/{id}` shows enabled product license permissions for admin review.

## Phase 8.75 route behavior
- Existing seller product create/edit routes now watermark uploaded public preview images and offer a POST-backed regenerate action from the edit form.
- Existing admin product detail route now supports a CSRF-protected `regenerate_watermark` action for product preview images with retained private originals.
- Public product pages include Facebook, X/Twitter, copy-link, and Instagram-friendly share controls without requiring login or adding third-party scripts.
- Public storefront pages render validated seller social links as safe external links and omit empty/invalid fields.

## Historical Phase 9 route additions and behavior
Phase 9 introduced the cart/order/download/manual-delivery route foundation that Phase 10 now connects to Stripe. For current checkout/payment behavior, use the Phase 10 section below.
- `GET/POST /checkout` originally created pending-payment foundation orders in Phase 9; in the current Phase 10 implementation it creates Stripe-backed pending orders and redirects to Stripe Checkout.
- `GET/POST /seller/order-item/{id}` introduced seller-owned manual delivery visibility; in Phase 10 buyer/manual-delivery details are exposed only after allowed paid payment status.
- `GET/POST /admin/order/{id}` includes manual delivery visibility and admin fulfillment override controls.
- `GET /admin/downloads` lists download log entries.
- `GET /download/{file}` validates buyer ownership, downloadable fulfillment, paid/fulfilled legacy status, expiration placeholder, and logs served/denied attempts without exposing protected storage paths.

## Phase 10 — Stripe Payment Integration
- `GET|POST /checkout` now validates the cart/order snapshots, creates a pending Stripe-backed order, creates a Stripe Checkout Session, stores Stripe session/amount/currency references, and redirects to Stripe Checkout.
- `GET /checkout/success` shows payment processing/current status only. It does not mark the order paid and does not unlock downloads or manual delivery.
- `GET /checkout/cancel` means the Stripe payment was not completed before access unlocked. It does not cancel a completed digital order and does not mark anything paid.
- `POST /checkout/retry/{id}` creates a new Checkout Session for unpaid retryable orders only. Paid, refunded, partially-refunded, and `manual_review` orders are blocked from retry.
- `POST /stripe/webhook` is public but Stripe-signature verified with `STRIPE_WEBHOOK_SECRET`; it is the source of truth for paid, failed, expired/canceled, refunded, and partially-refunded statuses.
- `GET /admin/payment-logs` shows marketplace commission totals, seller payout/transfer status, payment transactions, and Stripe webhook logs.
- `GET /dashboard/order/{id}` shows receipt-style payment status, paid download/manual-delivery state, retry messaging, and manual-review messaging.
- `GET /download/{file}` unlocks only for webhook-confirmed paid access or allowed legacy fulfilled/completed access.
- `GET /seller/sales` exposes seller-visible paid/allowed sales with payout status.
- `GET|POST /seller/order-item/{id}` exposes buyer/manual-delivery details only for paid seller-visible order items; mark-delivered remains paid-only.

Buyer self-cancellation of completed digital purchases is not a route behavior. Future seller refund/cancellation requests must go through admin review and approval before any Stripe refund/cancellation action.

### Phase 10 seller onboarding and Stripe Connect routes
- `GET /seller/onboarding` — approved seller onboarding checklist, readiness statuses, fee FAQ, refund/cancellation rules.
- `GET /seller/stripe` — current Stripe Connect status and payout readiness.
- `POST /seller/stripe/connect` — create/reuse Stripe Express connected account and redirect to Stripe onboarding.
- `GET /seller/stripe/return` — refresh connected account status after Stripe onboarding.
- `GET /seller/stripe/refresh` — create a fresh Stripe onboarding account link.
- `POST /stripe/webhook` — existing Stripe platform webhook; also supports `account.updated` status sync when that event is delivered to this endpoint.

#### Stripe webhook secret behavior
`POST /stripe/webhook` verifies the required platform `STRIPE_WEBHOOK_SECRET`. If `account.updated` is sent through a separate Stripe Connect webhook destination with a different signing secret, configure optional `STRIPE_CONNECT_WEBHOOK_SECRET`; if Stripe sends connected-account events to the same destination/secret, leave the optional value empty.

## Phase 10.1 product cleanup routes
- `POST /seller/product/{id}/archive` — seller-only archive/hide for the seller's own product.
- `POST /seller/product/{id}/restore` — seller-only restore of the seller's archived/deleted product to draft.
- `POST /seller/product/{id}/delete` — seller-only safe permanent delete for owned draft/test products with no completed orders; ordered products are archived instead.
- `POST /admin/products/bulk-cleanup` — admin-only bulk archive or safe bulk permanent delete for pre-live test product cleanup.

## Phase 10.2 Coupon Routes
- `GET|POST /admin/coupons` — admin coupon list and create handling for platform-wide and seller-scoped coupons.
- `GET|POST /admin/coupons/{id}` — admin coupon edit handling, including active status, date windows, limits, and seller/product/category restrictions.
- `GET|POST /seller/coupons` — approved seller coupon list and create handling for seller-scoped coupons only.
- `GET|POST /seller/coupons/{id}` — approved seller coupon edit handling; server-side ownership checks require `scope="seller"` and the current seller ID.
- `POST /cart/coupon` — applies a normalized coupon code to the buyer cart after server-side validation.
- `POST /cart/coupon/remove` — removes the currently applied cart coupon.
- Stripe checkout routes remain unchanged; coupon usage is recorded from the paid webhook path, not at code entry time.

## Phase 10.3B Stripe Tax compliance
No new public tax routes were added in Phase 10.3B, and Phase 11 still uses the existing checkout and signed webhook routes. The current checkout creates a standalone Stripe Tax Calculation before applying credit; atomic internal/webhook finalization creates the corresponding Tax Transaction. Tax remains separate from seller payout and commission math.

### Phase 10.4 routes

- `GET /admin/ip-risk-terms` — admin only — list configured advisory terms; read-only.
- `GET /admin/ip-risk-terms/create` — admin only — render create form; read-only.
- `POST /admin/ip-risk-terms` — admin only + CSRF — create a term and aliases; changes state.
- `GET /admin/ip-risk-terms/{id}/edit` — admin only — render edit form; read-only.
- `POST /admin/ip-risk-terms/{id}` — admin only + CSRF — update term, aliases, note, category, enabled state; changes state.
- `POST /admin/ip-risk-terms/{id}/enable` — admin only + CSRF — enable an existing term; changes state.
- `POST /admin/ip-risk-terms/{id}/disable` — admin only + CSRF — disable an existing term without deleting history; changes state.
- `POST /admin/products/{id}/ip-risk-review` — admin only + CSRF — keep pending, approve, publish/keep published while flagged, reject, or archive through validated transitions; changes state.

There are no public term-list endpoints and no state-changing GET routes.

## Phase 10.5 routes
| Method | Route | Access |
|---|---|---|
| GET, POST | `/waitlist` | Public, CSRF on POST |
| GET, POST | `/email/unsubscribe` | Public token, CSRF on POST |
| GET | `/notifications` | Authenticated |
| POST | `/notifications/read-all` | Authenticated owner, CSRF |
| POST | `/notifications/{id}/read` | Authenticated owner, CSRF |
| GET | `/admin/waitlist` | Admin read access |
| POST | `/admin/waitlist` | Admin, CSRF-protected status mutation |
| GET | `/admin/waitlist/export` | Admin |
| POST | `/admin/waitlist/launch-invite` | Admin, CSRF |
| GET | `/admin/email-campaigns` | Admin |
| GET | `/admin/email-campaigns/new` | Admin form/read access |
| POST | `/admin/email-campaigns/new` | Admin, CSRF-protected preview/save/queue |
| GET | `/admin/email-campaigns/{id}` | Admin detail/read access |
| POST | `/admin/email-campaigns/{id}` | Admin, CSRF-protected queue/cancel/test |

`/notifications/read-all` and `/admin/email-campaigns/new` are registered before their dynamic sibling routes.



## Phase 10.6 seller receipt route

`GET|POST /seller/receipt-settings` maps to `SellerController::receiptSettings`. Access requires the authenticated seller's approved designer record and completed onboarding. GET displays current future-order settings; POST requires CSRF and accepts the explicit `save`, `remove_note`, `remove_image`, or `restore` action for that seller only. Successful changes affect snapshots created for later order items and never mutate existing snapshots. Seller and admin secondary navigation now includes the existing `/account` shortcut. Buyer download links are displayed only after the corresponding protected file passes containment, regular-file, and readability checks.

### Phase 10.6 live fixes
- `GET|POST /waitlist` renders through a navigation-free minimal shell; submission behavior is unchanged.
- `POST /admin/users/{id}/delete` is admin-only, globally CSRF-protected, exact-email-confirmed permanent deletion for accounts that pass retention checks.

### Phase 10.6 final live-testing routes

- `POST /admin/waitlist/{id}/delete` — admin only + CSRF + exact target-email confirmation — permanently deletes an eligible waitlist entry and its directly linked email records.
- `GET /admin/payment-logs?issue=failed_transfers` — admin only — lists unresolved seller-transfer issues.
- `GET /admin/payment-logs?issue=webhook_issues` — admin only — lists unresolved Stripe webhook issues.
- `POST /admin/payment-logs` — admin only + CSRF — records resolution of an eligible transfer or webhook issue while preserving its original history.

### Phase 11 routes
- `GET /dashboard/referrals` — authenticated buyer balances, ledger, referrals made, and attached referral.
- `GET /seller/referrals` — approved seller referral link and seller qualification status.
- `GET /admin/referrals` — admin-only primary Credits & Referrals search/review page.
- `POST /admin/platform-credit-payouts/{id}/settle` — active-admin, CSRF-protected settlement of one eligible `platform_credit_hold` from Stripe platform balance; idempotent replay returns the existing transfer.
- `GET /admin/credits` — admin-only alias of the primary page.
- `POST /admin/credits/adjust` — admin-only, CSRF-protected audited adjustment.
- `GET|POST /register` accepts an optional normalized `ref`; `GET|POST /apply` preserves optional `seller_ref` intent.

### Phase 11 seller-referral lifetime commission
Seller-referral qualification permanently selects either referrer-only $5 store credit or, when the referrer is then an approved seller, an Asset Moth-funded 1% commission calculated per stored seller-payout item using integer-cent half-up rounding. Accruals and linked refund/recovery adjustments are append-only. Disabled, inactive, and deleted store states permanently stop new accrual without cancelling earned balances. Closed UTC-month platform-balance transfers reuse the seller's existing Stripe Connect account, omit `source_transaction`, and retain stable idempotency, processing leases, attempt history, and retryable failures. Active admins retry failed/not-ready batches only through CSRF-protected `POST /admin/seller-referral-payouts/{id}/retry`; immutable audit rows record the result. Unpaid prior-period amounts and post-payout recovery adjustments roll into the next positive batch, and no negative transfer is created.

### Phase 12 recognition routes
- `GET /seller/rank` shows private calculated/effective rank progress and Founder state.
- `GET /store/{slug}` and `GET /product/{slug}` show effective rank and only an active Founder badge.
- Existing `GET|POST /admin/designers` handles `set_rank_override`, `remove_rank_override`, `grant`, `force_active`, `force_inactive`, `restore`, and `automatic`. Every recognition POST uses the existing CSRF field, seller ID, and a 3–500 character reason; no public recognition directory or additional badge route exists.

Founder `restore` and `automatic` both return the badge to the current UTC automatic calculation; `restore` emails only when an inactive badge actually becomes eligible and active. `force_active` is the sole persistent inactivity exception.

### Phase 12.2 — Bulk Product Upload & Batch Listing
- `GET /seller/product-batches` — list the authenticated seller's saved bulk-product batches.
- `GET|POST /seller/product-bulk/template` — collect and save the shared starting product information for a new bulk workflow.
- `GET|POST /seller/product-bulk/count` — collect the total number of products being created.
- `GET|POST /seller/product-bulk/item/{step}` — process Product 1 through the final product sequentially, with each product prefilled from the shared starting information and independently editable before continuing.
- `GET /seller/product-batch/{id}` — view/reopen a saved bulk batch and its products.
- `POST /seller/product-batch/{id}` — perform supported batch mutations, including product submission actions and bulk-batch deletion.

All seller bulk routes remain ownership-scoped and retain the existing onboarding, CSRF, product validation, IP-risk, submission, moderation, and publishing protections.
# Phase 12.3 email preference behavior

- `GET|POST /account` displays and saves the authenticated user's name and three independent email preferences with CSRF protection.
- `GET|POST /email/unsubscribe?token=...` verifies the existing HMAC/nonce identity and applies the category encoded by a Phase 12.3 link. Weekly, monthly, and favorite-shop links do not change one another or transactional delivery.

## Phase 12.5 messaging
All routes require authentication and POST routes require CSRF. `GET /buyer/messages` and `GET /seller/messages` provide participant-specific active inboxes; `GET /buyer/messages?archived=1` and `GET /seller/messages?archived=1` provide the corresponding participant's archived inbox. `GET|POST /{side}/messages/{id}` reads or replies. Archive, block, and report use `POST /{side}/messages/{id}/{archive|block|report}`, and `GET /messages/attachments/{id}` reauthorizes conversation participation.

Start routes are `POST /messages/start/product/{id}`, `POST /messages/start/store/{id}`, `POST /messages/start/buyer-order-item/{id}`, and `POST /messages/start/seller-order-item/{id}`. Product and storefront starts reject messaging one's own store. Buyer order-item starts require authoritative buyer ownership and an order payment state of `paid` or `partially_refunded`; seller order-item starts require authoritative seller ownership and the same payment-state allowlist.

Reported-conversation administration uses the exact routes `GET /admin/message-reports`, `GET /admin/message-reports/{id}`, `POST /admin/message-reports/{id}/moderate`, and `GET /admin/message-reports/{reportId}/attachments/{id}`. These routes require an authenticated administrator, and attachment access is scoped to the conversation referenced by the supplied report ID.

### Report identity and empty compose behavior
Admin detail URLs always identify a `message_reports.id`; the controller separately uses its aliased `conversation_id` to load history. Report-scoped attachment URLs and moderation posts retain the report ID. Start routes may prepare an empty compose thread, but it is absent from inbox routes until its first message commits.
