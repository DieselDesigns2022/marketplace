# Testing

## Phase 12.4

Final external tester review completed with four submitted tester responses across computer, tablet, and phone use. No remaining Phase 12.4 blocker was reported after the live licensing usability correction.

Run `php tests/Phase124CsvProductImportTest.php` for all eight adapters, UTF-8/CSV structure, mapping, curated variants, privacy, price/type review, identity fingerprints, tags/categories, and image parsing. Run `php tests/Phase124RemoteImageImportTest.php` for deterministic HTTPS destination, redirect, timeout, size, byte validation, cleanup, manual private-original preservation, imported public-only max-1200px/WebP behavior, partial failure, order, and watermark behavior; it makes no public internet requests. Run `RUN_DISPOSABLE_DB_TESTS=1 php tests/Phase124DatabaseIntegrationTest.php` only against an explicitly disposable MariaDB server: the test creates a temporary fixture database, builds its prerequisite tables, applies both Phase 12.4 migrations, then checks exact selection, legacy zero-selection stuck-run reconciliation and initial progress, repeated confirmation, interrupted-claim resume, overlapping process workers, idempotent retries, active image leases, terminal-image parent recovery, in-order bounded image work and warnings, failures/partial success, ownership, counts, completion, fingerprints, and review requirements, and then drops the fixture database. Relevant regressions are `php tests/Phase122BulkProductBatchTest.php` and `php tests/Phase106DashboardUsabilityTest.php`. Phase 12.4 live-fix closeout also verifies seller bulk archive wiring, explicit batch license-source selection, backward-compatible generic batch copying, and target license-review clearing.

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

- Phase 8.75 live testing historically confirmed 15MB seller preview/avatar/banner uploads, active web PHP upload limits of `upload_max_filesize=100M`, `post_max_size=120M`, and `max_file_uploads=50`, and verified `public/.user.ini` is blocked from public access with HTTP 403. The historical 15MB avatar/banner result is superseded by the current 25MB seller avatar and store-banner application limit.
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
- A coupon-only `$0.00` checkout remains rejected; a positive merchandise/tax total may reach `$0.00` only through reserved store credit and the internal finalizer.
- Checkout without a coupon creates a Stripe Checkout Session only when store credit leaves a positive remainder; full credit coverage uses internal finalization.
- Stripe paid webhook records coupon usage once and repeated webhook/retry processing does not double-count usage.

## Stripe Tax compliance (Phase 10.3B foundation, Phase 11 current lifecycle)
The Phase 10.3B automatic-Tax checklist is superseded for current checkout. Validate that Phase 11 creates a standalone Tax Calculation from the normalized US billing address before credit reservation; stores the calculation ID/snapshot while leaving `tax_collected_at` null; rejects non-US or materially mismatched completed billing data into manual review; and creates one idempotent Tax Transaction during atomic finalization before marking tax complete/collected. Stripe Checkout automatic tax is disabled because its single remaining-total line item already includes the authoritative tax after credits. No shipping address/rates are collected. Tax remains excluded from seller earnings, payouts, and commission, while coupons reduce item totals before tax/commission snapshots.
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

#### Phase 10.4 live admin moderation separation
- The IP-risk review form contains only IP-specific actions: keep IP review pending, approve IP review, and leave published while flagged.
- Normal admin moderation remains separate and available regardless of IP-risk state or whether a current scan has active matches.
- Verify an unflagged product can be rejected, disabled, archived, restored where eligible, or marked deleted through normal moderation.
- Verify a flagged product can still be rejected or archived through normal moderation without using an IP-risk transition.
- Verify ordinary approval remains blocked when a pending product has active matches requiring IP review.
- Verify IP-specific actions still reject products that do not have a current scan with active matches.


## Phase 10.5 testing

