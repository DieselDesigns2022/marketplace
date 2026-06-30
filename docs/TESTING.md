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
- Confirm order checkout creates order, order item, seller earning, and platform commission rows.
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
- Checkout successfully.
- Confirm cart clears after checkout.
- Confirm order appears in purchases.
- Confirm authorized download works.
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
