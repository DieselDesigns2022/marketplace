# Security

## CSV product imports
Phase 12.4 accepts CSV uploads only: no OAuth, API keys, account linking, tokens, webhooks, scraping, or synchronization. Routes require an authenticated approved seller with completed onboarding, POST requests use CSRF, and every run query includes the designer owner. CSVs are bounded, structurally checked UTF-8 text, stored below private application storage, escaped in views, removed on failed/completed workflows; abandoned CSVs become eligible for 24-hour stale-file cleanup during subsequent import cleanup activity, rather than by an automatic timed job. Preview run/item persistence is atomic. Only curated supported product/variant fields are retained; unknown, customer, plugin, and WooCommerce `meta:*` columns are not persisted. Duplicate protection uses seller/source-scoped full canonical fingerprints. CSV-supplied product images are fetched over HTTPS with credentials forbidden, DNS resolution and every redirect checked against private, loopback, link-local, reserved, and non-routable addresses, DNS-pinned connections, bounded redirects, strict timeouts, and a 25MB limit. Downloaded bytes must decode as JPG, PNG, or WEBP before the existing private-original/public-watermark pipeline attaches normal image records; handles and temporary files are cleaned on every handled failure.

## Password hashing

Passwords are stored as hashes, not plaintext. Future auth changes must continue using PHP password hashing APIs and never store raw passwords.

## Sessions

The app uses the `design_marketplace` session. Session cookies are configured as HTTP-only, SameSite=Lax, and secure when HTTPS is detected.

## CSRF

All POST requests are checked by the router using the CSRF helper. Forms must include the current CSRF token.

## Role checks

Protected routes must call the correct helper:

- `requireLogin()` for logged-in users.
- `requireRole('admin')` or controller admin gate for admin-only pages.
- `requireSeller()` for approved sellers/admins.

## Admin restrictions

Admin pages must be restricted to admin users. Admin actions should be logged where practical in `admin_logs`.

## Seller restrictions

Seller pages must be restricted to approved designers or admins. Sellers should only modify their own storefront/products unless an admin-specific path intentionally allows broader access.

## Protected downloads

Product files must not be directly public. Download routes must verify the current user has purchased the relevant product/file before serving it. Downloads should be logged with user, product, file, IP, and user agent.

## File upload safety

- Validate upload errors.
- Validate file type/extension.
- Enforce reasonable size limits.
- Store public previews separately from protected product files.
- Generate safe filenames.
- Never execute uploaded files.
- Never commit upload directories.

## `.env` handling

`.env` contains secrets and local database credentials. It must never be committed.

## `.gitignore` protections

The repository should ignore environment files, public uploads, protected uploads, backups, logs, and other generated artifacts. If new generated folders are added, update `.gitignore` before committing work.

## Phase 8.5 licensing security
- Seller license saves continue to load products by both product id and designer id before editing.
- License pricing is server-authoritative: Personal is always included/free, seller-enabled add-on licenses may be free (`$0.00`) or paid, cart totals are recalculated server-side, and order items snapshot selected licenses plus their prices.
- Buyer-submitted license key lists are normalized and validated server-side against enabled product licenses during add-to-cart and cart update, with Personal always included.
- Checkout validates every selected license again, recalculates line totals from product base price plus selected paid add-on license prices, and writes snapshots for selected licenses, selected names/descriptions, and selected paid add-on prices; client-provided prices are not trusted.
- Disabled or missing licenses are not purchasable, and existing products fall back to a safe Personal license if custom rows are missing.

## Phase 8.75 upload, watermark, and external-link security
- Product preview image uploads are validated by extension, MIME type, image metadata, and size before storage. Filenames are random server-generated values; original upload names are not trusted for storage paths.
- Watermarking applies only to public product preview images. Protected purchased/download files in `storage/protected_uploads/products` are not processed by the watermark service.
- Newly uploaded preview originals are retained under `storage/app/private/product_previews/` for seller/admin regeneration and are not served as public product images when a watermarked public version exists.
- The watermark source image can be placed at `storage/app/private/branding/watermark.png` or overridden with `WATERMARK_SOURCE_PATH`. If GD or the configured source is unavailable, the app fails gracefully and records a seller/admin-safe status message instead of breaking product pages.
- Storefront social links are normalized to http/https URLs, reject dangerous schemes such as `javascript:`, and render publicly with `target="_blank"` and `rel="noopener noreferrer nofollow ugc"`.

