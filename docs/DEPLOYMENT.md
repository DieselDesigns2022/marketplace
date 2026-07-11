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
- Phase 10.2 did not include Stripe Tax or Phase 11 credit/referral redemption; Stripe Tax deployment is documented below in Phase 10.3B, while credits/referrals remain Phase 11.

## Phase 10.3B Stripe Tax compliance
Apply `database/migrations/2026_07_08_phase_10_3b_stripe_tax_compliance.sql` before deploying the Phase 10.3B code so `orders.tax_amount` and the Stripe Tax metadata columns exist. In Stripe Dashboard, confirm Stripe Tax is enabled/configured, the digital artwork tax category is set, prices/shipping behavior match launch policy, USD-only checkout is expected, no shipping address or shipping rates are configured by the app, and the Stripe webhook endpoint is active. After deploy, test a Stripe Checkout Session and webhook confirmation to verify `automatic_tax` is enabled, billing address collection is required, Stripe-returned tax is stored, and delivery unlocks only after a valid webhook-confirmed paid order. Include a negative webhook/test case for non-complete `automatic_tax.status` to confirm the order remains in manual review and delivery/download unlock stays blocked.

## Upload size requirements

Seller product preview images support JPG, PNG, and WEBP uploads up to 25MB each. Production PHP and web server limits must allow that size plus normal multipart overhead. Recommended minimums:

- `upload_max_filesize = 25M`
- `post_max_size = 30M` or higher
- Nginx `client_max_body_size 30M` or an equivalent reverse proxy limit

If these server limits are lower than the application limit, sellers may see a server-level upload failure before Asset Moth can show the normal validation message.
