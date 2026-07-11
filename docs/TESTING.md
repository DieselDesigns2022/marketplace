# Testing

## Public visitor testing

- Open homepage.
- Open browse page.
- Open category pages.
- Open approved product pages.
- Open designer store pages.
- Open sell and static pages.
- Confirm public pages do not expose draft/rejected/disabled products.

## Buyer testing

- Register a buyer.
- Log in and out.
- Update account details.
- Wishlist/unwishlist an approved product.
- Follow/unfollow a designer store.
- View buyer dashboard, wishlist, following, referrals, purchases, and order detail.

## Seller testing

- Submit designer application.
- Verify pending application state.
- Approve application as admin.
- Access seller dashboard.
- Edit storefront settings.
- Create/edit products.
- Upload preview images.
- Upload protected product files.
- Submit products for review.
- View sales, referrals, and rank pages.

## Admin testing

- Log in as admin.
- Review admin dashboard.
- Approve/deny applications.
- Moderate products.
- Manage categories.
- Review designers/users.
- Review orders/order detail.
- Review homepage features and ads pages.

## Database verification

- Confirm migrations apply cleanly.
- Confirm expected tables and columns exist.
- Confirm status fields contain expected values.
- Confirm checkout creates Stripe-backed pending order and order item records, plus seller earning/payout placeholder rows where applicable; payment finalization is webhook-driven in Phase 10.
- Confirm downloads table logs download attempts.

## Upload testing

- Test accepted preview image upload.
- Test rejected invalid upload.
- Test replacing/deleting previews where supported.
- Test protected file upload.
- Confirm protected files are not directly public.

## Checkout/download testing

- Add product to cart.
- Switch license type where commercial license is enabled.
- Submit checkout and confirm a Stripe-backed pending order is created, then the buyer is redirected to Stripe Checkout; do not treat the browser success redirect as payment proof.
- Confirm cart clears after checkout.
- Confirm order appears in purchases.
- Confirm authorized download works only for paid/fulfilled/completed orders and denied attempts are logged otherwise.
- Confirm unauthorized user cannot download the file.

## Mobile/responsive testing

- Check homepage, browse, product, store, cart, checkout, dashboards, and admin pages at mobile widths.
- Confirm navigation remains usable.
- Confirm forms remain readable and submit buttons are accessible.

## Regression testing

Before merge, smoke test:

- Public home/browse/product/store.
- Register/login/logout.
- Buyer dashboard.
- Seller dashboard.
- Admin dashboard.
- Cart and checkout.
- Purchased download.

## Formatting-only PR testing rules

- Confirm no intentional behavior changes.
- Run syntax checks for touched PHP files.
- Use `git diff --check`.
- Manually smoke test any area affected by reformatted templates/controllers.

## Required smoke test before merge

At minimum before merge:

```bash
git diff --check
php -l <modified-php-file>
```

Then manually verify public, buyer, seller, admin, cart, checkout, and download workflows on the appropriate test target.

## Phase 6 SEO testing

Run `git diff --check` and `php -l` for modified PHP files. Inspect rendered source for public pages to confirm titles, descriptions, canonicals, Open Graph, Twitter tags, robots meta, and JSON-LD. Verify `/browse` is indexable while filtered browse URLs render `noindex,follow` and canonicalize to `/browse`. Verify `/sitemap.xml` returns valid XML and excludes private routes, filtered browse URLs, and unapproved products/stores. Verify `public/robots.txt` disallows private route groups without blocking public marketplace pages.

## Phase 6 closeout validation

Phase 6 closeout validation included PHP syntax checks for modified controllers/views, `/sitemap.xml` HEAD and XML checks, static page source checks for About, Privacy, Terms, Contact, Buyer FAQ, Seller FAQ, and Licensing Help, duplicate H1 checks, filtered browse noindex checks, logout redirect checks, login create-account CTA checks, File Types UI removal checks, and seller product form browser testing.

## Phase 7 launch polish checks

