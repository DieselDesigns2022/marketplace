# Analytics and reporting

Phase 15 reports use the existing order, seller-payout, refund, coupon, promotion, referral, collaborative-event, user, product, payment, and webhook records. Historical financial reports and rankings therefore cover existing records according to their stored payment and ledger state.

## Tracking that starts with Phase 15

Traffic attribution and marketplace-search history were not previously stored. The Phase 15 migration adds an order-level `traffic_source` snapshot and privacy-conscious `search_events` records. Older orders appear as **Unknown / Unattributed**. Search reports have no history before the migration.

Attribution uses an explicit referral-link or UTM source when present, otherwise the landing referrer host. Recognized Facebook, Pinterest, and Google traffic is grouped by name; no-referrer landings are Direct, and unsupported sources remain Other / Unknown. An account's referral relationship and the existence of a social post are never treated as order attribution. Attribution is retained in the session for at most 30 days and snapshotted when a future order is created.

Search events contain only a normalized query, result count, and timestamp. They do not contain a user ID, session ID, IP address, or referrer. Product-click and purchase attribution to a search is unavailable because the marketplace does not record a provable connection.

## Comparisons and insights

The selected inclusive date range is compared with the immediately preceding range of the same length. Percentage change is omitted when the previous value is zero. Insights are deterministic rules over calculated report values: period sales direction, repeat customers, refund share, highest-grossing product, and admin zero-result searches. They do not call an AI service, claim causation, or calculate view/conversion metrics that are not recorded.

The marketplace records sponsored-promotion impressions and clicks, but it does not record general product/store views. Organic listing conversion rates and storewide view-to-purchase conversion are therefore unavailable.
