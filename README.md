# Digital Design Marketplace

## Project Overview

Digital Design Marketplace is a custom PHP marketplace application for selling digital design products such as SVG cut files, fonts, and Canva templates. The current implementation includes public browsing, buyer accounts, designer applications, seller storefronts, product management, cart checkout, orders, protected downloads, and admin moderation.

## Marketplace Purpose

The long-term product vision is a curated digital design marketplace where independent designers can apply to sell, manage branded storefronts, upload downloadable products, and earn revenue while buyers discover, purchase, wishlist, follow, and download digital products.

Features from the original blueprint that are not implemented in the current codebase are treated as **Planned / Future Phase** items and are not documented as currently working features.

## Current Implementation Status

### Implemented now

- Public homepage, browse page, category pages, product pages, store pages, static pages, and sell landing page.
- Buyer registration, login, account management, dashboard, wishlists, follows, purchases, order detail, and downloads.
- Designer application workflow with admin approval and denial.
- Approved seller dashboard, storefront settings, product creation/editing, preview images, downloadable files, tags, sales, referrals, and rank pages.
- Cart, mock checkout, order creation, order items, seller earnings, platform commissions, and protected download access.
- Admin dashboards for users, applications, designers, products, categories, orders, referrals, homepage features, and ads.
- Phase 4.5 codebase standardization for readability, plus restoration of public product previews and sell page regressions.

### Planned / Future Phase

- Real payment processor integration.
- Automated payouts and tax/reporting workflows.
- Full review workflow and review display polish.
- Advanced search, filtering, and recommendations.
- Complete SEO execution, including sitemap and robots automation where not already implemented.
- Production-grade notification and email flows.
- More complete ad campaign management and analytics.

## Quick Start

For local or VPS development, use GitHub as the source of truth and work from a dedicated branch.

```bash
git branch --show-current
git status --short
```

Recommended development flow:

1. Confirm the current branch and scope.
2. Create backups before each phase.
3. Make focused changes only within the approved scope.
4. Run the required checks for the files changed.
5. Commit changes and open a PR only after the required review/approval workflow is satisfied.
6. Deploy to the VPS only after merge and testing.

## Technology Stack

- PHP 8.3 target runtime.
- MariaDB database.
- Nginx web server.
- Ubuntu VPS.
- Custom PHP MVC-style architecture.
- PDO prepared statements.
- Session-based authentication.
- Server-rendered PHP views.
- Plain CSS and JavaScript assets.

## Server / VPS Info

- Live URL: `https://marketplace.dieseldesigns.co`
- VPS application path: `/var/www/marketplace.dieseldesigns.co`
- Error log: `/var/log/nginx/marketplace.error.log`
- GitHub is the source of truth.
- VPS is the deployment and testing target.
- Codex is temporary and must not be treated as the source of truth.

## Repository Structure

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

## Main Workflows

- Visitor workflow: browse marketplace, view products, view stores, and read static pages.
- Buyer workflow: register/login, wishlist products, follow stores, add products to cart, checkout, view orders, and download purchased files.
- Seller workflow: apply to sell, wait for approval, manage storefront, create/edit products, upload previews/files, submit products for review, and view sales/rank/referrals.
- Admin workflow: approve applications, moderate products, manage categories/designers/users/homepage/ads, and review orders/referrals.
- Deployment workflow: develop on a branch, test, open PR, review, merge, pull to VPS, run migrations, and verify smoke tests.

## Documentation Index

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

## Current Completed Phases

- Original MVP completed 2026-06-22.
- Phase 1 — Designer Applications.
- Phase 2 — Storefront Management.
- Phase 3 — Product Management.
- Phase 4 — Shopping Cart, Orders & Downloads / Marketplace Polish.
- Phase 4.5 — Codebase Standardization.
- Phase 5 — Project Documentation.

## Future Roadmap Summary

Future phases should continue the blueprint carefully, marking unimplemented blueprint ideas as planned until code exists. Likely roadmap areas include production payments, payout automation, deeper SEO, advanced discovery, notifications, reviews, analytics, ads, operations hardening, and continued documentation maintenance.