Recommended Phase 7 verification includes `git diff --check`, PHP syntax checks for modified PHP files, source/route checks for `/`, `/browse`, `/sell`, `/about`, `/privacy`, `/terms`, `/contact`, `/buyer-faq`, `/seller-faq`, and `/licensing-help`, and confirmation that the header logo slot either loads `public/assets/img/asset-moth-logo.png` or falls back to visible `Asset Moth` text. Browser smoke tests should verify homepage, browse/category, sample product, sample storefront, seller dashboard, buyer dashboard, and admin review pages where environment data is available.

## Phase 8 manual testing checklist

- Keyword search on `/browse?q=...`.
- Multi-word search and partial keyword search.
- Category filter on `/browse` and category route browsing on `/category/{slug}`.
- Price min/max filters, including min greater than max.
- AI disclosure filter using existing values.
- POD permission filter.
- Creator/store filter.
- Featured, recently added, file type, and commercial-available filters where data exists.
- Sort by relevance, newest, oldest, price low/high, title A/Z, and featured first.
- Pagination with filters and sort preserved.
- Clear filters links.
- No-results state and browse-all guidance.
- Related products and more-from-creator sections on product detail pages.
- Homepage featured products, featured creators, and recently added products.
- SEO checks: filtered browse/category pages render `noindex,follow`; unfiltered category pages remain canonical/indexable; sitemap excludes filtered URLs.
- Mobile layout checks for filters, cards, and pagination.

## Phase 8.5 licensing checks
- Run PHP syntax checks for changed PHP files after editing licensing code.
- Run `git diff --check` to catch whitespace issues.
- Manual coverage should confirm Personal is always included/free; sellers can enable/disable Basic, Commercial, POD, Wholesale, Fabric with overseas printing, Fabric without overseas printing, VA, Reseller with credit required, Reseller with no credit required, and Extended Commercial add-ons; sellers can save `$0.00` and paid add-on prices; buyers can select multiple licenses; guest cart add/update/remove works before login; checkout requires login and returns users to the saved cart; cart totals include base price plus selected paid add-ons; order items snapshot selected licenses and prices; buyer/admin displays show selected license details; disabled license rejection and fallback behavior work; tooltips/modals are readable; Licensing Help shows current terms; and single-product listing cards/images do not stretch or distort.

## Phase 8.75 testing notes
- Upload JPG, PNG, and WEBP preview images from the seller product form and confirm the public `product_images.image_path` points to a watermarked `/uploads/product_previews/*-wm.*` file when GD succeeds.
- Confirm `product_images.original_image_path` points to `storage/app/private/product_previews/` and is used by seller/admin watermark regeneration, rather than applying a second watermark to an already-watermarked public image.
- Confirm protected product files remain in `storage/protected_uploads/products` and are not modified by preview watermarking.
- Confirm product pages render share controls, copy buttons work in browsers with Clipboard API support, and Open Graph/Twitter image metadata uses the public preview image.
- Confirm seller storefront social fields reject invalid or dangerous URLs, valid links display publicly, and public links include safe external-link attributes.

- Phase 8.75 live testing confirmed 15MB seller preview/avatar/banner uploads, active web PHP upload limits of `upload_max_filesize=100M`, `post_max_size=120M`, and `max_file_uploads=50`, and verified `public/.user.ini` is blocked from public access with HTTP 403.
- Live testing confirmed transparent PNG watermarks render without black rectangles, use bottom-left placement at 50% opacity, regenerate correctly from retained private originals, and legacy preview images were backfilled to watermarked public previews with `watermark_status = watermarked` and no errors.
- Live testing confirmed product share controls render as clickable icon buttons under the wishlist action, copy/share actions work, storefront social links normalize seller-entered domain-only URLs to HTTPS, and license trust notes display below the product description.

## Phase 9 manual test scenarios
Verify downloadable and Google Drive products can be added to the cart, mixed carts show fulfillment type, duplicate product/license entries are prevented, licenses can be changed, removed items disappear, unavailable products/licenses block checkout, Google Drive products require seller delivery instructions before save/submit, public Google Drive product pages show the manual-delivery notice, checkout shows seller delivery instructions beside the Google Drive email field, Google Drive checkout requires a valid email, buyer order detail shows license proof/download or manual delivery status, sellers only see their own order items and can mark manual delivery delivered, delivered manual-delivery items no longer show the mark-delivered button, admins can view orders/download logs/manual delivery details and override fulfillment status, unauthorized buyers/sellers are blocked, and direct unauthorized downloads are denied/logged.

