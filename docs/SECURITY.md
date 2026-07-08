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
