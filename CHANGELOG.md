# Changelog

## Current status

The project currently includes the original MVP plus implemented phases through Phase 9. Phase 9 provides cart, pending-payment order, download logging, and Google Drive/manual delivery foundations; real Stripe/payment processing remains future Phase 10 work.

## Original MVP — completed 2026-06-22

Major completed items:

- Initialized the repository and baseline custom PHP marketplace skeleton.
- Added MVC-style folders for controllers, core classes, and views.
- Added `public/index.php` as the front controller and route registry.
- Added baseline database schema with users, designers, products, categories, orders, and marketplace support tables.
- Added public homepage/browse/product/store foundations.
- Added authentication foundation for registration, login, logout, and account pages.
- Added initial buyer, seller, and admin dashboard structure.

Bug fixes:

- Established a stable baseline for subsequent phase work.

## Phase 1 — Designer Applications

Major completed items:

- Added designer application workflow for users who want to sell.
- Added application form fields for display name, desired slug, bio, portfolio, social links, design types, AI usage, and agreement.
- Added admin review of pending designer applications.
- Added application approval/denial workflow.
- Added designer record creation/updates when an application is approved.
- Added denial reason/admin notes support.

Bug fixes:

- Improved seller access flow so unapproved users are redirected toward application where needed.

## Phase 2 — Storefront Management

Major completed items:

- Added approved designer storefront settings.
- Added public store pages by store slug.
- Added seller fields for display name, bio, avatar, banner, website/social information, announcement, and SEO metadata.
- Added admin designer management.
- Added buyer follow support for stores.
- Added storefront status/featured support.

Bug fixes:

- Added slug checks to reduce storefront/application conflicts.
- Improved seller role recognition after approval.

## Phase 3 — Product Management

Major completed items:

- Added seller product list and product editor.
- Added product title, slug, descriptions, price, category, tags, file types, license options, POD permission, AI disclosure, and SEO metadata.
- Added preview image upload management.
- Added protected downloadable product file management.
- Added draft, pending review, approved, rejected, and disabled product statuses.
- Added admin product moderation and product detail review.
- Added category and tag support.

Bug fixes:

- Fixed product checkbox saving.
- Cleaned up product management behavior after initial implementation.

## Phase 4 — Shopping Cart, Orders & Downloads / Marketplace Polish

Major completed items:

- Added cart pages and add/remove/update actions.
- Added personal/commercial license selection.
- Added mock checkout flow.
- Added order and order item creation.
- Added buyer purchases and order detail pages.
- Added protected download route for purchased files.
- Added downloads audit table usage.
- Added seller earnings and platform commission records.
- Added admin order listing and order detail pages.
- Added marketplace polish and bug fixes across buyer/seller/admin workflows.

Bug fixes:

- Completed Phase 4 marketplace polish and bug fixes.

## Phase 4.5 — Codebase Standardization

Major completed items:

- Reformatted compressed PHP, CSS, and JavaScript source for readability.
- Standardized source layout without intentional application behavior changes.
- Restored public product previews and sell page behavior after formatting regressions.

Bug fixes:

- Fixed regressions from formatting-only changes.
- Restored public product preview output.
- Restored sell page display.

## Phase 5 — Project Documentation

Major completed items:

- Added project-level documentation for architecture, database, deployment, testing, phase history, routes, security, SEO, Codex workflow, troubleshooting, contribution standards, and development workflow.

Bug fixes:

- Documentation-only phase; no application bug fixes were made.

## Phase 6 — SEO Foundation, Indexing & Public Launch Content

Major completed items:

- Rebranded public-facing marketplace copy to Asset Moth.
- Added shared metadata support for title, description, canonical URLs, robots meta, Open Graph, Twitter Cards, and JSON-LD.
- Added absolute canonical handling with APP_URL support and current deployment fallback to https://marketplace.dieseldesigns.co, with assetmoth.com noted as a future domain target.
- Added public launch copy for homepage, sell, static legal/help/FAQ pages, apply page, public empty states, and internal links.
- Added dynamic sitemap.xml and production robots.txt rules for public and private routes.
- Added browse noindex rules for filtered/search/sort URLs and indexable category URLs.
- Added product, store, category, browse, homepage, and static-page structured data without fake ratings/reviews/company details.

Bug fixes:

- Removed public placeholder branding and skeleton launch copy from shared layout and static pages.

### Phase 6 VPS closeout updates

- Verified Phase 6 on the VPS deployment at `https://marketplace.dieseldesigns.co`.
- Fixed HEAD requests for GET routes so `/sitemap.xml` returns HTTP 200 for HEAD checks as well as GET requests.
- Fixed logout fallback behavior so `/logout` safely redirects instead of showing a 419/404-style workflow issue.
- Added a create-account call to action on the login page for new seller applicants.
- Removed visible File Types UI/copy from active marketplace forms and public pages after product categorization rules changed.
- Improved seller product form checkbox alignment and legacy commercial license field layout.
- Upgraded public static launch pages for About, Privacy, Terms, and Contact.
- Removed duplicate static page H1 output on upgraded static pages.
- Archived temporary phase backup files, created a fresh completed Phase 6 backup, committed final changes, and pushed the phase branch.

