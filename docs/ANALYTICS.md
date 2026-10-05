# Analytics and reporting

Phase 15 reports use the existing order, seller-payout, refund, coupon, promotion, referral, collaborative-event, user, product, payment, and webhook records. Historical financial reports and rankings therefore cover existing records according to their stored payment and ledger state.

Admin screens and exports require the existing `dashboard.view` permission. Seller screens and exports require an authenticated approved seller and derive the designer identifier from that account; a request cannot select another seller. The routes are `GET /admin/analytics`, `GET /admin/analytics.csv`, `GET /seller/analytics`, and `GET /seller/analytics.csv`.

## Tracking that starts with Phase 15

Traffic attribution and marketplace-search history were not previously stored. The Phase 15 migration adds an order-level `traffic_source` snapshot and privacy-conscious `search_events` records. Older orders appear as **Unknown / Unattributed**. Search reports have no history before the migration.

Attribution uses a referral link only after its code is validated through the existing active-user referral mechanism, or an explicit `utm_source` when present; otherwise it uses the landing referrer host. Recognized Facebook, Instagram, Pinterest, and Google traffic is grouped by name; no-referrer landings are Direct, and unsupported sources remain Other / Unknown. Random or invalid `ref` values are not Referral attribution and do not replace a current, unexpired attribution. A valid UTM source or validated referral may update it. An account's referral relationship and the existence of a social post are never treated as order attribution. Attribution is retained in the session for at most 30 days and snapshotted when a future order is created.

Search events contain only a normalized query, result count, and timestamp. They do not contain a user ID, session ID, IP address, or referrer. Only the first results page records an event. Tracking is completely fail-open: a missing table, failed write, or failure in safe error reporting does not prevent Browse/category results from loading. Product-click and purchase attribution to a search is unavailable because the marketplace does not record a provable connection.

## Comparisons and insights

The selected inclusive date range can be compared with either the immediately preceding range of the same length or the same calendar dates in the prior year. Leap-day comparisons safely use the last valid day in February. Percentage change is omitted when the previous value is zero. Insights are deterministic rules over calculated report values: period sales direction, repeat customers, refund share, highest-grossing product, Friday–Sunday sales share when at least five qualifying orders exist, and admin zero-result searches. They do not call an AI service, claim causation, or calculate view/conversion metrics that are not recorded.

The marketplace records sponsored-promotion impressions and clicks, but it does not record general product/store views. Organic listing conversion rates and storewide view-to-purchase conversion are therefore unavailable.

Seller customer ranking includes only orders where that seller retains a positive authoritative gross portion after refunds. Returning customers are the separate subset with more than one such qualifying order. Admin customer ranking remains marketplace-wide. Friday–Sunday insights require at least five qualifying orders. CSV exports use the same assembled report and filters as the screen, export the financial comparison plus the applicable product, customer, returning-customer, weekday, source, and admin-search sections, and neutralize text beginning with spreadsheet formula characters.
