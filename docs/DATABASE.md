# Database

## Phase 12.4 CSV imports
`product_import_runs` stores seller-scoped detected, selected, imported, skipped, duplicate, invalid, and failed counts plus source, sanitized display filename, temporary protected path, result, and a surviving batch. Confirmation atomically changes only the exact seller selection from `ready` to `queued`; draft claiming and creation share one transaction, and `product_import_images` durably leases at most one source image per request so product and image retries remain bounded and idempotent. Legacy `ready` items are never inferred as selected for a processing run; legacy zero-selection processing runs derive `selected_count` from their preserved terminal imported/failed attempts and mark leftover ready items skipped. `product_import_items` stores curated normalized product-only metadata and final per-item outcomes. `product_import_sources` uses a full SHA-256 canonical identity fingerprint in the unique `(designer_id, source_platform, source_fingerprint)` guard while retaining a shortened display key. `product_import_requirements` durably blocks submission until imported-product AI, licensing, fulfillment, unresolved price, and incompatible source-type choices are explicitly reviewed. Imported products permit `NULL` AI disclosure and price only as incomplete states.

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

Stores seller products linked to designers and optionally categories. Important fields include title, slug, descriptions, price, tag text, file types, license flags/prices, POD/resale settings, AI disclosure, SEO fields, status, rejection reason, featured flag, and sales count. Status values are `draft`, `pending_review`, `approved`, `published`, `rejected`, `disabled`, `archived`, and `deleted`; existing `approved` products are treated as the published/public product state in the current UI.

### `product_images`

Stores public preview image paths, alt text, and sort order for products.

### `product_files`

Stores protected downloadable file metadata: storage path, original name, file size, and MIME type.

### `tags`

Stores tag names and slugs.

### `product_tags`

Join table between products and tags.

### `cart_items`

Stores buyer cart entries by user, product, normalized selected license keys, quantity, and Phase 9 price/license/fulfillment snapshots.

### `orders`

Stores buyer order and payment snapshots. Phase 11 adds reserved/redeemed credit, Stripe-paid amount, normalized billing address, Stripe Tax Calculation/Transaction identifiers and states, internal-completion marker, and finalization key. Captured Stripe payments remain non-deliverable in `captured_pending_finalization` or `manual_review` until atomic finalization succeeds.

### `order_items`

Stores purchased products in each order, including product/designer/license snapshots, unit and license prices, total price, commission rate, fulfillment type, manual-delivery fields, and download tracking placeholders.

### `downloads`

Audit table for product file downloads by user/product/file with IP address and user agent.

### `wishlists`

Join table for buyer wishlisted products. Unique per user/product.

### `follows`

Join table for buyer-followed designers. Unique per user/designer.

### `reviews`

Planned/current foundational table for product/designer reviews. Includes rating, body, and moderation status.

### `referrals`

Stores one immutable referrer relationship per referred user. Buyer and seller intent, qualification status, reward amount per party, qualifying order/item, event key, and rewarded timestamp are separate so the $1.50 buyer and $5.00 seller events never overwrite one another.

### `creator_rank_history`

Tracks designer rank changes, old/new rank, admin changer, reason, and timestamp.

### `marketplace_credits`

Stores exact `DECIMAL(12,2)` total and reserved balances; available credit is total minus reserved. Credits never expire, are marketplace-only, non-transferable, and have no cash value.

### `credit_transactions`

Append-only credit ledger with reservation, redemption, release, grant, and admin-adjustment types; unique idempotency key; status/timestamps; and optional order, referral, acting-admin, and related-reservation references. Existing ledger rows are not rewritten during normal processing.

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

- `users.id` links to `designer_applications.user_id`, `designers.user_id`, `orders.user_id`, `cart_items.user_id`, `wishlists.user_id`, `follows.user_id`, referral participants, credit balances, and ledger users/admin actors.
- `designers.id` links to `products.designer_id`, `order_items.designer_id`, `seller_earnings.designer_id`, and `platform_commissions.designer_id`.
- `products.id` links to images, files, cart items, order items, downloads, wishlists, reviews, and tags.
- `orders.id` links to `order_items.order_id`, `seller_earnings.order_id`, `platform_commissions.order_id`, seller payout obligations, coupon usage, payment transactions, and credit ledger rows.

