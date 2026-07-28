# Phase 10.6 dashboard audit

| Requirement | Pre-implementation | Finished status |
|---|---|---|
| Buyer/seller/admin desktop and 320px layout | Partially complete | Complete but cleaned up — shared cards, spacing, wrapping actions, focus styles, and responsive-table scrolling. |
| Role-aware secondary navigation | Partially complete | Added in Phase 10.6 — server-side role sets, dynamic detail-route matching, current-page state, and notification shortcuts. |
| Buyer cards and recent content | Partially complete | Added in Phase 10.6 — purchase, eligible-file, wishlist, unread counts plus recent orders, wishlist, and notifications. |
| Buyer download history | Moved for clearer placement | Separate existing page — every protected product file has buyer-owned order context and accurate paid/refunded/expired/manual/missing states. |
| Buyer receipts | Already complete | Complete but cleaned up — seller snapshot groups remain separate from Asset Moth payment, refund, tax, coupon, license, total, and fulfillment data. |
| Seller readiness and warnings | Partially complete | Complete but cleaned up — corrective links and unresolved warnings; incomplete sellers remain on the usable onboarding gate. |
| Seller Stripe and tax status | Partially complete | Added in Phase 10.6 — friendly account states and platform Stripe Tax/no-action explanation. |
| Seller products, earnings, payouts, orders | Partially complete | Added in Phase 10.6 — scoped status totals, tax-exclusive cumulative refund allocation, net gross/earnings, replay-safe pending payout reconciliation, transferred payouts, and recent paid seller items. |
| Seller notifications and actions | Partially complete | Added in Phase 10.6 — unread/recent data and links to every existing seller tool. |
| Admin attention and statistics | Partially complete | Added in Phase 10.6 — operational queues, Stripe/webhook/payout warnings, live-only money, catalog/user/waitlist totals. |
| Admin recent activity, notifications, waitlist | Partially complete | Added in Phase 10.6 — admin logs, owned notifications, interest/confirmation/invitation breakdown, and management links. |
| Tables, statuses, empty states | Partially complete | Complete but cleaned up — responsive wrappers, clear badges, warnings separated from statistics, and useful next actions. |
| Role permissions | Already complete | Already complete — controller role/ownership checks remain authoritative; navigation is not authorization. |
| Protected download availability | Partially complete | Complete but cleaned up — dashboard counts, download history, and buyer order-page buttons require a buyer-owned, paid, unexpired downloadable file that resolves inside protected product storage as a readable regular file. |
| Receipt decompression and snapshot resilience | Partially complete | Complete but cleaned up — source images are capped at 25 megapixels and exact PNG IEND, WEBP RIFF length, or JPEG EOI boundaries are required before GD decode; invalid optional current settings become null snapshots and cannot block checkout. |
| Account navigation and operational warnings | Partially complete | Complete but cleaned up — buyer, seller, and admin navigation includes Account; seller payout issues and failed/manual-review admin payments remain actionable until resolved. |
| Deterministic category consolidation | Partially complete | Complete but cleaned up — normalized canonical-slug matches take priority over normalized names, and each duplicate receives exactly one different canonical target before products and coupon restrictions move. |
| Free checkout, new fulfillment, messaging, refund controls, seller tax | Intentionally outside Phase 10.6 | Intentionally outside Phase 10.6. |
