# Deployment

## Environment

- Live URL: `https://marketplace.dieseldesigns.co`
- VPS path: `/var/www/marketplace.dieseldesigns.co`
- Stack: Ubuntu, Nginx, PHP 8.3, MariaDB
- Error log: `/var/log/nginx/marketplace.error.log`

## Source of truth

GitHub is the source of truth. The VPS is the deployment and testing target. Codex is temporary and must not be treated as permanent source control.

## Before each phase

1. Remove old scattered backup files from the project tree.
2. Create a fresh project `.tar.gz` backup.
3. Create a fresh database `.sql` backup.
4. Confirm backups are outside committed source control.
5. Confirm `.env`, public uploads, and protected uploads are not committed.

## Pull a branch on VPS

```bash
cd /var/www/marketplace.dieseldesigns.co
git fetch origin
git checkout <branch-name>
git pull --ff-only origin <branch-name>
```

## Pull main after merge

```bash
cd /var/www/marketplace.dieseldesigns.co
git checkout main
git pull --ff-only origin main
```

## Migration workflow

1. Back up database first.
2. Review migration SQL.
3. Apply only migrations needed for the branch/merge.
4. Verify schema and core workflows after migration.
5. Keep `database/schema.sql` aligned with current schema when schema changes are made.

Example:

```bash
mysql -u <user> -p <database> < database/migrations/<migration-file>.sql
```

Phase 10.1 deployments must apply `database/migrations/2026_07_07_phase_10_1_product_cleanup.sql` before relying on archived/deleted product statuses or admin/seller cleanup tools.

## Upload folder permissions

Upload folders must be writable by the PHP/Nginx runtime user but must not be committed to Git. Public preview uploads may be web-accessible. Protected product files must not be directly web-accessible.

## Error log

Use the Nginx/PHP error log for HTTP 500 triage:

```bash
sudo tail -n 100 /var/log/nginx/marketplace.error.log
```

## Rollback notes

- Source rollback: check out the last known good Git commit or restore the project `.tar.gz` backup.
- Database rollback: restore the last known good database `.sql` backup.
- Always capture current broken state/logs before rollback if possible.
- After rollback, smoke test public, auth, buyer, seller, admin, cart, checkout, and downloads.

## Phase 6 SEO deployment notes

Before requesting indexing, set `APP_URL=https://marketplace.dieseldesigns.co` in the current build/test deployment or rely on the current fallback. After deployment, verify `https://marketplace.dieseldesigns.co/robots.txt`, `https://marketplace.dieseldesigns.co/sitemap.xml`, public canonicals, and noindex behavior for private workflow pages. Treat `https://assetmoth.com` as the future domain migration target after purchase and DNS/application migration. Submit the sitemap in Google Search Console only after production content, support process, and owner legal/privacy review are complete.

## Phase 6 completed deployment state

Phase 6 was validated on the VPS deployment path `/var/www/marketplace.dieseldesigns.co` and pushed to `origin/phase-6-seo-foundation-indexing`. A completed post-Phase-6 project backup was created under `/root/marketplace-phase-backups/` before moving into the next phase workflow. Before starting Phase 7, merge Phase 6 into the main project baseline and create the Phase 7 branch from that updated baseline.

## Phase 10.2 Coupon Deployment Notes
- Apply `database/migrations/2026_07_07_phase_10_2_coupons_discounts_commission_rules.sql` before enabling coupon UI in a deployed environment.
- The migration creates coupon definition, restriction, and usage tables and adds order/order item coupon snapshot columns.
- The migration includes idempotent `CREATE TABLE IF NOT EXISTS` and `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` statements for existing environments.
- Phase 10.2 reserved tax and credit/referral handling in the checkout order. Phase 10.3 now implements seller opt-in store-level sales tax; Phase 11 credit/referral redemption remains future-only.

## Phase 10.3 store-level sales tax settings
- Apply `database/migrations/2026_07_07_phase_10_3_store_level_sales_tax_settings.sql` before enabling seller tax settings in production.
- Verify `designers` has the `sales_tax_*` columns, `orders`/`order_items` have `tax_amount` and `tax_snapshot`, and seller payout/earning tables have `seller_tax_collected`.
- Smoke test checkout with a seller-entered tax rate and confirm `orders.total`, `orders.stripe_amount_total`, and the Stripe Checkout amount include seller tax.
- Confirm seller tax is visible to admins/sellers and is included in seller payable tracking while platform commission remains based on the discounted item subtotal before tax.
- Stripe Tax and marketplace facilitator tax automation remain disabled/future-only.