### Executed database-independent suite
Run `php tests/Phase105EmailsNotificationsWaitlistTest.php`. Its 22 executed database-independent behavior groups directly exercise original-input URL/CTA control validation, header-injection rejection, signed-token verification and tamper failure, waitlist normalization, administrator status/invitation decisions, active-admin test authorization decisions, seller subject/action safety, retry/status calculations, escaped campaign rendering, neutral launch copy, visible marketing unsubscribe links, stored receipt-title precedence/escaping, monotonic refund decisions and keys, paid-communication eligibility, shared diagnostic redaction (including API-key variants, Stripe signatures, and URL userinfo), and log/webhook helper behavior.

### Database connectivity gate
On a disposable MariaDB database with the corrected migration applied, set the normal `DB_*` environment values and run `PHASE105_RUN_DATABASE_TESTS=1 php tests/Phase105DatabaseIntegrationTest.php`. This gate confirms connectivity and table availability only; it is not proof that stateful scenarios passed.

### Manual/staging scenarios not run by the lightweight suite
Verify active repeat signup, transactional signup/confirmation rollback, intentional resubscription, suppressed protection, notification ownership, queue/event deduplication, paid webhook replay with separate receipt/download notifications, seller alert deduplication, two concurrent workers, consent withdrawal after snapshot, explicit individual/filtered invitation modes, invalid status transitions, confirmation/invitation timestamps, campaign `completed`/sent/failure states, and recipient synchronization/recalculation failures after message state is safely stored. Confirm such reconciliation failures do not stop independent queue work.

Step 8 staging must also verify: a receipt insert followed by a failed download insert is healed on replay; an existing buyer notification with a missing seller notification is healed without duplicates; replay does not repeat coupon usage, earnings, payout ledgers, transfers, unlock, or transaction logging; manual-review orders receive no paid communication; complete communication sets remain unchanged; and identical/smaller/out-of-order refunds do not communicate while increased partial and partial-to-full transitions do. These database-backed cases are not passed by the lightweight suite or connectivity gate.

Resend request construction, acceptance validation, configuration failure, and secret-safe errors are covered by `php tests/ResendEmailTransportTest.php`. Sender verification, bounce handling, and live delivery still require production-provider testing.

The database-independent suite also covers backslash/browser-normalization URL attacks, same-host unapproved ports, URL userinfo, deterministic verified-payload fingerprints, normalized event types, allowlisted failure categories, controlled non-sensitive webhook-alert copy, and idempotent structured log append by message ID. Database persistence after physical delivery and verified webhook notification insertion remain part of the unexecuted staging matrix when no disposable MariaDB environment is configured.

Log recovery tests execute duplicate-ID suppression, distinct valid records, incomplete-tail truncation, malformed-complete-record failure, invalid-ID rejection, JSON validity, and sensitive-field absence. Webhook helper tests execute stable and distinct verified-payload fingerprints, event-type normalization, category allowlisting, and proof that supplied sensitive text is excluded from alert copy. Actual database notification insertion for verified webhook failures remains an unexecuted staging scenario without a disposable migrated MariaDB environment.



## Phase 10.6 test matrix

| Area | Automated | Disposable database / browser verification |
|---|---|---|
| PHP and regressions | PHP lint; Phase 10.6 suite; Phase 10.5 suite | Run relevant database integration suites |
| Buyer downloads | Query/static behavior checks for per-file rows, refund precedence, expiry, and protected links | Purchase a multi-file product; verify paid, unpaid, refunded, expired, manual, and missing-file states |
| Roles/navigation | Dynamic-route and role-aware navigation checks | Exercise buyer, incomplete/approved seller, and admin sessions, including 403/redirect boundaries |
| Receipt notes/images | Normalization, path, action, encoder/transparency, grouping, escaping, and retention checks | Upload valid/invalid/oversized/polyglot formats; replace/remove/restore; verify future snapshots and historical retention |
| Categories | Canonical list and punctuation normalization checks | Run migration twice; verify assignments, coupon restrictions, duplicates, filters, pages, and sitemap |
| Responsive/accessibility | Markup and JS state checks | Test keyboard controls, screen-reader names, responsive tables, and layouts at 320px and desktop widths |
| Payments/security | Phase 10.5 regression, cumulative partial/full refund allocation, tax exclusion, replay determinism, Stripe account-state mapping, and trailing-payload image checks | Verify Stripe Checkout/Tax/webhook/payout/coupon/IP-risk behavior in a test Stripe environment |

