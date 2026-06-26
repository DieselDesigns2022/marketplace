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