## Important status fields

- Users: `active`, `disabled`.
- Applications: `pending`, `approved`, `denied`.
- Designers: `approved`, `disabled`.
- Products: `draft`, `pending_review`, `approved`, `published`, `rejected`, `disabled`, `archived`, `deleted`.
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
- `product_license_types` links products to enabled seller license permissions with description override, display order, and seller-configured add-on pricing. The `price` column stores seller-configured add-on pricing for enabled non-personal licenses; Personal/included licenses should remain `0.00`.
- License types support Personal as the included/free default plus seller-enabled add-on permissions that may be free (`$0.00`) or paid. Active license types include Basic, Commercial, POD, Wholesale, two Fabric options, VA, two Reseller options, and Extended Commercial. Buyers see add-on license pricing when selecting paid add-on permissions, and cart/order totals include the product base price plus selected paid add-on license prices.
- `cart_items.license_type` and `order_items.license_type` are flexible `VARCHAR(255)` fields that may contain a normalized comma-separated list of selected license keys. Buyers can select multiple licenses/permissions, including paid add-ons when enabled.
- Checkout validates every selected license server-side and does not silently substitute invalid licenses.
- `order_items` stores `license_name`, `license_price`, `license_description`, and `license_snapshot` to preserve selected licenses after later seller edits; `license_price` should be `0.00` for included/free permissions and should snapshot selected paid add-on prices where applicable.
- Migration: `database/migrations/2026_07_01_phase_8_5_licensing_system.sql` seeds the initial license types and backfills existing products. Compatibility migration `database/migrations/2026_07_01_phase_8_5_license_included_multi_select_fix.sql` is retained as a no-op because add-on pricing and expanded license type corrections superseded the earlier included-license-only pricing correction.

## Phase 8.75 schema additions
- `product_images.original_image_path` stores the private source path for newly uploaded public preview images, relative to `storage/app/private/`. This private source is used only for regeneration and is not displayed on buyer-facing pages.
- `product_images.watermark_status` and `product_images.watermark_error` record whether public preview watermarking succeeded, fell back to the original public preview, or failed.
- `designers.facebook_url`, `instagram_url`, `tiktok_url`, `pinterest_url`, `etsy_url`, and `shopify_url` store optional validated storefront links. `website_url` remains the general website field.
- Migration: `database/migrations/2026_07_01_phase_8_75_watermarks_social_links.sql`.

## Phase 9 schema additions
Phase 9 migration `2026_07_01_phase_9_cart_orders_downloads_delivery.sql` adds product `fulfillment_type` and `manual_delivery_instructions`; cart price/license/fulfillment snapshots; future-ready order tax/coupon/fulfillment fields; order item product, seller, license, fulfillment, Google Drive email, manual delivery status, delivery notes/timestamps, purchased file version, download count, and expiration placeholders; and download log order/order-item/status fields.

`not_applicable` is used for downloadable products and other non-manual-delivery order items. Manual Google Drive delivery statuses are `pending_delivery`, `buyer_email_needed`, `ready_for_seller_delivery`, `delivered`, and `cancelled_refunded`. Order statuses include `pending`, future `paid`/`failed`, `cancelled`, `refunded`, `partially_fulfilled`, and `fulfilled`, while legacy `completed` remains accepted for existing data compatibility.

## Phase 10 — Stripe Payment Integration
Migration `2026_07_02_phase_10_stripe_payment_integration.sql` adds Stripe payment reconciliation, webhook logging, and seller payout foundation fields.

