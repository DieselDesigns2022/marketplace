# Asset Moth

## Project Overview

Asset Moth is a custom PHP marketplace application for selling digital design products such as SVG cut files, fonts, and Canva templates. The current implementation includes public browsing, buyer accounts, designer applications, seller storefronts, product management, cart checkout, orders, protected downloads, and admin moderation.

## Current Project Status

- Development Status: active documentation and marketplace feature development.
- Current Phase: Phase 7 — Marketplace Content & Launch Polish.
- Default Branch: `main`.
- Source of Truth: GitHub.
- Current build/test URL: `https://marketplace.dieseldesigns.co`.
- Future planned domain after purchase/migration: `https://assetmoth.com`.

## Marketplace Purpose

The long-term product vision is a curated digital design marketplace where independent designers can apply to sell, manage branded storefronts, upload downloadable products, and earn revenue while buyers discover, purchase, wishlist, follow, and download digital products.

Features from the original blueprint that are not implemented in the current codebase are treated as **Planned / Future Phase** items and are not documented as currently working features.

## Current Implementation Status

These features represent the current implemented and tested functionality in the codebase.

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
- Advanced SEO iteration after launch data is available.
- Production-grade notification and email flows.
- More complete ad campaign management and analytics.


### Phase 7 launch polish

Phase 7 improves marketplace clarity and launch readiness across the shared layout, homepage, browse/category pages, product pages, storefronts, sell landing page, and buyer/seller/admin dashboards. The header expects a local logo at `public/assets/img/asset-moth-logo.png` and safely falls back to visible text if the image is missing. Phase 7 intentionally does not add the future full licensing system, advanced search, real payment processing, production emails, referral/rank systems, sponsored listings, or bundle events.

## Project Principles

- GitHub is the source of truth.
- VPS is used only for deployment and testing.
- All work is completed on feature branches.
- Every change requires a Pull Request.
- Every Pull Request is manually reviewed before merging.
- Production changes require backups before deployment.

## Quick Start

To run the project in a local or VPS environment:

1. Clone the repository from GitHub.
2. Configure `.env` with the required database connection settings and environment values.
3. Import `database/schema.sql` into MariaDB.
4. Configure Nginx so the document root points to the project `public/` directory.
5. Ensure public upload folders and protected upload folders exist and are writable by the web server/PHP runtime user.
6. Launch the application through the configured web server.
7. Verify the homepage, login/register pages, browse page, cart, dashboards, and protected download workflow as appropriate for the environment.

## Technology Stack

- PHP 8.3.
- MariaDB.
- Nginx.
- Ubuntu VPS.
- Server-rendered PHP.
- PDO.
- Session authentication.
- Custom MVC-style application structure.
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
    Controllers/
    Core/
    Views/

assets/
    css/
    js/

database/
    schema.sql
    migrations/

docs/

public/
```

### Important folders

- `app/`: Application source for controllers, core routing/database/helper classes, and server-rendered views.
- `assets/`: Public CSS and JavaScript assets used by the application.
- `database/`: Current baseline schema and phase migration files.
- `docs/`: Project documentation for architecture, database, deployment, testing, routes, security, SEO, workflows, and troubleshooting.
- `public/`: Web document root and front controller entrypoint.

## Main Workflows

### Visitor Workflow

- Visit the homepage.
- Browse products.
- View categories.
- View product detail pages.
- View designer store pages.
- Read public static pages.
- Visit the sell landing page.

### Buyer Workflow

- Register or log in.
- Browse products.
- Wishlist products.
- Follow designers.
- Add products to cart.
- Purchase products through checkout.
- Download purchases.
- View order history.
- Manage account details.

### Seller Workflow

- Submit a designer application.
- Wait for admin approval.
- Manage storefront settings.
- Create and edit products.
- Upload preview images and protected product files.
- Submit products for admin review.
- View sales.
- Review referrals and creator rank.

### Admin Workflow

- Review marketplace dashboard.
- Manage users.
- Approve or deny designer applications.
- Manage designers.
- Moderate products.
- Manage categories.
- Review orders and order details.
- Manage homepage features and ads.
- Review referrals.

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

## Phase 6 SEO launch foundation

The application now includes Asset Moth public branding, shared metadata rendering, absolute canonicals, robots meta controls, browse filtered-URL noindex behavior, dynamic `/sitemap.xml`, `public/robots.txt`, conservative JSON-LD structured data, public launch copy, and internal links across key public pages.


Current phase: Phase 8 — Search & Browsing