- Phase 8.75 live testing historically raised seller preview/avatar/banner image validation to 15MB while keeping extension, MIME, image metadata, and server-generated filename checks. That avatar/banner limit is superseded: current seller avatar and store-banner uploads allow up to 25MB. PHP upload handling is capped through `public/.user.ini`; Nginx dotfile protection was verified so `.user.ini` returns 403 publicly.
- Legacy public preview images were backfilled by copying the existing public preview into private preview storage first, then generating a watermarked public preview from that retained private original.

## Phase 10.1 product cleanup security
- Seller cleanup actions are POST-only, CSRF-protected, and scoped by `designer_id` so sellers can only archive, restore, or delete their own products.
- Admin bulk cleanup is POST-only, CSRF-protected, and guarded by admin authentication.
- Permanent product deletion is blocked when completed paid or partially refunded order items reference the product; the safer archive path is used instead.

## Phase 10.2 Coupon Security Notes
- Seller coupon edit POSTs require the coupon to exist with `scope="seller"` and `seller_id` matching the current approved seller before updates or restriction rewrites occur.
- Coupon restriction IDs are validated server-side; sellers can only save product/category restrictions tied to their own catalog.
- Coupon codes are normalized and stored with a unique code key to prevent unsafe collisions.
- Cart and checkout coupon discounts are recalculated server-side from current cart items; hidden form fields are not trusted for product, seller, or discount ownership.
- Discount amounts are capped to eligible subtotal and final totals are clamped non-negative.
- `$0.00` Stripe Checkout is blocked until a dedicated free-order flow exists.
- Usage tracking is written only after successful paid webhook confirmation and uses an order-level uniqueness guard to avoid double-counting.

## Phase 10.3B Stripe Tax compliance
Tax is trusted only from Stripe webhook data, not buyer input, seller input, or client-provided totals. Seller/manual tax fields and manual seller tax settings are intentionally absent. If a Checkout Session provides a non-US billing country, or Stripe returns a non-complete `automatic_tax.status`, the order goes to manual review and delivery/download unlock remains blocked. Existing Stripe webhook signature verification, duplicate-event protection, amount/currency checks, and metadata checks remain required.

### Phase 10.4 security controls

Seller scanning and confirmation verify ownership inside `ProductIpRiskWorkflow` by joining products to designers and comparing the authoritative designer user ID. Confirmations bind the authenticated seller to the latest server-side scan; submitted scan IDs, seller IDs, term IDs, detection lists, match counts, and review states are ignored.

Admin writes require admin authorization, CSRF, POST routes, allowlisted categories/actions/statuses, transition validation, prepared statements, and escaped output. Term aliases and canonical terms are collision-checked across both tables. Only stored downloadable product-file original filename metadata is scanned; no preview-image names, file binary, private path, image, OCR, audio, video, or external database content is transmitted or inspected. Safe permanent product deletion removes Phase 10.4 product child rows before deleting products while preserving configured terms and aliases.


Phase 10.4 approval-bypass hardening: ordinary admin approval checks authoritative latest IP state and active detections before approving. Products with active matches and `pending_review` IP status are blocked from single approval and skipped during bulk approval until an explicit IP review action is taken. Combined IP review decisions that also change product status are committed in one database transaction with the product status update, IP state/history, and admin log together.


Explicit IP review transitions re-read and lock the current product row and current IP state inside the transaction. Controllers do not supply or trust previous product status, new product status, latest scan ID, target IP status, active counts, product update status, or log action for those transitions.


Final Phase 10.4 hardening: failed confirmation attempts for existing published products do not replace live content or unpublish the listing. Admin IP transitions validate a complete product-status/action matrix and cannot revive terminal product statuses. Ordinary admin approval fails closed when latest-scan active detections exist with contradictory or incomplete IP state.

Phase 10.4 seller confirmation hardening: the pre-scan is an advisory UI check only. Seller confirmations are bound to the final authoritative saved scan, not to browser-submitted scan data or a pre-save preview. Publication-sensitive product edits, tag/license changes, scan persistence, detection/state updates, and seller confirmation are coordinated to fail closed; if the coordinated save fails, newly created uploads are cleaned up while existing uploads remain untouched. Authoritative filename matching covers stored downloadable product-file original names, not preview-image upload names that are not retained for every future scan.
Phase 10.4 new-product save hardening: a missing confirmation during a flagged new review submission is a validation outcome, not a system failure; the valid product remains a draft with its authoritative scan, uploads, tags, and licenses. A system/database exception after new-product creation uses compensating cleanup scoped only to the new product. Cleanup removes Phase 10.4 child records, newly created upload rows and physical files, product tags, product license rows, and then the product row. Existing products and their uploads are not affected, and this compensating cleanup is not described as a database rollback.