Commands: `find app public tests -name '*.php' -print0 | xargs -0 -n1 php -l`, `php tests/Phase106DashboardUsabilityTest.php`, `php tests/Phase105EmailsNotificationsWaitlistTest.php`, and `git diff --check`. Never run the migration first against production. The automated suite also asserts protected-file availability gating, the pre-decode source-pixel ceiling, non-blocking invalid receipt snapshot fallback, seller/admin Account links, manual-review payment warnings, payout actions, and validated `assetUrl` receipt rendering. Migration checks assert a single deterministic target per duplicate, canonical-slug priority over legacy name conflicts, and exclusion of self-maps. Buyer-order checks assert that `file_available` is supplied by the controller and required by the Download button. Phase 10.6 checks also execute partial/full cumulative refund allocation, repeated-input determinism, tax exclusion, exact PNG/WEBP/JPEG boundaries before GD decode, and Stripe information-required/payout-ready/payout-issue mapping. Database staging must still verify absolute pending-ledger reconciliation against real `payment_transactions`.

### Phase 10.6 live-fix regression
`php tests/Phase106DashboardUsabilityTest.php` asserts source-level metric stacking, category merge ordering, seller placeholder removal, the form-only waitlist shell, deletion guards and transaction hooks, post-commit cleanup ordering, admin navigation/dashboard continuity, banner-processing/path-policy implementation, and contained legacy `.jpeg` banner cleanup. It does not fully prove migrated-database deletion, real file cleanup, multipart upload handling, GD processing, physical 2400 × 800 output, or browser rendering. Environment-dependent validation still requires migrated MariaDB deletion integration, real filesystem cleanup, multipart upload validation, GD decode/re-encode and output inspection, and browser banner-display checks.

### Phase 10.6 final live-testing results

Completed live checks covered seller, buyer, and admin workflows on desktop, iPad, and phone; receipt-image uploads; protected downloads; product submission and IP-risk behavior; account and waitlist deletion flows; payment/webhook resolution queues; seller product action controls; and responsive navigation/table behavior. A real product ZIP upload also passed after the marketplace PHP-FPM limits were verified at 600 MB per file / 650 MB request and Nginx was aligned to `client_max_body_size 650M`.

### Phase 11 verification
Run `php tests/Phase11ReferralsCreditsStoreCreditTest.php` for strict money, checkout arithmetic, referral-code and billing-address behavior, plus deterministic Tax Calculation, Tax Transaction success/failure, and platform-balance transfer request/replay assertions. Run `PHASE11_ALLOW_FIXTURE=1 DB_HOST=127.0.0.1 DB_NAME=marketplace_test_control DB_USER=... DB_PASS=... php tests/Phase11DatabaseIntegrationTest.php` with disposable-database privileges. The fixture applies the migration three times, compares Phase 11 migrated metadata to canonical schema, preserves legacy data, runs separate-connection credit and payout races, exercises independent referral rewards and ineligible events, completes and recovers real shared finalization, injects Stripe/communication failures, and invokes actual admin controller actions for role/CSRF/adjustment/settlement coverage. CLI-only helpers under `tests/helpers/` isolate concurrent connections and controller requests. A missing MariaDB connection or fixture flag reports `SKIP`, which is not a pass. Run every `tests/*.php`, lint PHP files, and run `git diff --check`.

#### Phase 11 correction matrix
The disposable suite must pass before completion review; its cases existing in source is not a substitute for execution. Live Stripe Tax and platform-balance transfer verification remains a staging check because automated tests use the CLI-only deterministic transport. A MariaDB `SKIP` is an environment result, not a pass or release signal.
# Phase 11 seller-referral commission checks

Run `php tests/Phase11ReferralsCreditsStoreCreditTest.php` for deterministic cents,
Stripe transport, and referral checks. The disposable database suite is
`php tests/Phase11DatabaseIntegrationTest.php`; it requires the test MariaDB
environment and applies the real Phase 11 migrations before exercising services.