## Phase 10 — Stripe Payment Integration manual test scenarios
- **Stripe config missing:** with no `STRIPE_SECRET_KEY`, checkout should fail gracefully and must not create paid access.
- **Checkout success redirect:** complete the browser return to `/checkout/success`; buyer should see processing/status messaging, and no download/manual-delivery access should unlock from the redirect alone.
- **Webhook success:** send a signed `checkout.session.completed` with `payment_status=paid`; order should become paid, downloadable access should unlock, and Google Drive/manual delivery should become seller-ready.
- **Checkout completed but unpaid:** send `checkout.session.completed` with `payment_status` not `paid`; order should remain pending and access should stay locked.
- **Duplicate webhook:** resend the same Stripe event id; processing should be skipped/idempotent through `stripe_events.stripe_event_id`.
- **Mismatch/manual review:** send amount, currency, or order metadata mismatch; order should become `manual_review`, access should stay locked, and buyer retry should be blocked.
- **Failed payment:** send failed/async failed/payment_intent failed event; order should become failed and allow retry.
- **Expired/canceled unpaid session:** send expired/canceled session behavior; order should be not completed/expired/canceled and allow retry when not manual review.
- **Refund webhooks:** send refunded/partially-refunded charge events; order status should update and download/delivery actions should be blocked according to current rules.
- **Buyer cancellation rule:** verify buyers cannot self-cancel a completed digital purchase; `/checkout/cancel` only means payment was not completed before access unlocked.
- **Seller direct URL protection:** direct `/seller/order-item/{id}` for unpaid items must not expose buyer email, Google Drive email, or delivery instructions.
- **Seller paid-only delivery action:** seller can mark delivered only when `payment_status=paid`; partially-refunded/manual-review/nonpaid items must not show delivery actions.
- **Admin visibility:** admin can view payment logs, webhook logs, Stripe references, failed/manual-review transactions, and manual review flags.
- **Future seller refund/cancellation workflow:** seller refund/cancellation requests are future work and must be admin-reviewed before any Stripe refund/cancellation action happens.

Phase 10 does not implement emails/notifications, buyer self-cancellation of completed digital purchases, or seller refund-request approval UI.

### Phase 10 Stripe seller onboarding test coverage
Check that approved sellers can open `/seller/onboarding`, start `/seller/stripe`, create/continue Stripe Express onboarding with test keys, and return to Asset Moth with status fields synced. Verify buyer Checkout can complete before seller onboarding; seller payout records should remain `pending_stripe_onboarding` until the seller is payout-ready, then become `pending_transfer`/`transferred` or `transfer_failed` without reversing buyer access. Confirm seller-facing pages state no startup fee, no monthly fee, no listing fee, 18% Asset Moth commission, separate Stripe/payment processing fees, Stripe Connect payout requirement, admin-exception refunds, no buyer self-cancellation of completed digital purchases, and no seller instant refunds.

#### Phase 10 correction tests
After an approved seller completes Stripe onboarding or an `account.updated` webhook marks the seller payout-ready, verify old `pending_stripe_onboarding` paid-order payouts become attempted transfers with idempotency key `asset_moth_payout_order_{orderId}_designer_{designerId}`. Confirm unpaid, manual-review, and refunded orders are skipped; successful transfers become `transferred`, failures become `transfer_failed`, and buyer paid access remains unchanged. Test webhook signatures with `STRIPE_WEBHOOK_SECRET` and, when configured for a separate Connect destination, `STRIPE_CONNECT_WEBHOOK_SECRET`.

#### Source transaction payout retry checks
Verify transfer requests include `source_transaction` from `orders.stripe_charge_id` and `transfer_group=order_{orderId}` when available. For paid orders with no charge id yet, confirm payouts remain `pending_transfer`; after `payment_intent.succeeded` or `charge.updated` stores the charge id, confirm eligible payout-ready seller transfers are attempted with the same deterministic idempotency key.

