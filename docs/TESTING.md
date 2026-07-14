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

## Seller tester feedback polish checklist

- Seller FAQ Stripe/CSRF clarity: open `/seller-faq` and confirm Stripe Connect onboarding, buyer checkout through Stripe, payout timing caveats, accurate Stripe account information, and plain-language CSRF/session recovery wording are visible. Confirm the old Product Status Guide help section is not shown.
- Character counters: on `/seller/product/new`, `/seller/product/{id}`, and `/seller/store`, type into seller-facing limited fields such as product title, SEO title, SEO description, store bio, store SEO title, and store SEO description. Confirm counters update live and clearly indicate over-limit text if the browser allows it.
- Wishlist card images/seller links: open `/dashboard/wishlist` with saved products that have preview images and approved seller records. Confirm cards show preview images, real seller display names, valid `/store/{slug}` links when a store slug exists, and no broken seller link when seller/store data is missing.
- Duplicate listing behavior: from `/seller/products`, duplicate one of the signed-in seller's own products. Confirm the new listing is a draft named like `Original Title Copy`, has a unique auto-generated slug, copies editable listing details/tags/license settings/SEO fields, does not copy order/download/sales/history data, and does not copy preview images or downloadable files.

### Phase 10.4 manual and repeatable tests

Scanner checks: run this command; it exits nonzero on assertion failure and covers canonical case-insensitive matching, alias matching, curly apostrophe and HTML entity normalization, hyphen and underscore filename normalization, exact-token safety for short terms, substring false-positive prevention, disabled canonical/alias skipping, repeated-occurrence deduplication, same term in different source fields, no phrase matching across separate tags/file names, and phrase matching inside one tag/file name.
Repeatable scanner command from repo root:

```bash
php <<'PHP'
<?php
require 'app/Services/IpRiskScanner.php';
use App\Services\IpRiskScanner;
$s = new IpRiskScanner();
$terms = [
 ['id'=>1,'term'=>'Star Wars','normalized_term'=>IpRiskScanner::normalize('Star Wars'),'category'=>'franchise','is_enabled'=>1,'aliases'=>[['alias'=>'SW','normalized_alias'=>IpRiskScanner::normalize('SW'),'is_enabled'=>1],['alias'=>'disabled alias','normalized_alias'=>IpRiskScanner::normalize('disabled alias'),'is_enabled'=>0]]],
 ['id'=>2,'term'=>"Artist’s Life",'normalized_term'=>IpRiskScanner::normalize("Artist’s Life"),'category'=>'slogan','is_enabled'=>1,'aliases'=>[]],
 ['id'=>3,'term'=>'IT','normalized_term'=>IpRiskScanner::normalize('IT'),'category'=>'company','is_enabled'=>1,'aliases'=>[]],
 ['id'=>4,'term'=>'Cat','normalized_term'=>IpRiskScanner::normalize('Cat'),'category'=>'character','is_enabled'=>1,'aliases'=>[]],
 ['id'=>5,'term'=>'Disabled','normalized_term'=>IpRiskScanner::normalize('Disabled'),'category'=>'brand','is_enabled'=>0,'aliases'=>[]],
 ['id'=>6,'term'=>'Tom & Jerry','normalized_term'=>IpRiskScanner::normalize('Tom &amp; Jerry'),'category'=>'character','is_enabled'=>1,'aliases'=>[]],
];
function assert_true($cond, $msg) { if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } }
$m = $s->scan(['title'=>'star wars STAR WARS', 'description'=>"Artist's Life and Tom &amp; Jerry, not educational or party", 'tags'=>['SW','Star Wars'], 'seo_title'=>'IT support', 'seo_description'=>'', 'file_names'=>['star_wars-file.png','star-wars-file.png','disabled alias']], $terms);
assert_true(count(array_filter($m, fn($x)=>$x['risk_term_id']===1 && $x['source_field']==='title'))===1, 'canonical case-insensitive and repeated dedup');
assert_true(count(array_filter($m, fn($x)=>$x['matched_alias']==='SW'))===1, 'alias match');
assert_true(count(array_filter($m, fn($x)=>$x['risk_term_id']===2))===1, 'curly apostrophe normalization');
assert_true(count(array_filter($m, fn($x)=>$x['risk_term_id']===6))===1, 'HTML entity normalization');
assert_true(count(array_filter($m, fn($x)=>$x['source_field']==='file_name' && $x['risk_term_id']===1))===1, 'hyphen/underscore filename normalization and dedup');
assert_true(count(array_filter($m, fn($x)=>$x['risk_term_id']===3))===1, 'short term exact token match');
assert_true(count(array_filter($m, fn($x)=>$x['risk_term_id']===4))===0, 'no substring false positive');
assert_true(count(array_filter($m, fn($x)=>$x['risk_term_id']===5))===0, 'disabled canonical ignored');
assert_true(count(array_filter($m, fn($x)=>$x['matched_alias']==='disabled alias'))===0, 'disabled alias ignored');
assert_true(count(array_filter($m, fn($x)=>$x['risk_term_id']===1 && $x['source_field']==='tags'))===2, 'same term alias/canonical retained in tags');
assert_true(count(array_filter($m, fn($x)=>$x['risk_term_id']===1))>=4, 'same term retained in different source fields');
assert_true(count($s->scan(['tags'=>['Star','Wars']], [$terms[0]]))===0, 'phrase not matched across separate tags');
assert_true(count($s->scan(['file_names'=>['Star','Wars.png']], [$terms[0]]))===0, 'phrase not matched across separate file names');
assert_true(count($s->scan(['tags'=>['Star Wars']], [$terms[0]]))===1, 'phrase matched inside one tag');
assert_true(count($s->scan(['file_names'=>['Star_Wars.png']], [$terms[0]]))===1, 'phrase matched inside one file name');
echo "scanner assertions ok\n";
PHP
```