### `orders` payment fields
- `payment_provider`
- `payment_status`
- `stripe_checkout_session_id`
- `stripe_payment_intent_id`
- `stripe_customer_id`
- `stripe_charge_id`
- `stripe_payment_status`
- `stripe_amount_total`
- `stripe_currency`
- `stripe_fee_total`
- `platform_commission_total`
- `paid_at`
- `failed_at`
- `refunded_at`
- `partially_refunded_at`
- `canceled_at`
- `payment_error`
- `payment_retry_count`
- `manual_review_required`
- `manual_review_reason`

### `order_items` payout fields
- `platform_commission_amount`
- `seller_payout_amount`
- `seller_payout_status`
- `stripe_transfer_id`
- `stripe_transfer_error`
- `paid_at`
- `payout_ready_at`

### `designers` Stripe Connect fields
- `stripe_connect_account_id`
- `stripe_charges_enabled`
- `stripe_payouts_enabled`
- `stripe_details_submitted`
- `stripe_account_status`
- `stripe_onboarding_started_at`
- `stripe_onboarding_completed_at`

### New Phase 10 tables
- `stripe_events` stores incoming Stripe webhook ids, event types, processing status/errors, payload text, and timestamps. `stripe_events.stripe_event_id` is unique for duplicate webhook protection.
- `payment_transactions` stores order-linked payment/refund/failure/manual-review transaction records and Stripe references for admin audit visibility.
- `seller_payouts` stores one aggregate payout ledger row per `order_id`/`designer_id`; `order_items` stores item-level commission and seller payout amounts.

`stripe_fee_total` is future/reconciliation-ready and may remain `NULL` until safe Stripe balance transaction fee retrieval is added. Buyer self-cancellation and seller refund/cancellation request approval workflows are not part of Phase 10; Phase 10 only records webhook refund status when Stripe reports it.

### Phase 10 Stripe Connect payout fields
Designers store Stripe Connect status in `stripe_connect_account_id`, `stripe_charges_enabled`, `stripe_payouts_enabled`, `stripe_details_submitted`, `stripe_account_status`, `stripe_onboarding_started_at`, and `stripe_onboarding_completed_at`. Order items and `seller_payouts` snapshot `platform_commission_amount` and `seller_payout_amount` so future commission changes do not alter historical orders. Payout statuses include `pending_stripe_onboarding`, `pending_transfer`, `transferred`, and `transfer_failed`; transfer failures are admin-visible and do not mark buyer orders unpaid.

#### Phase 10 correction: payout retry scope
Pending seller payout retries are scoped to payout-ready designers and webhook-confirmed paid orders only. `seller_payouts.payout_status` and matching `order_items.seller_payout_status` move from `pending_stripe_onboarding` to `pending_transfer`, `transferred`, or `transfer_failed`; manual-review, unpaid, and refunded orders are not transfer-attempted.

#### Stripe charge id and pending transfers
`orders.stripe_charge_id` is used as the Stripe transfer `source_transaction` for seller payouts when available. Paid orders missing a charge id keep seller payout rows in `pending_transfer` until a later webhook stores the charge id and retry logic can safely attempt the transfer.

## Phase 10.1 product cleanup schema
- Migration `2026_07_07_phase_10_1_product_cleanup.sql` expands `products.status` to include `archived` and `deleted` for product cleanup workflows.
- Existing `approved` products remain the published/public product state. UI labels present `approved` products as Published.
- Products with completed `paid` or `partially_refunded` orders must remain in `products` so order items, downloads, seller sales, and admin records can continue joining historical product data.

## Phase 10.2 Coupon Database Objects
- `coupons` stores normalized unique codes, `platform` or `seller` scope, optional `seller_id`, percent/fixed discount value, start/end dates, active flag, minimum eligible cart amount, total usage limit, per-user usage limit, and denormalized usage count.
- `coupon_restrictions` stores optional seller/product/category restrictions with a unique `(coupon_id, restrictable_type, restrictable_id)` key and lookup index.
- `coupon_usages` stores paid-order usage rows with `coupon_id`, `user_id`, `order_id`, code snapshot, discount amount, a unique order key, and coupon/user lookup index.
- `orders.coupon_discount`, `orders.coupon_id`, `orders.coupon_code`, and `orders.coupon_snapshot` snapshot order-level coupon state.
- `order_items.coupon_id`, `order_items.coupon_code`, and `order_items.coupon_discount` snapshot item-level allocation; `order_items.total_price` stores the discounted item total used for commission and seller payout calculations.
- Phase 10.3B introduced Stripe Tax snapshots; Phase 11 now applies credit after authoritative tax and implements referral rewards.