## Phase 10.1 product cleanup checks
Recommended manual checks:
- Seller archives their own product and confirms it disappears from public browsing.
- Seller restores an archived product and confirms it returns as a draft.
- Seller permanently deletes a draft/test product with no completed orders.
- Seller attempts to delete a product with completed orders and confirms it is archived instead.
- Admin uses bulk archive/delete cleanup while logged in as admin.
- Buyer purchase history, downloads, seller sales, and admin order detail still load for completed purchases.

- Direct POST submit attempts against archived or deleted products must be blocked until the product is restored to draft.
- Restore actions should only succeed for the seller's own archived or deleted products; other statuses should not claim success.
- Disabled products should not be submitted directly by POST.
- Seller product lists should hide `deleted` products from the seller-facing dashboard, including the All tab, while admin/payment records remain preserved.

## Admin seller management checks
- Admin can change seller creator rank from `/admin/designers`.
- Admin can disable and re-enable sellers from `/admin/designers`.
- Admin designer management defaults to approved sellers and can filter disabled/all sellers so preserved test sellers do not clutter the live tester view.

## Admin commission report checks
- Admin can open `/admin/payment-logs` and see gross sales, Asset Moth commission, seller payout totals, transfer status, payment transactions, and webhook logs.
- A $5.00 paid order at 18% commission should show $0.90 Asset Moth commission and $4.10 seller payout.
- Failed seller transfers should show the transfer error without changing the commission snapshot.
- Admin commission report should count live Stripe payments only by default, excluding old `cs_test_` test-mode orders from live money totals.
- Admin payment log tables should stay inside their content area without causing full-page sideways scrolling.

## Admin dashboard money stat checks
- Admin dashboard live money stats should count live Stripe paid orders only.
- Test-mode `cs_test_` orders, pending orders, canceled orders, and deleted test-seller cleanup records should not inflate live Gross Sales or Asset Moth Commission dashboard stats.
- Failed payouts from deleted test sellers can be marked `test_voided` so they do not appear as active seller payout failures.

## Phase 10.2 Coupon Testing Checklist
- Admin creates a platform coupon, edits its amount/date/limits, and deactivates it from `/admin/coupons`.
- Admin creates a seller-scoped coupon and verifies the seller must be an approved seller.
- Seller creates and edits only their own seller coupon from `/seller/coupons`.
- Seller POST attempts against another seller coupon ID return 404 before any update or restriction rewrite.
- Buyer applies a valid coupon and sees the discount in cart and checkout totals.
- Invalid, inactive, expired, not-yet-started, over-limit, per-user-limit, below-minimum, and non-applicable coupons show clear errors.
- Percentage coupon math discounts only the eligible subtotal.
- Fixed coupon math is capped to eligible subtotal and never creates a negative total.
- Seller-scoped coupons in mixed-seller carts discount only eligible items from that seller.
- A coupon that makes checkout total `$0.00` is rejected because this phase does not implement free-order checkout.
- Checkout without a coupon still creates a Stripe Checkout session.
- Stripe paid webhook records coupon usage once and repeated webhook/retry processing does not double-count usage.

## Phase 10.3B Stripe Tax compliance
Use this checklist for Phase 10.3B validation:
- Stripe Checkout Session has `automatic_tax` enabled.
- Billing address collection is required.
- No shipping address or shipping rates are collected.
- US checkout works.
- Non-US billing country gets manual review and no delivery/download unlock if detected on the Checkout Session.
- Checkout Session `automatic_tax.status` must be `complete`; `failed` or `requires_location_inputs` gets manual review and no delivery/download unlock.
- Stripe webhook stores `total_details.amount_tax` into `orders.tax_amount`.
- Tax is excluded from seller earnings, seller payouts, and platform commission.
- Coupons still reduce item totals before commission.
- `$0.00` coupon checkout remains blocked.
- Admin order detail and payment logs show tax separately; payment-log detail shows order-level tax once per order while the summary remains authoritative.
- Seller pages state tax is handled by Asset Moth/Stripe Tax and excluded from payout.
- Downloads/manual delivery unlock only after a valid webhook-confirmed paid order.


## Current seller upload limit check

- Current seller preview, avatar, and banner image validation allows JPG, PNG, and WEBP uploads up to 25MB. Verify app validation messages, seller form help text, and deployment limits (`upload_max_filesize`, `post_max_size`, and reverse proxy body size) before launch.
