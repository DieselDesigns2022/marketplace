# Digital Design Marketplace MVP

PHP 8+/MySQL skeleton for **[MARKETPLACE NAME TBD]** — “The marketplace built by designers, for designers.”

## Quick start

1. Create a MySQL database.
2. Copy `.env.example` to `.env` and update credentials.
3. Import `database/schema.sql`.
4. Point your web server document root to `public/`.
5. Ensure `storage/protected_uploads/` is writable and remains outside `public/`.

Default admin can be created by registering a user, then updating `users.role` to `admin` in MySQL.

## MVP scope

Includes public browse/search/product/store pages, auth, buyer dashboard, designer application and seller dashboard, admin moderation, cart, simulated checkout, protected downloads, referral/credit/ad skeletons, and database schema for Phase 2 expansion.
