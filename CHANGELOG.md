# Changelog

## Current status

The project currently includes the original MVP plus Phases 1 through 4.5. Phase 5 is documentation only. The marketplace has public browsing, buyer accounts, seller applications, seller storefronts, product management, cart/checkout/order/download workflows, admin moderation, and standardized readable source formatting.

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