The correction suite must apply both Phase 11 migrations three times and compare canonical metadata. A MariaDB `SKIP` is a release blocker, not a pass. Required verification includes service/controller authorization and CSRF, exact cents, replay and concurrent payout claims, append-only refunds/recovery, permanent stop irreversibility, notification idempotency, and retry audit history.

### Phase 12 tests
`php tests/Phase12CreatorRecognitionTest.php` executes rank/progress boundaries, cumulative seller-specific refund allocation, exact Founder time boundaries, force modes, deterministic first-50 planning with tie-breaks/reservations, and rendered storefront active/inactive behavior.

`PHASE12_ALLOW_FIXTURE=1 php tests/Phase12DatabaseIntegrationTest.php` requires disposable-database privileges. It creates a pre-Phase-12 schema, applies the actual migration twice, verifies legacy conversion/preservation and structural rerun stability, exercises distinct and mixed-seller counting, excluded states, seller-specific partial refunds, same-count latest-sale replacement, rank overrides, Founder modes/reservation, no-op history, unique positions, and separate-connection mutex blocking. A `SKIP` means those database cases were **not executed** and is not a pass or release verification.

The disposable suite starts independent PHP worker processes with independent connections for two automatic Founder assignments and a competing admin grant, then verifies completion without deadlock, unique deterministic positions, the database uniqueness guard, and seller 51 rejection. It also checks refund replay/full/partial/tax math, UTC under a non-UTC PHP timezone, history separation, restore semantics, durable admin action cycles, communication types/titles, all six guarded foreign keys, and canonical-schema names. Exit 77 means SKIP, never PASS.

### Phase 12.1 tests

Run `php tests/Phase121DigitalProductLicenseTest.php` for deterministic normalized-key, price, snapshot, wording, and source-contract checks; its source inspection is not controller authorization execution. Run `PHASE121_ALLOW_FIXTURE=1 php tests/Phase121DatabaseIntegrationTest.php` with disposable-database privileges. The integration suite applies the real migration twice, configures licenses through `LicenseService::normalizePosted()` and `syncProductLicenses()`, drives `CartController` add/resolve/checkout in isolated CLI workers, executes cross-seller product access rejection, checks manipulated-price resistance, stale stored carts, multi-item snapshots, and renders buyer/seller/admin order views from saved order-item fields after current configuration changes. Exit 77 is a database SKIP, not a pass.

When the disposable MariaDB fixture is enabled, the Phase 12 suite executes all three CLI modes, the paid-order finalizer hook, the actual Stripe refund-processing method plus its recovery path, durable 24→25→24→25→24 automatic-event cycles, stale planned-position revalidation, and authoritative Founder admin refreshes.
The PR #59 correction additionally verifies semantic-only rank/Founder events, latest semantic-history stale-trigger suppression across automatic and administrative rank/Founder cycles, post-commit communication recovery, and no-op Founder grant/restore authoritative repairs. The final correction verifies recognition-only event JSON, authoritative Founder tenth-order/earned-date repair without false history or communication, separate durable rank events during Founder refresh, and a second no-op daily run after inactivity.

## Phase 12.2 — Bulk Product Upload & Batch Listing

Phase 12.2 final live testing covers the guided bulk-product workflow and directly affected existing seller product behavior.

Required live checks:
- Begin from Create Bulk Products and enter shared product information.
- Confirm the next step asks for the total number of products instead of immediately creating blank drafts.
- Confirm Product 1 opens with the shared information prefilled.
- Confirm every following product also opens with the shared information prefilled.
- Confirm individual product edits remain independent.
- Confirm seller license selections and applicable paid license prices carry into the workflow.
- Confirm preview images and protected downloadable files are supplied per individual product.
- Confirm the seller can move sequentially through the full requested product count.
- Confirm saved bulk drafts can be reopened and edited.
- Confirm bulk batches can be deleted from both the saved batch list and the individual batch page.
- Confirm completed products remain normal independent products.
- Regression-test normal seller product creation/editing, validation, IP-risk behavior, submission, moderation, and publishing where directly affected.
- Confirm cross-seller access is denied and POST mutations remain CSRF protected.
- Confirm affected desktop and mobile/tablet layouts remain usable.

