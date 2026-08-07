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

Upload folders must be writable by the PHP/Nginx runtime user but must not be committed to Git. Public preview uploads may be web-accessible. Protected product files must not be directly web-accessible. Phase 10.6 banner normalization additionally requires an application-writable `public/uploads/store_banners/` directory with script execution prohibited.

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
- Phase 10.2 did not include Stripe Tax or credit/referral redemption; those capabilities were subsequently implemented in Phases 10.3B and 11.

## Phase 10.3B Stripe Tax compliance
Apply `database/migrations/2026_07_08_phase_10_3b_stripe_tax_compliance.sql` in its historical order before the Phase 11 migration. For the current deployment, confirm Stripe Tax and the digital-product tax code are configured, USD/US-only rules are expected, and the webhook is active. Verify the Phase 11 standalone Tax Calculation, normalized billing snapshot, post-capture Tax Transaction, address-mismatch manual review, and delivery lock; do not expect Checkout `automatic_tax` to be enabled because the remaining Stripe line item already includes the authoritative precomputed tax after credits.

## Upload size requirements

Seller product preview images support JPG, PNG, and WEBP uploads up to 25MB each. Production PHP and web server limits must allow that size plus normal multipart overhead. Recommended minimums:

- `upload_max_filesize = 25M`
- `post_max_size = 30M` or higher
- Nginx `client_max_body_size 30M` or an equivalent reverse proxy limit

If these server limits are lower than the application limit, sellers may see a server-level upload failure before Asset Moth can show the normal validation message.

### Phase 10.4 deployment notes

Run `database/migrations/2026_07_13_phase_10_4_ip_risk_compliance.sql` before using the Phase 10.4 code. Back up the database first. This migration is not documented as idempotent; do not run it twice without checking table existence. Verify fresh schema parity against `database/schema.sql`, then check that the seven IP risk tables exist, starter terms are present, and FK constraints were created.

Rollback order is: `product_ip_risk_review_history`, `product_ip_rights_confirmations`, `product_ip_risk_states`, `product_ip_risk_detections`, `product_ip_risk_scans`, `ip_risk_term_aliases`, `ip_risk_terms`. Do not run production migrations from an agent session.

## Phase 10.5 deployment
1. Back up, then apply `database/migrations/2026_07_20_phase_10_5_emails_notifications_waitlist.sql` after Phase 10.4.
2. Set `MAIL_TRANSPORT=log`, `MAIL_QUEUE_BATCH_SIZE`, and a randomly generated `EMAIL_UNSUBSCRIBE_SECRET` of at least 32 bytes before waitlist or marketing delivery. `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` are reserved for a future production provider and are not consumed by the current log transport. Verify that `APP_URL` is the final HTTPS application origin before queueing mail because unsubscribe URLs, email links, and absolute CTA-origin validation derive from it.
3. Grant the PHP/cron user write access to `storage/logs` without making it web-accessible.
4. Schedule `php /path/to/marketplace/scripts/process_email_queue.php 50` every minute and alert on a nonzero exit.
5. Before enabling a real transport, test provider authentication, sender verification, bounce/suppression handling, unsubscribe links, concurrency, and secret-redacted logging.

Each worker run clamps its requested batch to 1–100 messages. It recovers processing claims older than 15 minutes, retries delivery failures after approximately 5 and 30 minutes, and makes the third failed delivery attempt terminal.

The protected log transport repairs an incomplete trailing fragment automatically while holding its exclusive lock. A malformed complete JSON record instead causes a nonzero worker failure. Stop the worker, inspect the protected log, and safely rotate it under operational control if necessary; do not blindly delete or overwrite the log.

### Phase 10.5 deployment safety
The Phase 10.5 migration is **not idempotent**. Back up the database and inspect migration state before applying it; never run it twice blindly. Apply the migration before activating application code that queries the new tables. In particular, the shared authenticated layout queries `notifications`, so code-first deployment can break authenticated page rendering. Use maintenance mode or the project’s schema-first safe deployment order when an atomic release is unavailable.

Configure `EMAIL_UNSUBSCRIBE_SECRET` before accepting waitlist signups, administrator test sends, or marketing queue work. Rotating this secret invalidates outstanding unsubscribe links unless a planned dual-key/migration strategy is used. `MAIL_TRANSPORT=log` is the only implemented transport; no production provider is included.



## Phase 10.6 deployment

First validate `database/migrations/2026_07_28_phase_10_6_dashboard_cleanup_usability.sql`, followed by `database/migrations/2026_07_28_phase_10_6_live_fixes.sql`, then `database/migrations/2026_07_29_phase_10_6_issue_resolution.sql` in a disposable MariaDB environment. During deployment, apply each once in that order: the live-fixes migration follows the original Phase 10.6 migration, and issue resolution follows both.

PHP must provide Fileinfo, GD decoding, and working JPEG, PNG, and WEBP encoders. Store-banner sources over 40,000,000 pixels are rejected before decode, and normalization must preserve PNG/WEBP transparency while producing the exact 2400 × 800 canvas. Create `public/uploads/receipt_images/` and `public/uploads/store_banners/` as web-readable, application-writable image directories and configure the web server to prohibit script execution in both. Uploads are decoded/re-encoded and receive random names; do not restore submitted names.

