# Digital Design Marketplace

## Project overview

Digital Design Marketplace is a PHP marketplace application for selling digital design products such as SVG cut files, fonts, and Canva templates. The current codebase is a custom MVC-style PHP application with public browsing, buyer accounts, seller applications/storefronts/product management, cart checkout, orders, downloads, and admin moderation.

## Marketplace purpose

The long-term product vision is a curated digital design marketplace where independent designers can apply to sell, manage branded storefronts, upload downloadable products, and earn revenue while buyers discover, purchase, wishlist, follow, and download digital products. Features described in the original blueprint that are not present in the current codebase are documented as Planned / Future Phase rather than current behavior.

## Current implementation status

Implemented now:

- Public homepage, browsing, category pages, product pages, store pages, static pages, and sell landing page.
- Buyer registration, login, account management, dashboard, wishlists, follows, purchases, order detail, and downloads.
- Designer application workflow with admin approval/denial.
- Approved seller dashboard, storefront settings, product creation/editing, preview images, downloadable files, tags, sales, referrals, and rank pages.
- Cart, mock checkout, order creation, order items, seller earnings, platform commissions, and protected download access.
- Admin dashboards for users, applications, designers, products, categories, orders, referrals, homepage features, and ads.
- Phase 4.5 codebase standardization changed formatting/readability only and restored public product previews/sell page regressions.

Planned / Future Phase:

- Real payment processor integration.
- Automated payouts and tax/reporting workflows.
- Full review workflow and review display polish.
- Advanced search/filtering and recommendations.
- Complete SEO execution including sitemap/robots automation where not already implemented.
- Production-grade notification/email flows.
- More complete ad campaign management and analytics.

## Technology stack

- PHP 8.3 target runtime.
- MariaDB database.
- Nginx web server.
- Ubuntu VPS.
- Custom PHP MVC-style architecture.
- PDO prepared statements.
- Session-based authentication.
- Server-rendered PHP views.
- Plain CSS and JavaScript assets.

## Server / VPS info

- Live URL: `https://marketplace.dieseldesigns.co`
- VPS application path: `/var/www/marketplace.dieseldesigns.co`
- Error log: `/var/log/nginx/marketplace.error.log`
- GitHub is the source of truth.
- VPS is the deployment and testing target.
- Codex is temporary and must not be treated as the source of truth.

## Repository structure

```text
app/
  Controllers/       Request handlers for public, auth, buyer, seller, cart, and admin flows
  Core/              Router, Database, and Helpers classes
  Views/             Layout and page templates
assets/
  css/               Application styles
  js/                Application JavaScript
database/
  schema.sql         Current baseline schema
  migrations/        Phase migration files
public/
  index.php          Front controller and route registration
docs/                Project documentation
```

## Main workflows

- Visitor workflow: browse marketplace, view products, view stores, and read static pages.
- Buyer workflow: register/login, wishlist products, follow stores, add products to cart, checkout, view orders, and download purchased files.
- Seller workflow: apply to sell, wait for approval, manage storefront, create/edit products, upload previews/files, submit products for review, and view sales/rank/referrals.
- Admin workflow: approve applications, moderate products, manage categories/designers/users/homepage/ads, and review orders/referrals.
- Deployment workflow: develop on a branch, test, open PR, review, merge, pull to VPS, run migrations, verify smoke tests.

## Documentation index

- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Testing](docs/TESTING.md)
- [Phase History](docs/PHASE_HISTORY.md)
- [Routes](docs/ROUTES.md)
- [Security](docs/SECURITY.md)
- [SEO](docs/SEO.md)
- [Codex Workflow](docs/CODEX_WORKFLOW.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [Development Guide](DEVELOPMENT.md)
- [Contributing](CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)

## Current completed phases

- Original MVP completed 2026-06-22.
- Phase 1 — Designer Applications.
- Phase 2 — Storefront Management.
- Phase 3 — Product Management.
- Phase 4 — Shopping Cart, Orders & Downloads / Marketplace Polish.
- Phase 4.5 — Codebase Standardization.
- Phase 5 — Project Documentation is this documentation-only phase.

## Future roadmap summary

Future phases should continue the blueprint carefully, marking unimplemented blueprint ideas as planned until code exists. Likely roadmap areas include production payments, payout automation, deeper SEO, advanced discovery, notifications, reviews, analytics, ads, operations hardening, and continued documentation maintenance.
