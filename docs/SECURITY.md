# Security

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

- Phase 8.75 live testing raised seller preview/avatar/banner image validation to 15MB while keeping extension, MIME, image metadata, and server-generated filename checks. PHP upload handling is capped through `public/.user.ini`; Nginx dotfile protection was verified so `.user.ini` returns 403 publicly.
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
Notification updates always bind ID and authenticated user, preventing IDOR; action links accept local paths only. Public mutations use global CSRF and a honeypot, normalize email, avoid enumeration, and store non-secret unsubscribe authorization nonces; HMAC-signed tokens require the environment-only `EMAIL_UNSUBSCRIBE_SECRET` and are verified with `hash_equals`. Campaign and email output is escaped, subjects reject CR/LF, CTA destinations are constrained, and marketing consent is rechecked at delivery. Queue claims are locked, deduplicated, bounded and stale-recoverable; errors are sanitized. Structured logs omit raw email/token/payment secrets. CSV cells whose first non-space character is `=`, `+`, `-`, or `@` are prefixed to neutralize formulas.

Phase 10.5 final hardening restricts invitation POSTs to explicit individual or confirmed filtered modes, prevents administrators from restoring withdrawn consent, commits consent and confirmation queueing atomically, reauthorizes test sends against the active administrator account at delivery time, and validates seller email action links as local paths. Campaign/recipient reconciliation failures cannot change a safely stored message back into a resendable state.

Final Phase 10.5 URL validation rejects browser-normalizable backslashes, control characters, protocol-relative paths, cross-origin hosts/ports, URL credentials, and non-HTTPS absolute campaign CTAs. Log delivery deduplicates under an exclusive lock by message ID and contains no recipient address, template data, body, or unsubscribe token. Webhook-issue alerts are emitted only after successful Stripe signature verification; their controlled copy contains only the normalized event type, allowlisted failure category, and directions to protected logs. Arbitrary exception text and event payload data are excluded, and missing-ID deduplication uses only a fingerprint of the verified payload.

Verified webhook failure coverage begins immediately after successful signature verification, including event lookup and initial persistence. Missing-ID deduplication fingerprints the verified payload without storing it in notifications. Webhook alerts use allowlisted categories and controlled copy, never arbitrary exception text. Mail-log recovery repairs only incomplete trailing writes under lock; malformed complete records fail closed instead of being ignored.

Step 8 centralizes Phase 10.5 operational diagnostics in `OperationalErrorSanitizer`. Before protected logging or diagnostic database storage, it bounds and flattens errors; removes markup and stack traces; and redacts email, authorization, common API-key header/assignment variants, Stripe signatures/secrets/objects, credential assignments, database DSN/passwords, URL userinfo, token-bearing URLs, and unsubscribe-token values. Context labels are normalized and bounded. This does not weaken the stricter webhook administrator notification, which continues to use only controlled event/category copy.