Phase 10.4 permanent-delete cleanup uses file-aware seller/admin upload cleanup. Seller and admin permanent deletion remove upload metadata plus safely contained public preview files, private retained preview originals, and protected downloadable files; path containment checks prevent unlinking files outside the expected upload directories, and completed-order protection still archives instead of permanently deleting ordered products.

## Phase 10.5 communication security
Notification updates always bind ID and authenticated user, preventing IDOR; action links accept local paths only. Public mutations use global CSRF and a honeypot, normalize email, avoid enumeration, and store non-secret unsubscribe authorization nonces; HMAC-signed tokens require the environment-only `EMAIL_UNSUBSCRIBE_SECRET` and are verified with `hash_equals`. Campaign and email output is escaped, subjects reject CR/LF, CTA destinations are constrained, and marketing consent is rechecked at delivery. Queue claims are locked, deduplicated, bounded and stale-recoverable; errors are sanitized. Structured logs omit raw email/token/payment secrets. CSV imports preserve cell text as application data and escape it when rendered; spreadsheet-formula hardening belongs at CSV export boundaries rather than mutating imported values.

Phase 10.5 final hardening restricts invitation POSTs to explicit individual or confirmed filtered modes, prevents administrators from restoring withdrawn consent, commits consent and confirmation queueing atomically, reauthorizes test sends against the active administrator account at delivery time, and validates seller email action links as local paths. Campaign/recipient reconciliation failures cannot change a safely stored message back into a resendable state.

Final Phase 10.5 URL validation rejects browser-normalizable backslashes, control characters, protocol-relative paths, cross-origin hosts/ports, URL credentials, and non-HTTPS absolute campaign CTAs. Log delivery deduplicates under an exclusive lock by message ID and contains no recipient address, template data, body, or unsubscribe token. Webhook-issue alerts are emitted only after successful Stripe signature verification; their controlled copy contains only the normalized event type, allowlisted failure category, and directions to protected logs. Arbitrary exception text and event payload data are excluded, and missing-ID deduplication uses only a fingerprint of the verified payload.

Verified webhook failure coverage begins immediately after successful signature verification, including event lookup and initial persistence. Missing-ID deduplication fingerprints the verified payload without storing it in notifications. Webhook alerts use allowlisted categories and controlled copy, never arbitrary exception text. Mail-log recovery repairs only incomplete trailing writes under lock; malformed complete records fail closed instead of being ignored.

Step 8 centralizes Phase 10.5 operational diagnostics in `OperationalErrorSanitizer`. Before protected logging or diagnostic database storage, it bounds and flattens errors; removes markup and stack traces; and redacts email, authorization, common API-key header/assignment variants, Stripe signatures/secrets/objects, credential assignments, database DSN/passwords, URL userinfo, token-bearing URLs, and unsubscribe-token values. Context labels are normalized and bounded. This does not weaken the stricter webhook administrator notification, which continues to use only controlled event/category copy.



## Phase 10.6 receipt and dashboard security

Receipt notes are normalized plain text, limited to 500 characters, and escaped at every output. Images require a genuine PHP upload, a matching JPG/PNG/WEBP extension and Fileinfo/image MIME, a valid decode, a maximum 10 MB size, GD resize/re-encode within 1600×1600, an available format encoder, and a random allowlisted path under `/uploads/receipt_images/`. SVG, GIF, scripts, malformed files, submitted filenames, and paths outside that directory are rejected.

