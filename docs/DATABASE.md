# Database

## Source of truth

The current baseline schema is `database/schema.sql`. Phase migrations live in `database/migrations`.

## Tables

### `users`

Stores accounts, password hashes, role (`buyer`, `designer`, `admin`), status, referral code, and timestamps.

### `designer_applications`

Stores seller applications submitted by users. Important status values are `pending`, `approved`, and `denied`. Includes display/store intent, portfolio/social/design fields, AI usage, agreement, denial reason, and admin notes.

### `designers`

Stores approved designer storefronts linked to users. Includes public store fields, avatar/banner paths, status, creator rank, featured flag, sales/follower/rating counters, and SEO metadata.

### `categories`

Stores product categories with slug, description, image, active flag, and sort order.

### `products`

Stores seller products linked to designers and optionally categories. Important fields include title, slug, descriptions, price, tag text, file types, license flags/prices, POD/resale settings, AI disclosure, SEO fields, status, rejection reason, featured flag, and sales count. Status values are `draft`, `pending_review`, `approved`, `rejected`, and `disabled`.

### `product_images`

Stores public preview image paths, alt text, and sort order for products.

### `product_files`

Stores protected downloadable file metadata: storage path, original name, file size, and MIME type.

### `tags`

Stores tag names and slugs.

### `product_tags`

Join table between products and tags.

### `cart_items`

Stores buyer cart entries by user, product, and license type. License type is `personal` or `commercial`.

### `orders`

Stores buyer orders with status (`pending`, `paid`, `completed`, `failed`, `refunded`), payment processor/mode, subtotal, credits applied, and total.

### `order_items`

Stores purchased products in each order, including product, designer, license type, unit/commercial prices, total price, and commission rate.

### `downloads`

Audit table for product file downloads by user/product/file with IP address and user agent.

### `wishlists`

Join table for buyer wishlisted products. Unique per user/product.

### `follows`

Join table for buyer-followed designers. Unique per user/designer.

### `reviews`

Planned/current foundational table for product/designer reviews. Includes rating, body, and moderation status.

### `referrals`

Tracks buyer/designer referrals, status, reward status, sales count, and estimated earnings.

### `creator_rank_history`

Tracks designer rank changes, old/new rank, admin changer, reason, and timestamp.

### `marketplace_credits`

Stores user marketplace credit balance.

### `credit_transactions`

Stores credit balance changes with type and description.

### `seller_earnings`

Stores per-sale seller earning records with gross sale, marketplace commission, seller earning, and status.

### `platform_commissions`

Stores marketplace commission records per order/product/designer, including referral commission placeholder.

### `ads`

Stores ad campaign placeholders/management records with product/designer, placement, dates, status, impressions, and clicks.

### `homepage_features`

Stores featured products, designers, or categories for homepage placement.

### `admin_logs`

Stores admin actions with entity type/id and JSON metadata.

## Key relationships

- `users.id` links to `designer_applications.user_id`, `designers.user_id`, `orders.user_id`, `cart_items.user_id`, `wishlists.user_id`, and `follows.user_id`.
- `designers.id` links to `products.designer_id`, `order_items.designer_id`, `seller_earnings.designer_id`, and `platform_commissions.designer_id`.
- `products.id` links to images, files, cart items, order items, downloads, wishlists, reviews, and tags.
- `orders.id` links to `order_items.order_id`, `seller_earnings.order_id`, and `platform_commissions.order_id`.

## Important status fields

- Users: `active`, `disabled`.
- Applications: `pending`, `approved`, `denied`.
- Designers: `approved`, `disabled`.
- Products: `draft`, `pending_review`, `approved`, `rejected`, `disabled`.
- Orders: `pending`, `paid`, `completed`, `failed`, `refunded`.
- Reviews: `pending`, `approved`, `rejected`.
- Ads: `draft`, `active`, `paused`, `ended`.

## Product images and files

Product images are public preview assets. Product files are protected downloadable assets and should be served only through authorized download routes.

## Orders, earnings, and commissions

Orders contain one or more order items. Each order item can produce seller earning and platform commission records. The default commission rate in `order_items` is `.2000`, representing a 20% platform commission unless changed by future business logic.

## Phase 8 search and browsing data usage

Phase 8 uses existing product, category, designer/store, tag, AI disclosure, POD permission, featured, price, file type, and existing commercial-license fields. It does not add Phase 8.5 licensing schema or cart/order license changes.

Added migration: `database/migrations/2026_06_30_phase_8_search_browsing_indexes.sql`. It uses `CREATE INDEX IF NOT EXISTS` so the index step is safe to re-run/idempotent in database environments that support this syntax.

Indexes added for browsing performance:

- `idx_products_public_browse` on `products(status, is_featured, created_at, id)`.
- `idx_products_category_status` on `products(category_id, status, created_at)`.
- `idx_products_designer_status` on `products(designer_id, status, created_at)`.
- `idx_products_price_status` on `products(status, price)`.
- `idx_products_ai_pod_status` on `products(status, ai_disclosure, pod_allowed)`.
- `idx_designers_status_slug` on `designers(status, store_slug)`.
- `idx_product_tags_tag_product` on `product_tags(tag_id, product_id)`.

## Phase 8.5 licensing tables
- `license_types` stores platform-level license definitions using a stable `license_key`, display name, description, active flag, and sort order so future license types can be added without changing the product schema.
- `product_license_types` links products to enabled seller license permissions with description override and display order. The `price` column is retained for compatibility but included licenses should store `0.00`; it is not buyer-facing pricing.
- License types support Personal as the included/free default plus seller-enabled add-on permissions that may be free (`$0.00`) or paid. Active license types include Basic, Commercial, POD, Wholesale, two Fabric options, VA, two Reseller options, and Extended Commercial. Product totals include the product base price plus selected paid add-on license prices.
- `cart_items.license_type` and `order_items.license_type` are flexible `VARCHAR(80)` fields that may contain a normalized comma-separated list of selected license keys. Buyers can select multiple included licenses.
- Checkout validates every selected license server-side and does not silently substitute invalid licenses.
- `order_items` stores `license_name`, `license_price`, `license_description`, and `license_snapshot` to preserve all selected included licenses after later seller edits; `license_price` should be `0.00` for included licenses.
- Migration: `database/migrations/2026_07_01_phase_8_5_licensing_system.sql` seeds the initial license types and backfills existing products. Corrective migration `database/migrations/2026_07_01_phase_8_5_license_included_multi_select_fix.sql` zeros existing product license prices and sets the price default to `0.00` after the original Phase 8.5 migration.