## Phase 10.3B Stripe Tax compliance
Phase 10.3B introduced Stripe Tax fields. In the current Phase 11 lifecycle, `tax_status=calculated` and `tax_calculation_id` identify the pre-credit calculation; `tax_snapshot` and `billing_address_snapshot` retain authoritative context. `tax_transaction_id`, `tax_transaction_status`, and `tax_collected_at` are populated only after idempotent `create_from_calculation` succeeds during finalization. Tax remains excluded from seller earnings, payouts, and commission math.

## Seller license presets

`seller_license_presets` stores optional store-level default license settings for approved designers. Rows are scoped by `designer_id` and `license_type_id`, with enabled state, default add-on price, optional default description, and sort order. These presets only prefill new product forms; saved product license rows in `product_license_types` remain the checkout and order-snapshot source of truth.

The migration `database/migrations/2026_07_11_seller_product_cleanup_launch_faq.sql` creates `seller_license_presets` if it is missing. It also consolidates legacy PNG/Sublimation categories into the canonical `PNG Files` category with slug `png-files`. Products assigned to old Sublimation categories or duplicate PNG categories are moved to the canonical `png-files` category. Old Sublimation rows and duplicate PNG rows by slug/name are disabled, not deleted, for safety.

### Phase 10.4 IP risk database tables

`ip_risk_terms` stores canonical advisory terms with normalized unique text, category, enabled state, admin audit columns, and `users.id` foreign keys using `ON DELETE SET NULL` for admin references. `ip_risk_term_aliases` stores globally unique normalized aliases and references canonical terms with `ON DELETE RESTRICT`; terms are disabled rather than deleted to preserve history.

`product_ip_risk_scans` stores product/user scan records, content fingerprints, match fingerprints, active counts, and scan timestamps. `product_ip_risk_detections` stores historical detections with active/inactive state, `matched_value_key` for MariaDB-safe canonical/alias duplicate protection, source field enum values (`title`, `description`, `tags`, `seo_title`, `seo_description`, `file_name`), carried-forward `first_detected_at`, and latest `last_detected_at`.

`product_ip_risk_states` stores one current IP review state per product, separate from `products.status`. `product_ip_rights_confirmations` binds the exact seller checkbox text to product, scan, and seller user. `product_ip_risk_review_history` preserves every admin IP review transition with previous/new IP state and previous/new product status. Product-related FKs are restrictive; safe permanent deletion explicitly removes Phase 10.4 child rows before deleting a product.

The Phase 10.4 migration and fresh schema seed 12 starter canonical terms and one Coke alias. These records are advisory/testing-oriented starter data only; they are incomplete and are not comprehensive legal, trademark, copyright, celebrity, franchise, sports, music, or protected-content coverage.

## Phase 10.5 tables

### `notifications`
Each row belongs to `user_id` and stores `notification_type`, an `audience` of `buyer`, `designer`, `admin`, or `system`, escaped display `title` and `message`, an optional validated local `action_url`, stable `event_key`, `read_at`, and `created_at`. Unique `(user_id, event_key)` enforces event deduplication. `(user_id, created_at)` supports newest-first display and `(user_id, read_at, created_at)` supports unread queries. Deleting the owning user cascades to notifications.

### `email_preferences`
There is zero or one row per user, keyed by `user_id`, with `marketing_opt_in`, `marketing_opted_in_at`, `marketing_opted_out_at`, a unique non-secret `unsubscribe_nonce`, and `created_at`/`updated_at`. The nonce participates in HMAC-signed unsubscribe authorization; it is not the complete token. User deletion cascades to the preference row.