Receipt updates require CSRF, approved-seller ownership, completed onboarding, and an allowlisted action. Checkout freshly reads the designer row inside its transaction, normalizes the note, allowlists the image path, and snapshots both without buyer input. Replaced images referenced by old snapshots are retained. Seller content cannot alter Asset Moth payment, tax, refund, license, totals, or protected-download enforcement. Dashboard navigation remains presentation only; controller role and ownership checks are authoritative. A 25,000,000 source-pixel ceiling is enforced before GD decode to limit decompression risk. Invalid optional seller note/image settings resolve to `NULL` snapshots so customization cannot interrupt checkout. Download availability uses the same protected-directory containment, regular-file, and readability policy as delivery. The buyer order receipt, dashboard count, and download history all require the selected file to pass protected-directory containment, regular-file, and readability checks before presenting availability. PNG uploads must end exactly at a CRC-valid IEND chunk, WEBP uploads must exactly match their RIFF declared length, and JPEG uploads must end at the parsed EOI marker; trailing payloads are rejected before GD decode. Seller refund reconciliation uses the maximum recorded cumulative refund, integer allocation, and absolute non-transferred ledger updates so duplicate or out-of-order events cannot double-adjust figures; tax is excluded from seller adjustments.

### Permanent pre-launch account deletion and banners
Admins cannot permanently delete themselves or another admin. Exact target-email confirmation and complete schema-aware retention checks precede a transaction that removes only eligible account-owned mutable records; financial, order, product, approved-store, payout, and marketplace history block deletion with a category-specific reason. For an eligible pre-launch account, directly linked campaign-recipient and email-delivery records are removed in the same transaction so campaign or mail identity data is not retained. The audit event contains no deleted person's name or email. An eligible unapproved seller's allowlisted avatar and banner are collected in the transaction and removed only after commit; cleanup is contained to the public store directories and cannot undo deletion. Store banners require genuine, verified 25 MB-or-smaller JPG/PNG/WEBP uploads, Fileinfo MIME verification, and GD. Sources over 40,000,000 pixels are rejected before decode; normalization preserves PNG/WEBP transparency and center-crops without distortion to an exact 2400 × 800 output. Files use randomized allowlisted paths and are safely cleaned around database replacement failures. Legacy randomized `.jpeg` banner paths are included in post-save cleanup while newly normalized JPEG files continue to use `.jpg`.

### Phase 10.6 live-testing hardening

Admin waitlist deletion requires administrator access, CSRF validation, and an exact-email confirmation. It removes only the selected entry's directly linked email records and stores a non-PII audit event. Payment-transfer and webhook resolution uses administrator-only, CSRF-protected actions that retain historical records while excluding resolved items from active dashboard attention counts. Product-upload server limits are aligned at 600 MB per PHP file and 650 MB per multipart request.

### Phase 11 security
Credit amounts use strict decimal-to-cent parsing; scientific notation, malformed/excess precision, and out-of-range values are rejected. Every balance mutation locks the user row, enforces `reserved <= total`, checks guarded-update row counts, and appends an idempotent ledger entry in the same transaction. Ledger history is never updated or deleted. Referral attachment is immutable, rejects self/conflicting/late attachment, and URLs use configured `APP_URL`. Buyer/seller/admin queries are scoped to the authenticated subject; admin adjustments require role authorization, explicit CSRF verification, an active target, bounded reason, actor log, and unique event key.

#### Atomic captured-payment recovery
Checkout snapshots a normalized US billing address and webhook finalization compares Stripe's returned address before delivery unlock. A captured Stripe payment remains `captured_pending_finalization` inside the same transaction that redeems credit and writes order, coupon, earnings, commission, payout, fulfillment, referral, and tax-transaction state. Failures roll back required records and persist an actionable `manual_review` recovery marker without releasing reserved credit or charging again. Communications run only after commit through sanitized nonblocking wrappers.

Credits never expire, are marketplace-only, non-transferable, and have no cash value. Refund processing does not automatically restore redeemed credit in the launch implementation. Fully credit-funded seller obligations are labeled `platform_credit_hold`; automatic source-transaction transfer code must not select them because no buyer Stripe charge exists. Admin credit changes require the admin role, explicit CSRF validation, an active target, strict decimal input, a bounded reason, a new ledger row, and a retained admin audit event.

Platform-credit settlement is POST-only and requires the active admin role plus explicit CSRF validation. The service locks both obligation and order, rejects refunded/manual-review/non-internal/source-charge orders and non-payout-ready sellers, transfers the exact stored amount without `source_transaction`, and records actor, entities, amount, idempotency key, and result. Concurrent or repeated success cannot create a second transfer; sanitized failures retain a retryable hold and never affect buyer fulfillment.

