# Troubleshooting

## HTTP 500 checks

1. Reproduce the failing page/action.
2. Check `/var/log/nginx/marketplace.error.log`.
3. Run `php -l` on recently modified PHP files.
4. Check recent commits and changed files.
5. Verify `.env` database settings.
6. Verify migrations were applied.

## Nginx/PHP error log

```bash
sudo tail -n 100 /var/log/nginx/marketplace.error.log
```

Use the stack trace/file/line from the log as the primary debugging clue.

## 419 CSRF troubleshooting

- Confirm the form includes `_csrf`.
- Confirm the session is active.
- Confirm cookies are not blocked.
- Confirm POST requests are not cached/replayed with old tokens.
- Confirm the route is not receiving POST data from a form missing the shared layout token pattern.

## Missing controller method troubleshooting

- Check `public/index.php` route definition.
- Confirm the controller class exists.
- Confirm the method name matches exactly.
- Confirm method visibility is `public` for routed actions.
- Run `php -l` on the controller.

## Preview image troubleshooting

- Confirm upload folder exists and is writable.
- Confirm image path is stored in `product_images`.
- Confirm public product/store views use the stored path.
- Confirm Nginx can serve the public upload path.
- Confirm invalid file types are rejected.

## Upload folder troubleshooting

- Confirm ownership matches the web runtime user.
- Confirm permissions allow writes by PHP.
- Confirm upload size limits in PHP/Nginx are large enough.
- Confirm public uploads and protected uploads are in separate locations.
- Confirm uploads are ignored by Git.

## Git branch mismatch troubleshooting

- Run `git branch --show-current`.
- Run `git status --short`.
- Run `git fetch origin`.
- Compare local branch with expected GitHub branch.
- Do not merge or deploy until the branch/target is clear.

## Codex PR conflict troubleshooting

- Treat GitHub as source of truth.
- Fetch latest remote state.
- Resolve conflicts one file at a time.
- Avoid broad rewrites.
- Re-run checks and smoke tests.
- Document conflict resolution in the PR.

## Rollback from backup

1. Stop traffic or pause changes if needed.
2. Save current logs and note current commit.
3. Restore project `.tar.gz` backup if source is broken.
4. Restore database `.sql` backup if data/schema is broken.
5. Pull verified GitHub source after restore if needed.
6. Reapply only safe migrations.
7. Smoke test public, buyer, seller, admin, cart, checkout, and downloads.

### Phase 10.4 troubleshooting

If the seller warning does not appear, inspect enabled rows in `ip_risk_terms` and `ip_risk_term_aliases`, confirm product tags/files/SEO metadata are saved, and review `product_ip_risk_scans` plus active detections. If confirmation repeats, compare the latest scan ID and `product_ip_rights_confirmations`; changed matches require a new confirmation.

If an admin flag is missing, check `product_ip_risk_states.latest_scan_id`, active detection rows tied to `product_ip_risk_states.latest_scan_id`, and active detections. Duplicate term or alias errors come from normalized text collisions across canonical terms and aliases. Stale active detections indicate a scan failed before old detections were marked inactive or the latest scan did not complete. Contradictory product/IP statuses should be corrected through valid product moderation and IP transition rules. Migration/schema mismatches and permanent delete failures usually indicate missing FKs or uncleared Phase 10.4 child rows; inspect database errors and the seven IP risk tables.


#### Phase 10.4 bulk approval skipped for IP review
If bulk approval reports products skipped because IP review is required, open each product detail page and use the IP / Protected Content Risk section. Ordinary approval intentionally cannot bypass active matches with pending IP review.

#### Phase 10.4 atomic transition failure
If an IP reject, archive, or publish-while-flagged action fails, inspect the application/database error and verify `products`, `product_ip_risk_states`, and `product_ip_risk_review_history`. The transaction is designed to prevent partial product/IP state or misleading success logs; retry only after fixing the underlying database or validation issue.


#### Phase 10.4 published edit rejected while original remains live
If a seller edit is rejected for missing IP confirmation, the current live approved/published listing should remain unchanged. Ask the seller to resubmit with the confirmation checkbox after reviewing the detected terms.

#### Phase 10.4 admin transition requires product recovery
If an IP action is rejected because the product is archived, disabled, deleted, or otherwise terminal for that action, use the normal product recovery workflow first where available; IP review actions must not revive deleted or terminal products.

#### Phase 10.4 list/detail count mismatch
The admin list counts active detections only for `product_ip_risk_states.latest_scan_id`. If counts differ from detail, inspect the latest state scan ID and active detection rows for older scans.

#### Phase 10.4 confirmation missing for saved scan
If a flagged approved/published edit saves but no confirmation appears, compare `product_ip_risk_states.latest_scan_id` with `product_ip_rights_confirmations.scan_id`. They must match for the authenticated seller; otherwise treat the save as invalid and investigate transaction/log errors.

#### Phase 10.4 preview filename not flagged
Phase 10.4 scans stored downloadable product-file original names. Preview-image original upload names are not retained as authoritative seller-facing filename metadata for future rescans, so they are intentionally not used for IP-risk matching.

#### Phase 10.4 new-product cleanup after failed save
If a system/database failure occurs after a new product row is created, inspect the failed new product ID and verify compensating cleanup removed `product_ip_risk_review_history`, `product_ip_rights_confirmations`, `product_ip_risk_detections`, `product_ip_risk_states`, `product_ip_risk_scans`, preview and downloadable upload rows/files, product tags, product license rows, and the new product row. Missing confirmation is different: the product should remain as a draft with its valid scan/uploads/tags/licenses and should not be deleted.