### `waitlist_entries`
Rows contain `name`, normalized unique `email`, optional `business_name`, `interest_type` (`seller`, `buyer`, `both`, or `tester`), controlled `source` (`direct`, `homepage`, `seller`, `social`, `referral`, or `campaign`), and `status` (`subscribed`, `invited`, `unsubscribed`, or `suppressed`). Consent and delivery history use `consent_at`, `unsubscribed_at`, `confirmation_sent_at`, `invited_at`, `created_at`, and `updated_at`; confirmation/invitation timestamps mean successful transport delivery, not queueing. Email and `unsubscribe_nonce` are independently unique. The `(status, interest_type, source, created_at)` index supports filtering and pagination.

### `email_campaigns`
Campaigns store `campaign_type` (`promotional` or `launch_invite`), a service-controlled `audience`, escaped `subject` and plain-text `body`, optional validated CTA label/URL, and creator attribution. Status is `draft`, `queued`, `sending`, `sent`, `completed`, `partially_failed`, `failed`, or `cancelled`; the status/creation index supports administration and processing. `created_by` references an administrator user with deletion restricted. Lifecycle timestamps are `queued_at`, `sent_at`, `completed_at`, `cancelled_at`, `created_at`, and `updated_at`. `sent_at` records that at least one delivery succeeded; `completed_at` records terminal recipient processing, including a truthful zero-delivery `completed` result.

### `email_campaign_recipients`
The campaign, nullable registered-user/waitlist references, and selected email/name fields preserve the intended recipient identity and audience snapshot. Operational `status`, `last_error`, and `updated_at` remain mutable. Status is `pending`, `queued`, `sent`, `failed`, `cancelled`, or `suppressed`; `(campaign_id, email)` is unique and `(campaign_id, status)` supports aggregate calculations. Campaign deletion cascades, and the nullable registered-user/waitlist foreign keys use `ON DELETE SET NULL` at schema level. The guarded admin permanent-user-deletion workflow blocks accounts with retained marketplace history; for an otherwise eligible pre-launch account, its directly linked campaign-recipient and email-delivery records are deleted transactionally with the account so personal data is not retained.

### `email_messages`
Durable work records include `classification` (`transactional` or `marketing`), recipient envelope fields, subject, controlled template name, JSON `template_data`, optional campaign/recipient/waitlist relationships, and a unique `deduplication_key`. Status is `pending`, `processing`, `sent`, `failed`, or `cancelled`. Delivery state uses `attempt_count`, `next_attempt_at`, `claimed_at`, `sent_at`, `last_error`, `created_at`, and `updated_at`. `(status, next_attempt_at, created_at)` supports due-work claiming and `(status, claimed_at)` supports stale-claim recovery. Related campaign, recipient, and waitlist foreign keys are nullable and use `ON DELETE SET NULL`. Once safely recorded, `sent` and `sent_at` never return to a resendable state, although `last_error` may later receive a sanitized reconciliation diagnostic.

The baseline schema repeats the additive Phase 10.5 migration definitions. The unreleased migration is non-idempotent: back up first, inspect migration state, apply it once, and activate schema-dependent code only afterward.

### Phase 10.5 campaign completion semantics
`sent_at` is set only when at least one recipient was successfully delivered (including partially failed campaigns). `completed_at` records terminal recipient processing for `sent`, `completed`, `partially_failed`, or `failed`. `completed` means processing ended without delivery failures but zero messages were delivered because no recipient was eligible or all snapshotted recipients were suppressed/excluded.

### Phase 10.5 refund communication state
No duplicate refund-total column is added. Existing `payment_transactions` rows with `transaction_type` `partial_refund` or `refund` store Stripe's cumulative refunded amount in `amount`; their maximum amount is the authoritative prior cumulative value for monotonic communication decisions. Stable refund communication keys include order, resulting partial/full state, and cumulative refunded cents. Older, equal, or smaller observations may remain in protected transaction history but cannot regress the order or create another buyer message.