After deployment, run PHP lint and both Phase 10.6 and Phase 10.5 behavioral suites, verify migration idempotency in disposable MariaDB, exercise note/image replace/remove/restore flows, confirm historical images remain, and check buyer/seller/admin layouts at 320px and desktop widths. Upload non-3:1 JPG, PNG, and WEBP store banners and verify each saved result is exactly 2400 × 800 without stretching, transparent PNG/WEBP inputs retain transparency, sources over 40,000,000 pixels are rejected, centered responsive 3:1 display is consistent, and old allowlisted files are replaced only after a successful database save. Verify `/waitlist` contains only its form or success/validation state and has no global header, footer, logo, or navigation links. The receipt processor rejects source images over 25,000,000 pixels before GD decode. Deployment verification must also confirm buyer availability ignores missing, unreadable, and out-of-directory protected-file records.

### Phase 10.6 live-tested upload configuration

The marketplace production product-upload path uses PHP-FPM `upload_max_filesize=600M`, `post_max_size=650M`, and `max_file_uploads=200`. Its Nginx virtual host uses `client_max_body_size 650M`. Keep the complete multipart request within 650 MB. Receipt images remain application-limited to 10 MB, and seller avatars/store banners remain application-limited to 25 MB.

### Phase 11 deployment
After Phase 10.6, back up MariaDB and apply `database/migrations/2026_07_31_phase_11_referrals_credits_store_credit.sql`; it uses rerunnable column/index operations and deterministic legacy backfills. Verify referral-code uniqueness, no negative/reserved-over-total balances, ledger keys, and foreign keys, then rerun the migration test. `APP_URL`, Stripe secret/webhook secrets, USD currency, Stripe Tax registration, and the digital-product tax code must be configured. Checkout now calls Stripe Tax before credit reservation, including credit-only orders, so Tax API availability is required. Rollback should be forward-fix/data-preserving rather than dropping immutable financial history.

#### Required Phase 11 correction verification
Use the deployed MariaDB version and a disposable pre-Phase-11 database. Run `PHASE11_ALLOW_FIXTURE=1 DB_HOST=127.0.0.1 DB_NAME=marketplace_test_control DB_USER=... DB_PASS=... php tests/Phase11DatabaseIntegrationTest.php`. The test creates a randomized disposable database, applies the migration three times, and compares `information_schema` plus referral/credit/ledger data after each rerun. Verify Stripe Tax in test mode by creating a Calculation, completing both a Stripe-funded and credit-funded order, and confirming the resulting `tax_` Transaction created with the order reference. Platform-credit payout holds require an explicit admin/platform-balance transfer process; they are not eligible for automatic source-charge transfers.

Before enabling settlement, verify the Phase 11 qualifying-reference foreign keys/indexes and the `seller_payouts` settlement audit columns. In Stripe test mode, use an active admin to POST the CSRF-protected settlement action for a disposable internal order and confirm the transfer has no `source_transaction`, uses `order_{id}` as its transfer group, and records the transfer ID, actor, and timestamp. A failure must remain `platform_credit_hold` and retry with the same idempotency key. The deterministic CLI transport tests API construction and failure/replay behavior, but do not replace this Stripe test-mode check.

The disposable suite requires `proc_open`, permission to create/drop randomized databases, and at least two concurrent MariaDB connections. It executes CLI-only controller and connection probes from `tests/helpers`; these helpers cannot be selected by web requests or production configuration.


Codex could not connect to MariaDB or execute live Stripe test-mode requests during the Phase 11 audit. A `SKIP` from the fixture suite and unexecuted Stripe API verification are deployment blockers, not passes.
# Monthly seller-referral commissions (Phase 11)

After applying `2026_08_01_phase_11_seller_referral_lifetime_commission.sql`, run the
following once per month for the prior closed UTC month (the optional argument is
the payout month):

```bash
php scripts/pay_seller_referral_commissions.php YYYY-MM
```

The command uses each approved referrer's existing Stripe Connect account and a
stable seller/month idempotency key. It intentionally creates platform-balance
transfers without a source transaction. A non-zero status requires operator
attention; failed or onboarding-incomplete earnings remain in the unpaid ledger.

The monthly period is a closed UTC calendar month. Omitting `YYYY-MM` selects the previous closed UTC month. Processing claims expire after 15 minutes; an admin retry recovers stale processing with the same batch idempotency key. Failed/not-ready batches retain claimed unpaid ledger entries for controlled retry. Later adjustments after a paid batch are included in a new sequence for that period or offset a future positive batch; negative balances never produce Stripe transfers.

### Phase 12 deployment
1. Back up the production database and verify restoration procedures.
2. Apply `database/migrations/2026_08_06_phase_12_creator_ranks_founder_badge.sql`; it is repeatable, but inspect every migration result.
3. Run `php scripts/recalculate_creator_recognition.php --dry-run` and retain the proposed deterministic first-50 positions for review.
4. With unchanged order/refund data, run `php scripts/recalculate_creator_recognition.php --apply` and compare its results to the reviewed plan.
5. Verify seller/admin rank pages, one storefront, one product, history counts, and queued communications.
6. Install daily UTC inactivity processing: `0 2 * * * cd /path/to/marketplace && /usr/bin/php scripts/recalculate_creator_recognition.php --daily >> storage/logs/creator-recognition-cron.log 2>&1`.
7. Run the Phase 12 behavior suite and disposable MariaDB migration/concurrency suite; a database `SKIP` is not release verification.

Recognition timestamps and the daily job use UTC. Verify the six guarded Phase 12 foreign keys after migration. Founder `restore` removes forced inactivity and immediately applies automatic eligibility; only `force_active` bypasses the 60-day rule.

`--apply` is the silent initial historical write; `--daily` is the recurring UTC mode that communicates real transitions. Paid/refund trigger keys permit replay recovery without duplicate messages.
