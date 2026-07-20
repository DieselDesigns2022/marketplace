# SEO

## Blueprint goals

The long-term SEO blueprint is to make public marketplace, category, product, and store pages discoverable with high-quality metadata, clean URLs, canonicals, structured data, sitemap support, robots rules, and noindex controls for private/duplicate/low-value pages.

## Current SEO status

Implemented foundations:

- Clean public URLs for products, stores, categories, and static pages.
- Product and designer/store schema fields include SEO title and SEO description columns.
- Dynamic metadata across public page types.
- Absolute canonical URL handling with an `APP_URL` setting and `https://marketplace.dieseldesigns.co` fallback for the current build/test deployment.
- Dynamic `/sitemap.xml` generation for public indexable URLs only.
- `public/robots.txt` rules with a production sitemap reference.
- Conservative structured data for public pages.
- Noindex controls for filtered browse URLs and private/utility workflows.

## Dynamic metadata

Product and store tables include SEO fields. Public page rendering should prefer custom SEO title/description when present, then fall back to product/store/category names and summaries.

## Canonicals

Canonical URLs are implemented for public product, store, category, browse, sell, homepage, and static pages to reduce duplicate indexing risk.

## Product SEO

Product pages should use:

- Product title.
- Short description or SEO description.
- Preview image where available.
- Canonical product URL.
- Conservative Product structured data where current product data supports it.

## Store SEO

Store pages should use:

- Store display name.
- Store SEO title/description where available.
- Avatar/banner images where appropriate.
- Canonical store URL.

## Category SEO

Category pages should use:

- Category name.
- Category description.
- Canonical category URL.
- Indexable approved products only.

## Sitemap

Sitemap generation is implemented dynamically at `/sitemap.xml`. It includes public indexable URLs only: homepage, browse/category pages, approved products, approved designers/stores, sell page, and selected static pages. It excludes filtered browse URLs and private workflow routes.

## `robots.txt`

`public/robots.txt` is implemented. It allows public marketplace pages, disallows private/dashboard/admin/cart/checkout/download/auth/apply routes where appropriate, and points crawlers to `https://marketplace.dieseldesigns.co/sitemap.xml`.

## Noindex rules

Private and utility pages should be noindexed:

- Login/register/account.
- Buyer dashboard pages.
- Seller dashboard pages.
- Admin pages.
- Cart/checkout.
- Download routes.

## Phase 5 and Phase 6 SEO status

Phase 5 created documentation only and did not implement SEO code. Phase 6 implemented the SEO foundation documented below, including metadata, canonicals, sitemap generation, robots rules, structured data, and noindex behavior. Future SEO updates should keep this document synchronized with actual code changes and should not claim deployment, Search Console submission, indexing approval, or legal review unless those steps are completed.

## Phase 6 implemented SEO behavior

The shared layout now renders dynamic titles, descriptions, absolute canonicals, robots meta, Open Graph tags, Twitter Card tags, and JSON-LD when controllers provide metadata. Existing `title`, `description`, `canonical`, and `og_image` metadata remains supported; newer fields include `robots`, Twitter-specific fields, `schema`, and `json_ld`.

Canonical URLs use `APP_URL` when configured and fall back to `https://marketplace.dieseldesigns.co` for the current build/test deployment. Public indexable routes include `/`, `/browse`, `/category/{slug}`, `/product/{slug}`, `/store/{slug}`, `/sell`, `/about`, `/contact`, `/terms`, `/privacy`, `/licensing-help`, `/buyer-faq`, and `/seller-faq`. Filtered browse URLs such as `?q=`, `?file_type=`, `?sort=`, `?ai=`, and `?pod=` render `noindex,follow` and canonicalize to `/browse`.

Private and utility routes render or inherit `noindex,follow` in the layout, including auth, account, dashboard, seller, admin, cart, checkout, apply, and protected download paths. `public/robots.txt` allows public marketplace pages, disallows private/utility route groups, and points crawlers to `https://marketplace.dieseldesigns.co/sitemap.xml`.

`/sitemap.xml` is generated dynamically and includes only public indexable URLs: static public pages, active categories, approved products, and approved designer stores. It excludes filtered browse URLs and private workflow routes.

Structured data is intentionally conservative: WebSite, CollectionPage, Product with price Offer when price exists, ProfilePage for stores, WebPage/AboutPage/ContactPage/PrivacyPolicy, and FAQPage metadata only for FAQ-style pages. No fake ratings, reviews, address, phone, founder, shipping, or SKU data is generated.


## Future domain migration

Asset Moth is the public brand name. `https://assetmoth.com` is the planned future domain after purchase, DNS setup, deployment migration, and verification. Until then, current runtime canonical, sitemap, robots, deployment, and smoke-test examples should use `https://marketplace.dieseldesigns.co` unless `APP_URL` is explicitly configured otherwise.

## Phase 6 VPS closeout notes

After the initial Phase 6 implementation, additional VPS validation and refinement were completed. The live deployment now confirms valid sitemap XML, working sitemap HEAD/GET behavior, public static page metadata/schema, filtered browse `noindex,follow`, static page content upgrades, and duplicate H1 cleanup for upgraded static pages. Current runtime examples remain based on `https://marketplace.dieseldesigns.co`; `https://assetmoth.com` remains the future production domain target.

## Phase 7 content polish note

Phase 7 kept the Phase 6 indexing, canonical, robots, sitemap, and structured-data behavior intact. Public-facing copy was improved on the homepage, browse/category pages, product pages, storefronts, and sell landing page to better describe Asset Moth as a digital design marketplace. No private dashboard/admin pages were made indexable. The expected local logo path for launch polish is `public/assets/img/asset-moth-logo.png`; upload the final logo asset there before production launch if it is not already present.

## Phase 8 browse/search SEO behavior

Phase 8 preserves Phase 6 SEO rules. Unfiltered `/browse` and clean `/category/{slug}` pages use canonical base URLs and remain public indexable collection pages. Search, filtered, sorted, or paginated browse/category URLs render `noindex,follow` and canonicalize to the base browse/category route to avoid indexing duplicate or thin result combinations. The sitemap continues to include only base public routes, active categories, approved products, approved stores, and selected static pages; it does not include filtered search URLs.

## Phase 10.5 route behavior

- `/waitlist` is a public footer-linked route using the shared default metadata and canonical behavior. It is not currently included in `/sitemap.xml`.
- `/email/unsubscribe` is a public utility route and does not currently receive an explicit `noindex` prefix from the shared layout.
- `/notifications` requires authentication and does not currently receive an explicit `noindex` prefix from the shared layout.
- `/admin/waitlist` and `/admin/email-campaigns` inherit the existing `/admin` `noindex` behavior.

Whether `/email/unsubscribe` and `/notifications` should receive explicit `noindex` behavior remains an unresolved implementation-policy decision. This documentation records current behavior; it does not claim those routes are already noindexed.