## Phase 10.6 receipt and category data

`designers.receipt_note VARCHAR(500)` and `designers.receipt_image_path VARCHAR(255)` store the current seller setting. `order_items.seller_receipt_note_snapshot VARCHAR(500)` and `order_items.seller_receipt_image_path_snapshot VARCHAR(255)` preserve what applied when checkout created the item, so later settings do not rewrite old receipts. Snapshot references prevent historical images from being removed.

The current nine canonical categories are Engagement Graphics (`engagement-graphics`), Social Media Graphics (`social-media-graphics`), Libby Wraps (`libby-wraps`), Digital Papers (`digital-papers`), Freebies (`freebies`), Digital Services (`digital-services`), Customs / Personalized (`customs-personalized`), Google Drives (`google-drives`), and Bundles & Collaborations (`bundles-collaborations`). Apply `database/migrations/2026_07_28_phase_10_6_dashboard_cleanup_usability.sql` first, followed by `database/migrations/2026_07_28_phase_10_6_live_fixes.sql`, then `database/migrations/2026_07_29_phase_10_6_issue_resolution.sql`. These migrations normalize case and punctuation/spacing/slash/hyphen variants, move product assignments, consolidate category coupon restrictions, and deactivate rather than delete duplicate history. Invalid current seller values are omitted (`NULL`) from new snapshots rather than blocking checkout; valid historical snapshot references continue to retain their images. Category cleanup deterministically selects at most one target for each duplicate: normalized canonical slug wins over normalized name, ties use stable canonical sequence/ID ordering, and a category never maps to its own ID.

### Phase 10.6 live fixes
`2026_07_28_phase_10_6_live_fixes.sql` idempotently adds/reactivates `google-drives` and `bundles-collaborations` after the current category sort maximum. Canonical slugs win during normalized duplicate merging; product and coupon-category references are reassigned before duplicate rows are retained inactive.

### Phase 10.6 issue-resolution data

`2026_07_29_phase_10_6_issue_resolution.sql` adds nullable admin-resolution timestamp, admin user, and note fields to the Phase 10 Stripe migration's `seller_payouts` and `stripe_events` tables. Resolution removes an item from the active dashboard queue while retaining the original payout or webhook record and its historical details.

### Phase 11 data model
`users.referral_code` is unique and URL-safe. `referrals` has one non-null `referred_user_id` per account, immutable referrer/code snapshots, buyer and seller intent/status, qualifying order/item references, reward amounts, and reward timestamps. Rejected duplicate legacy rows remain as audit rows with a null referred-user attachment.

`marketplace_credits` stores `total_balance` and `reserved_balance` as `DECIMAL(12,2)` with the invariant `0 <= reserved <= total`. `credit_transactions` is append-only: reservation, redemption, release, grant, and admin-adjustment rows have unique idempotency keys and optional order, referral, admin, and related-reservation references. Balance changes and ledger inserts share one row-locked transaction. Orders snapshot tax, credit reserved/redeemed, Stripe-paid amount, completion mode, and finalization key. Buyer qualifying order, seller qualifying order, and seller qualifying item columns have lookup indexes and `ON DELETE RESTRICT` foreign keys so order/referral history cannot be orphaned.

#### Phase 11 tax and reward lifecycle correction
Orders retain `tax_calculation_id`, the immutable calculation snapshot, normalized `billing_address_snapshot`, and distinct `tax_transaction_id` / `tax_transaction_status`. A pending order is only `calculated`; `tax_collected_at` is written after Stripe Tax `create_from_calculation` succeeds. Referral relationships retain independent buyer and seller referrer/referred amount snapshots, event keys, qualifying references, and timestamps. Platform-funded credit orders use `platform_credit_hold` payout obligations because no buyer charge exists for a source-transaction transfer; an admin/platform-balance transfer workflow must resolve the hold. Legacy conversions preserve already migrated states on rerun.

