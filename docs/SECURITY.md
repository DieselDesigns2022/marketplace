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
- Licenses are included permissions, not paid add-ons: product base price is the only buyer-facing price and `license_price` is retained as `0.00` compatibility/snapshot data.
- Buyer-submitted license key lists are normalized and validated server-side against enabled product licenses during add-to-cart and cart update, with Personal always included.
- Checkout validates every selected license again, recalculates line totals from product base prices only, and writes snapshots for all selected included licenses; client-provided prices are not trusted.
- Disabled or missing licenses are not purchasable, and existing products fall back to a safe Personal license if custom rows are missing.