`php tests/Phase122BulkProductBatchTest.php` remains a source-contract test and must not be represented as full behavioral live coverage.

`PHASE122_ALLOW_FIXTURE=1 php tests/Phase122DatabaseIntegrationTest.php` remains the disposable-database integration suite where supported. Exit 77 means SKIP/unexecuted, not PASS. Final approval additionally requires successful live testing of the current guided workflow.
# Phase 12.3

Run `php tests/Phase123EmailPreferencesDigestsTest.php` for preference mapping, deterministic periods, scoped signed tokens, migration policy, template safety, follows eligibility/privacy, and transactional separation. With a disposable migrated MariaDB server, run `PHASE123_RUN_DATABASE_TESTS=1 PHASE123_ALLOW_FIXTURE=1 php tests/Phase123DatabaseIntegrationTest.php` for the service/database scenarios below.

The destructive fixture is doubly opt-in: set both `PHASE123_RUN_DATABASE_TESTS=1` and `PHASE123_ALLOW_FIXTURE=1`. It creates and drops a randomly named isolated database and exercises the real Phase 12.3 migration, preference service, scoped unsubscribe controller, digest producers, queue deduplication, current follow/user-status suppression, safe log worker, and transactional separation. Without both flags it exits safely without changing a database.

The fixture also exercises cross-preference product assignment with weekly plus favorite-shop, overlapping weekly plus monthly, all three preferences, repeated producers, a distinct second product, and a monthly-only recipient. Assertions inspect actual sent queue payloads after the safe log worker runs, rather than treating source text as delivery behavior.

The enabled command `PHASE123_RUN_DATABASE_TESTS=1 PHASE123_ALLOW_FIXTURE=1 php tests/Phase123DatabaseIntegrationTest.php` exists but has not been successfully executed in the current Codex environment because disposable MariaDB was unavailable. A safe `SKIP` is not a passing integration result; the enabled disposable MariaDB suite must pass before release approval. Phase 12.3 also requires live testing before it is considered fully released, covering `/account` save/reload and independent preference combinations, scoped unsubscribe links, weekly and monthly producers, favorite-shop producer behavior, queue-worker processing and retries, follow/unfollow and current-user-status suppression, cross-preference product deduplication, aggregate admin counts, safe log delivery, and transactional-email regression.

## Phase 12.5
Run `php tests/Phase125InternalMessagingTest.php` and, with a configured disposable MySQL database, `php tests/Phase125DatabaseIntegrationTest.php`. Also run PHP lint on changed PHP, the Phase 10.5/10.6 regressions, and `git diff --check`.

`Phase125InternalMessagingTest.php` performs static security and source-contract checks plus pure exact-image-container parser tests. `Phase125DatabaseIntegrationTest.php` performs opt-in disposable database/service behavior and attachment-authorization checks using fixture metadata. When enabled with `RUN_DISPOSABLE_DB_TESTS=1`, it creates and drops a uniquely named disposable database and checks thread flow, durable context snapshots, inbox visibility/read state, notification synchronization/audience/deduplication, email deduplication, archive/block/report persistence, report/conversation identity separation, self/foreign/ineligible start predicates, blocked start/reply predicates, conversation IDOR, participant attachment IDOR, and report-scoped admin attachment authorization. Without the explicit gate or an available server it prints `SKIP/UNEXECUTED`; that result is not a pass and means the database behavior was not executed.

Neither automated suite performs a genuine end-to-end PHP multipart upload through PHP multipart handling and `is_uploaded_file`, real upload-size enforcement, Fileinfo MIME inspection, GD decoding, or movement into protected attachment storage. Genuine multipart attachment validation remains an environment-dependent live test requirement and must cover those checks without weakening the production upload boundary.