`seller_payouts.platform_credit_attempt_key` uniquely identifies settlement attempts; `platform_credit_settled_at` and `platform_credit_settled_by` audit successful admin settlement. A failed Stripe call returns the row to `platform_credit_hold` with a sanitized retry reason; `transferred` stores the Stripe transfer ID. The settlement-admin foreign key uses `ON DELETE RESTRICT`.

### Phase 11 seller-referral lifetime commission
Seller-referral qualification permanently selects either referrer-only $5 store credit or, when the referrer is then an approved seller, an Asset Moth-funded 1% commission calculated per stored seller-payout item using integer-cent half-up rounding. Accruals and linked refund/recovery adjustments are append-only. Disabled, inactive, and deleted store states permanently stop new accrual without cancelling earned balances. Closed UTC-month platform-balance transfers reuse the seller's existing Stripe Connect account, omit `source_transaction`, and retain stable idempotency, processing leases, attempt history, and retryable failures. Active admins retry failed/not-ready batches only through CSRF-protected `POST /admin/seller-referral-payouts/{id}/retry`; immutable audit rows record the result. Unpaid prior-period amounts and post-payout recovery adjustments roll into the next positive batch, and no negative transfer is created.

### Phase 12 creator-recognition storage
`designers.creator_rank` is the effective public rank; `calculated_rank` is the sales-derived value, `qualifying_sales_count` is the distinct qualifying order-and-seller count, and `last_qualifying_sale_at` drives progress and Founder inactivity. Each order counts once per represented seller, and cumulative refund allocation removes only seller participation with no positive merchandise value remaining. `rank_override`, `rank_override_value`, reason, actor, and timestamp retain the effective-rank override without freezing calculation. Founder state uses `founder_position`, `founder_earned_at`, `founder_qualifying_order_id` (the auditable tenth-order tie-break), `founder_active`, `founder_inactive_at`, and the automatic/force-active/force-inactive override fields.

`creator_recognition_lock` contains the single global Founder mutex. Writers lock the designer first and this row second before reading occupied positions. The unique `designers_founder_position_unique` index and 1–50 check enforce permanent, nonduplicated positions; the recognition lookup index supports approved-seller backfill. `creator_rank_history` preserves legacy rows and adds calculated/effective before/after values, count, source, actor/reason, and a unique event key; new rows represent only calculated/effective rank transitions, not count- or timestamp-only refreshes. `creator_badge_history` stores only semantic Founder assignment, active/mode transition, or administrative Founder-action before/after JSON, position, source, actor/reason, and unique event key; audit-field repairs alone create no row. Historical qualification sorts by tenth-sale time, tenth order ID, then designer ID.

The repeatable Phase 12 migration widens the legacy enum, converts `Legend` to `Diamond`, installs guarded columns/indexes/constraints/tables, seeds `calculated_rank` only on first installation, preserves orphan legacy history without imposing unsafe new history foreign keys, and uses `INSERT IGNORE` for the mutex row.

Recognition timestamps are UTC. New Phase 12 audit relationships are guarded, repeatable `ON DELETE RESTRICT` foreign keys: both designer override-admin columns to `users`, Founder qualification order to `orders`, and badge-history designer/admin actors to their retained parent rows. Only legacy `creator_rank_history` omits new foreign keys so pre-existing orphan audit rows remain deployable.

`creator_recognition_events` is the durable automatic-transition journal: source, optional unique 190-character trigger key, complete recognition-state before/after JSON, rank/Founder flags, and UTC creation time. Its ID forms `recognition-event:{id}` history and communication keys; count-, timestamp-, and Founder audit-field-only refreshes create no event. Existing-position grant and inapplicable restore remain administrative no-ops, while a real rank or Founder-active transition discovered by their authoritative refresh is recorded separately as an automatic semantic event. The table has a guarded `ON DELETE RESTRICT` designer foreign key and designer/date lookup index.
Recognition-event JSON is allowlisted recognition state only; it never snapshots payout, Stripe, receipt, profile, social-link, or storefront fields. Phase 12 has six recognition foreign keys in total.