Seller checks: save unflagged drafts and review submissions; save flagged drafts without confirmation; confirm flagged review submission without checkbox is blocked; confirm flagged review submission with checkbox records the exact text for the latest scan; edit products to add/remove matches and verify stale confirmations are not reused; verify published products are not automatically unpublished solely due to matches.

Seller UI copy checks: verify the exact warning text is “This product may contain trademarked, copyrighted, or protected terms. Please confirm you own the rights, have permission, or that your use is legally allowed before publishing.” Verify the exact checkbox text is “I confirm I have the legal right to sell this design and any included wording, artwork, or references.” Verify the disclaimer communicates that automated matching may be incorrect, cannot identify every legal issue, Asset Moth does not provide legal advice, and the seller remains responsible for confirming rights. Confirm the checkbox is unchecked on every render, including first warning display, validation-error redisplay, reloading an already flagged product, and returning after a missing-confirmation submission. Do not treat the browser checkbox value alone as proof; the saved confirmation must bind to the final authoritative scan.

Admin checks: verify list badge/count/status, detail active/inactive detections, first/last detection dates, confirmations, history, pending/approve/published-flagged/reject/archive actions, required rejection reason, invalid transition errors, and no history on failed transitions. Test state changes between page load and submitted IP action: the repository must re-read and lock product/state inside the transaction; stale scan IDs, stale product status, stale IP status, and submitted counts must not be trusted. Confirm `published_flagged` fails if the product became archived before the action commits, reject with an empty reason leaves no partial changes, simulated failure after a product update rolls back product, IP state, history, and admin log, and repository search shows only one authoritative IP review state-changing method. Verify ordinary single approval for a flagged pending product is blocked, crafted POST approval is blocked server-side, bulk approval skips flagged pending products with a dedicated IP-review-required count, and simulated database failures during reject/archive/published-flagged leave no partial product/IP state. Term tests must cover create/edit/enable/disable, canonical-vs-canonical duplicates, canonical-vs-alias duplicates, alias-vs-canonical duplicates, alias-vs-other-alias duplicates, invalid category, unsafe short terms, non-admin denial, and preserved history after disable. Regression tests must cover seller and admin permanent product deletion cleanup, pricing, licenses, uploads, AI disclosure, and existing product moderation.