## Phase 7 — Marketplace Content & Launch Polish

Major completed items:

- Polished shared header/footer content, including a local logo image slot at `public/assets/img/asset-moth-logo.png` with a safe text fallback when the file is missing.
- Updated homepage copy, CTAs, category messaging, featured product/designer sections, and launch-ready empty states.
- Improved browse/category page headings, filter labels, category explanations, and no-results messaging without adding advanced search or reintroducing File Types UI.
- Polished product page CTAs, trust notes, and empty states for more-from-designer and related products while keeping licensing limited to existing fields.
- Refined sell landing page wording around reviewed storefronts, protected files, product review, SEO fields, categories, tags, POD permission, commercial license availability, and AI disclosure.
- Improved buyer, seller, and admin dashboard copy, including clearer seller product status guidance and admin review context.
- Added targeted CSS for logo sizing, footer layout, CTA rows, tabs, empty states, and mobile spacing.

Intentionally postponed:

- Full licensing system remains postponed to Phase 8.5.
- Advanced search/filtering, real payments, production email/notification flows, referrals/credits polish, creator ranks, sponsored listings, and bundle events remain future-phase work.

## Phase 8 - Search & Browsing

- Added weighted public marketplace search across product titles, descriptions, tags, categories, file types, and creator/store names.
- Added browse/category filters for keyword, category, price range, AI disclosure, POD permission, creator/store, featured, recently added, file type, and existing commercial-license availability.
- Added stable sorting, SQL-backed pagination, result counts, active filter summaries, and no-results guidance.
- Added real-data discovery sections for featured products/creators, recently added products, related products, and more from the same creator.
- Added Phase 8 browse/search database indexes.
- Postponed full licensing, fake popularity, fake best-seller/rating sorts, cart/order/payment/download changes, sponsored listings, and review/rating work to later phases.

## Phase 8.5 - Licensing System
- Added platform license types and per-product license configuration for Personal, Commercial, POD, Wholesale, Fabric, VA, and Extended Commercial permissions.
- Corrected Phase 8.5 licensing so Personal is always included/free while seller-enabled add-on permissions may be free (`$0.00`) or paid, with buyer/cart/order/admin snapshots reflecting selected licenses and prices.
- Updated product, cart, checkout, buyer order, and admin review screens for multi-license selection with visible add-on license pricing where paid permissions are selected.
- Cart items store selected licenses as normalized key lists, checkout validates every selected license server-side, recalculates totals from product base price plus selected paid add-on license prices, and order item snapshots preserve selected licenses.
- Retained `license_price` for compatibility/snapshots; Personal/included licenses remain `0.00`, while selected paid add-on licenses snapshot their configured add-on prices. Kept `database/migrations/2026_07_01_phase_8_5_license_included_multi_select_fix.sql` as a compatibility no-op after the add-on pricing correction superseded the earlier included-license-only pricing fix.
- Fixed product listing card/image CSS so single-product sections no longer stretch or distort previews.

## Phase 8.75 — Marketplace Protection, Sharing & Store Polish
- Added server-side preview image watermarking for seller-uploaded public product previews. Watermarking only applies to public preview images; protected purchased/download files are not watermarked or altered.
- Added private original preview retention for newly uploaded product preview images, plus seller/admin watermark regeneration from the private original.
- Added product page share actions for Facebook, X/Twitter, clean copy link, and Instagram-friendly copy text, using existing Open Graph/Twitter metadata patterns and watermarked public preview images when present.
- Added optional storefront social link fields for Facebook, Instagram, TikTok, Pinterest, Etsy, Shopify, and website with http/https validation and safe public external link attributes.

- Live testing polish: raised seller preview/avatar/banner image upload validation from 2MB to 15MB, added app-specific PHP upload settings through `public/.user.ini`, fixed transparent PNG watermark alpha handling, adjusted preview watermark placement to bottom-left at 50% opacity with a larger size, backfilled existing legacy preview images into watermarked previews with private originals retained, converted share/social links to clickable icon buttons, moved product sharing under the wishlist action, and moved license trust notes below the product description.

## Phase 9 - Cart, Orders, Downloads & Delivery Foundation
- Added persistent cart price snapshots, product fulfillment types, pending-payment checkout foundation, order item purchase snapshots, secure download logging foundations, and Google Drive/manual delivery statuses.
- Added seller and admin manual delivery visibility/override workflows.
- Stripe/payment collection remains Phase 10; Phase 9 orders are clearly marked as pending-payment foundation records.