### Phase 12.1 Digital Product License seed

`2026_08_10_phase_12_1_digital_product_license.sql` repeatably upserts the active `digital-product` row in `license_types`. It adds no tables or columns and does not alter `product_license_types`, seller configurations, carts, or historical `order_items` snapshots. Sellers opt products into the new free-or-paid permission through the existing product-license rows.

## Phase 12.2 — Bulk Product Upload & Batch Listing
`product_batches` stores `id`, required `designer_id`, required `name`, and creation/update timestamps. Its indexed `designer_id` references `designers.id` with `ON DELETE CASCADE`. `product_batch_items` stores `id`, required `batch_id`, required `product_id`, `sort_order`, nullable JSON `validation_errors`, nullable JSON `submission_errors`, nullable `submitted_at`, and `created_at`. `validation_errors` records current derived product validation, while `submission_errors` preserves the latest submission-specific failure across the redirect until any successful product save or successful submission clears it; current validation is recalculated separately. Its batch/order index supports ordered reopening; `batch_id` and `product_id` reference their parent rows with `ON DELETE CASCADE`. A unique key on `product_id` means a normal product can belong to at most one batch.

These tables contain metadata and membership only. Every member remains an independent row in `products`; product content, unique slug, tags, licenses and paid prices, preview images, protected files, moderation status, and IP-risk state remain in their existing canonical tables. Removing an eligible draft through the batch workflow removes that product and its membership; submitting or independently editing a product does not remove its membership.
# Phase 12.3 email preferences

Migration `2026_08_15_phase_12_3_email_preferences_digests.sql` adds `weekly_emails`, `monthly_emails`, `favorite_shop_emails`, and `preference_changed_at` to `email_preferences`, plus eligibility indexes. Existing rows initialize every category from `marketing_opt_in`; nonce and historical consent timestamps are retained.

Migration `2026_08_16_phase_12_3_digest_content_claims.sql` adds `email_digest_content_claims`. Each row binds an authoritative user/product pair and overlapping digest period to its category and durable queue message. The overlap index supports cross-preference assignment, while the message/product uniqueness constraint makes claim replay idempotent.

### Phase 12.4 correction state
`product_import_runs.status` includes the transient `processing` claim used to make confirmation idempotent. `product_batch_items.submission_errors` separately retains the latest submission failure across the redirect while `validation_errors` remains derived from current product data. Imported unresolved prices remain nullable until explicitly saved by the seller; source sale prices remain normalized metadata rather than replacing the Asset Moth base price.

## Phase 12.5 messaging
`message_conversations` binds the authoritative buyer, seller user, designer, and optional product/order/order-item context. Its immutable `context_label` stores type-aware durable wording: `Product: …` for product starts, `Storefront: …` for storefront starts, and `Purchased product: …` for order-item starts. Order-item labels prefer the existing `order_items.product_title` purchase snapshot. Empty compose-only rows have no `last_message_at` and are excluded from both inboxes.

Read and archive state is stored separately for the buyer and seller participant. Archiving removes a thread only from that participant's active inbox; archived threads remain accessible through the archived inbox, and archiving never deletes `conversation_messages` or any other conversation history. `conversation_messages` is immutable thread history. `message_attachments` contains metadata and randomized names only; image bytes are below `storage/protected_uploads/messages`, outside public uploads.

Either participant may create a `message_blocks` row, but only the user who created that block may remove it. `message_reports` permits the reasons `abuse`, `spam`, `inappropriate`, and `other`; moderation status is `open`, `reviewing`, `resolved`, or `dismissed`. There is one current report per participant/thread. Reopening or updating a report to `open` clears its prior moderator identity, moderator notes, and reviewed timestamp. Messaging participant/sender/reporter/moderator foreign keys are retained history: permanent account deletion detects them before beginning deletion rather than removing that history.