#### Phase 10.4 focused final workflow checks
- Seller published-edit protection: edit an existing approved product to add an IP-risk term without checking the confirmation box; confirm the product remains approved/published, existing live field values remain unchanged, no confirmation row is recorded, and uploaded temporary files are not attached/orphaned. Repeat with an already flagged published product missing current confirmation, then resubmit with confirmation and verify the edit commits.
- Admin transition matrix: test pending/approve/reject for draft, pending_review, approved, and published products; test published_flagged only for pending_review, approved, and published; test archive for draft, pending_review, approved, published, rejected, and disabled; verify rejected/disabled/archived/deleted products are rejected for pending/approve/reject and deleted products cannot be revived through pending/reject/archive. Failed transitions must create no history, no admin log, and no product/IP state changes.
- Admin list counts: verify latest-scan active detection-row count matches the product detail active detection count, old-scan active rows are not counted, and ordinary approval fails closed when a pending product has latest-scan active detections with missing, clear, rejected, archived, or otherwise contradictory IP state.

#### Phase 10.4 final scan-confirmation save-integrity checks
- Approved product edit with checked confirmation: add a protected term to an approved product, check the IP rights confirmation box, save, and verify the save succeeds, a new `product_ip_risk_scans.id` is current in `product_ip_risk_states.latest_scan_id`, and `product_ip_rights_confirmations.scan_id` equals that exact newest scan.
- Legacy published product edit with checked confirmation: repeat the same flow for a `published` status product and verify the confirmation is stored against the final authoritative scan created by that save.
- Missing confirmation on approved/published edit: verify the old live title/descriptions/tags/licenses remain unchanged, status remains approved/published or valid pre-edit status, no confirmation row is inserted, and any newly uploaded files from the failed attempt are removed.
- Final authoritative scan differs from pre-scan: simulate a match introduced only after saved downloadable file metadata is present; verify the final scan fails closed without confirmation and displays the final matches on resubmission.
- Failure rollback: simulate scan persistence failure and confirmation insert failure during an approved/published edit; verify product fields, tags, licenses, IP scan rows, detections, state, and confirmations roll back together and no success flash appears.
- Upload cleanup: verify newly uploaded preview/product files are removed after rollback, existing product files and preview images remain untouched, and no orphaned product file rows remain.
- Filename coverage: verify downloadable product-file original names appear in both the pre-scan and final authoritative scan. Preview-image original filenames are not part of Phase 10.4 authoritative scanning because the product workflow does not retain them as seller-facing filename metadata for every rescan.
- Transaction nesting: verify publication-sensitive saves do not start a nested IP scan transaction; `IpRiskRepository::saveScan()` participates in the caller-owned transaction when one is already active.
- `submitProduct`: submit a flagged existing product through the submit route with and without the checkbox; verify missing confirmation blocks submission and checked confirmation is stored against the authoritative newest scan created by that submit action.

#### Phase 10.4 new-product failure cleanup checks
- Simulate an exception during new-product IP scan persistence and verify the newly inserted product, Phase 10.4 rows, tags, licenses, preview-image rows/files, and downloadable-file rows/files are removed without affecting any existing product.
- Simulate an exception during final-scan confirmation insertion for a new flagged review submission and verify the same compensating cleanup occurs; do not describe this as a database rollback because the new-product path uses explicit cleanup after product creation.
- Submit a new flagged product for review without the confirmation checkbox and verify the product is kept as a valid draft with its uploads, tags, licenses, authoritative scan, and detected matches; no confirmation row is created and no success message appears.
- Submit a new flagged product for review with the checkbox and verify the product remains `pending_review` only after a confirmation row exists for the exact newest `product_ip_risk_scans.id`.
- Confirm cleanup queries and filesystem deletion target only the newly created product ID and never remove rows/files from an existing product.

#### Phase 10.4 permanent-delete physical file cleanup checks
- Seller permanent delete: create a product with no completed orders, preview images, retained private preview originals, protected downloadable files, tags, licenses, and Phase 10.4 scan records. Permanently delete it and verify public preview files, private retained originals, protected downloadable files, upload database rows, Phase 10.4 rows, tags, licenses, and the product row are removed without touching any unrelated product files.
- Admin permanent delete: repeat the same physical/database cleanup checks through admin bulk delete and verify bulk delete uses the same safe cleanup path. Products with completed paid or partially refunded orders must remain protected and be archived instead of permanently deleted.
- Missing-file behavior: remove one physical preview/download file before deletion and verify the matching database row and product still delete safely. Insert or simulate a path outside the approved preview/download directories and verify it is never unlinked.