### Phase 11 seller-referral lifetime commission
Seller-referral qualification permanently selects either referrer-only $5 store credit or, when the referrer is then an approved seller, an Asset Moth-funded 1% commission calculated per stored seller-payout item using integer-cent half-up rounding. Accruals and linked refund/recovery adjustments are append-only. Disabled, inactive, and deleted store states permanently stop new accrual without cancelling earned balances. Closed UTC-month platform-balance transfers reuse the seller's existing Stripe Connect account, omit `source_transaction`, and retain stable idempotency, processing leases, attempt history, and retryable failures. Active admins retry failed/not-ready batches only through CSRF-protected `POST /admin/seller-referral-payouts/{id}/retry`; immutable audit rows record the result. Unpaid prior-period amounts and post-payout recovery adjustments roll into the next positive batch, and no negative transfer is created.

### Phase 12 recognition security
Designer Management first requires an authenticated admin role and then verifies the user is still an active admin in the database. Recognition POSTs require CSRF, a valid seller and allowlisted action/value, and a multibyte-safe 3–500 character reason. Service entry points repeat active-admin validation. All Founder mutations lock the target designer first, the global recognition mutex second, and read occupied positions only after both locks; unique/check constraints remain the final concurrency guard.

No-op administrative submissions create no administrative history, audit, notification, or email; an authoritative refresh discovered during a Founder no-op may still record and communicate a separate automatic semantic transition. Automatic event and communication keys use insert-only recognition-event IDs; administrative keys use `admin_logs.id`. Unique trigger, history, notification, and email indexes make webhook, daily, and recalculation replay harmless. Rank/badge text is escaped in seller, admin, storefront, product, notification, and email views. Automatic and administrative transitions retain before/after history and existing `admin_logs` audit metadata without exposing buyer data.

Every real admin recognition transition inserts `admin_logs` first inside the same transaction and uses that row ID in history, notification, and email keys. A no-op creates no audit identity. This permits later legitimate state cycles while deduplicating immediate repeats. All recognition clocks parse and compare explicit UTC; `force_active` is the only exception to automatic inactivity.

Automatic recognition transitions use an insert-only event ID rather than a final-state hash. Stable paid/refund trigger keys are unique and bounded; replay recovers missing communication only when the seller’s current semantic rank/Founder state still matches the committed event’s changed after-state and its linked rank/badge history row remains the latest committed semantic history for each affected dimension, including administrative transitions. Joined transactions persist recognition state and events but never communicate before the outer caller commits; a post-commit trigger replay performs safe recovery. Proposed Founder positions are recomputed after seller/mutex locking before assignment.

### Phase 12.1 license validation

Digital Product License requests use `LicenseService::purchasableLicenses()` against the approved product's current enabled license rows. Client-posted prices are ignored: cart totals and checkout/order snapshots are rebuilt from server-side product license prices. Unknown or newly disabled keys invalidate the selection, while completed orders display immutable order-item snapshot fields. Existing seller product ownership, authentication, role, and CSRF boundaries are unchanged.

## Phase 12.2 — Bulk Product Upload & Batch Listing

All bulk-product routes require an approved seller with completed onboarding. Batch access remains constrained to the authenticated seller's designer ownership. Another seller cannot view, edit, continue, submit, or delete a batch they do not own. POST mutations remain protected by the application's CSRF verification.

Shared starting information is used only to prefill products in the guided workflow. Each resulting product remains independently owned and validated through the existing product rules. Client-submitted ownership, status, pricing, license state, and IP-risk state are not trusted over authoritative server-side data.

Bulk-batch deletion remains seller-scoped and must preserve existing permanent-product deletion protections. Draft products may be removed as part of deleting a batch when safe; submitted, approved, published, or historically retained products must not bypass existing order/history protections.

Normal product validation and IP-risk rules remain authoritative. Bulk creation does not bypass required product data, protected-file/manual-delivery rules, license validation, IP-rights confirmation, moderation, or normal submission behavior.
# Phase 12.3 marketing-email boundaries

Digest producers query active users and saved category consent before queueing; the worker rechecks that same category immediately before delivery. Signed category unsubscribe tokens remain bound to the private per-user nonce and application HMAC secret, are idempotent, and cannot change transactional delivery. Followed-shop eligibility is computed internally from `follows`; no seller route, view, export, or campaign tool receives buyer addresses. Admin visibility is aggregate-only.

Registered-user marketing worker checks include current user existence and active status. Favorite-shop delivery additionally intersects queued product/designer IDs with current follows and currently available products/shops, preventing an unfollowed shop from authorizing later delivery. POST unsubscribe confirmation is CSRF-verified in the controller in addition to the router's application-wide POST check.
