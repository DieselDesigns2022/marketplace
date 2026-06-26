# SEO

## Blueprint goals

The long-term SEO blueprint is to make public marketplace, category, product, and store pages discoverable with high-quality metadata, clean URLs, canonicals, structured data, sitemap support, robots rules, and noindex controls for private/duplicate/low-value pages.

## Current SEO status

Implemented foundations:

- Clean public URLs for products, stores, categories, and static pages.
- Product and designer/store schema fields include SEO title and SEO description columns.
- Public product/store/category pages exist and can be expanded with dynamic metadata.

Planned / Future Phase:

- Complete dynamic metadata across all public page types if not fully rendered in current views.
- Automated sitemap generation.
- `robots.txt` review/finalization.
- Structured data implementation.
- More complete noindex rules for dashboards, auth pages, cart, checkout, admin, and seller pages.

## Dynamic metadata

Product and store tables include SEO fields. Public page rendering should prefer custom SEO title/description when present, then fall back to product/store/category names and summaries.

## Canonicals

Future SEO work should add canonical URLs for public product, store, category, browse, and static pages to prevent duplicate indexing.

## Product SEO

Product pages should use:

- Product title.
- Short description or SEO description.
- Preview image where available.
- Canonical product URL.
- Product structured data in a future phase.

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

Sitemap generation is planned. It should include public indexable pages only: homepage, browse/category pages, approved products, approved designers/stores, and selected static pages.

## `robots.txt`

`robots.txt` should allow public marketplace pages and disallow private/dashboard/admin/cart/checkout routes where appropriate.

## Noindex rules

Private and utility pages should be noindexed:

- Login/register/account.
- Buyer dashboard pages.
- Seller dashboard pages.
- Admin pages.
- Cart/checkout.
- Download routes.

## Phase 5 SEO work still planned

Phase 5 created documentation only. It did not implement SEO code. Future SEO work should update this document when metadata, canonicals, sitemap, robots, structured data, or noindex behavior changes.
