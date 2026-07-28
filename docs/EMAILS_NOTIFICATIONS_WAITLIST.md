# Emails, Notifications & Waitlist (Phase 10.5)

## Scope
Phase 10.5 adds durable in-app notifications, the public launch waitlist, consent-aware promotional campaigns, and a database-backed email queue. Purchase mail is queued only by the verified Stripe paid webhook. Refund, seller sale/coupon, seller application, product moderation, Stripe Connect setup/payout-readiness transitions, failed-payment, and waitlist events have idempotent hooks. Credit/referral, tax-reminder, promotion, bundle, and rank/badge methods are **foundation only**; those business systems are not claimed here.

## Data and consent
`notifications` uniquely identifies `(user_id,event_key)`. `email_preferences` separates optional marketing consent from transactional delivery. `waitlist_entries` stores normalized email, consent/status, and a non-secret authorization nonce; unsubscribe URLs are HMAC-signed with `EMAIL_UNSUBSCRIBE_SECRET` and validated with `hash_equals`. Campaigns snapshot recipients in `email_campaign_recipients`; `email_messages` is the durable queue. Suppressed waitlist records cannot be reactivated by public signup. Unsubscribed people may intentionally resubscribe. Marketing consent is checked again immediately before delivery.

## Routes
Public: `GET|POST /waitlist`, `GET|POST /email/unsubscribe`. Authenticated: `GET /notifications`, `POST /notifications/read-all`, and `POST /notifications/{id}/read` (the static route is registered first). Admin: `GET|POST /admin/waitlist`, `GET /admin/waitlist/export`, `POST /admin/waitlist/launch-invite`, `GET /admin/email-campaigns`, `GET|POST /admin/email-campaigns/new`, and `GET|POST /admin/email-campaigns/{id}`.

## Worker and transport
Development defaults to `MAIL_TRANSPORT=log`. Run `php scripts/process_email_queue.php 50` from cron (normally every minute). It claims with a row lock and `SKIP LOCKED`, recovers claims older than 15 minutes, processes at most 100 per invocation, retries after roughly 5 and 30 minutes and permanently fails on attempt three. Log delivery writes structured JSON lines to `storage/logs/mail.log`, containing a recipient hash rather than an address or unsubscribe token. The current repository implements the safe log transport; production provider integration must be implemented and tested before selecting a different transport.

After transport delivery, the worker transactionally attempts message, waitlist, recipient, and campaign bookkeeping. Persistence or recalculation failures become reconciliation work and never re-enter the delivery retry path. Cancellation prevents pending queued work from being sent and preserves sent history; already claimed or in-flight processing may complete. Campaign copy and templates are escaped; CTA URLs are local or HTTPS on the configured `APP_URL` origin. Run the behavioral suite and PHP lint locally. Run the opt-in database connectivity gate and documented webhook/stateful staging matrix only in a prepared disposable environment.

## Corrected operational behavior
Active repeat waitlist signups update only allowed profile fields and do not rotate authorization, reset delivery timestamps, or enqueue work. An unsubscribed entry can explicitly resubscribe with a fresh nonce/consent event; suppressed entries remain untouched. Every campaign and launch invitation gets a recipient-specific signed unsubscribe URL at queue time. Registered-user marketing requires an opted-in `email_preferences` row with a nonce. Transactional receipts, downloads, refunds, and seller operational messages do not depend on marketing consent.

Admin waitlist management exposes source/status/interest/search filters, matching totals, status totals, filter-preserving pagination/actions, individual invites, and explicitly confirmed filtered bulk invites. An unfiltered bulk action additionally requires “all eligible” confirmation. Previously invited entries are excluded by default. The worker sets `invited_at` only after delivery.

Seller new-sale, affected-coupon, product-approved, product-rejected, and materially-new product-flag events create both in-app and transactional email alerts. Stripe Connect readiness is reported as payout setup readiness; `seller tax enabled` remains foundation-only because no separate tax-setting transition exists. Campaign creation has an escaped preview action; zero-recipient campaigns terminate with an admin warning. Suppressed recipients are reported separately and do not count as failures.

## Consolidated review corrections
Waitlist consent creation/resubscription and the required confirmation queue insert commit in one transaction; administrator signup notices happen afterward and are nonblocking. Administrator invitations accept only explicit `individual` or confirmed `filtered` modes. Status changes follow consent-safe transitions: admins cannot restore unsubscribed consent or demote invited entries to subscribed.

Campaign test delivery is authorized again at delivery time against the referenced active administrator role, status, ID, and normalized account email; it does not require or change general marketing consent. `completed` is the truthful zero-delivery terminal campaign state. `sent_at` means actual delivery, while `completed_at` means terminal processing. Buyer paid events create separate receipt and download-ready notifications. Seller alert subjects use a controlled event mapping and seller action links are local-path validated.

`NotificationService` includes foundation-only admin methods for a future compliant seller-tax transition, promotional submission, and bundle submission; none are wired to Stripe readiness or fake routes/data. Queue recipient synchronization/recalculation failures are reported as reconciliation work after the message state is safely stored and do not stop independent messages.

## Delivery and webhook final hardening
The log transport is idempotent by `email_messages.id`: it holds one exclusive file lock while checking existing structured JSON lines and appending, so a retry after post-append persistence failure does not append another delivery record. Once transport delivery returns, persistence and recipient/campaign reconciliation are isolated from the retry-delivery path; their diagnostics are nonthrowing and cannot stop independent queue messages.

Only Stripe payloads that have passed webhook signature verification can create the deduplicated `webhook_issue` administrator notification. Invalid signatures return the existing error response without a database notification. The notification contains only the normalized verified event type, deterministic event key, allowlisted failure category, and a direction to protected payment and server logs. It never contains exception text, payloads, signatures, customer data, payment identifiers, credentials, tokens, or stack traces.

## Verified webhook and log recovery completion
After signature verification, missing IDs, Stripe-event lookup/insertion, business processing, and final status persistence all use the verified-event failure handler. Missing-ID notification keys combine the normalized event type with a SHA-256 fingerprint of the verified payload; only the hash enters the key. Administrator copy is controlled to event type, an allowlisted failure category, and directions to protected logs—never exception text or payload data.

While holding the exclusive mail-log lock, the log transport validates every complete newline-terminated JSON record. It truncates an incomplete trailing fragment to the last valid record boundary before retrying. A malformed complete record fails safely for operator inspection. New records use a full-write loop and flush before unlock.

## Step 8 receipt, refund, replay, and diagnostic completion
Purchase receipts use `order_items.product_title` as the authoritative purchased-title snapshot. The current product title is consulted only for a legacy blank snapshot, followed by the neutral “Purchased product” fallback. Stored license, price, discount, tax, total, and order values remain authoritative.

Refund progression uses the highest cumulative refund amount already stored in `payment_transactions`. Identical, smaller, and out-of-order cumulative reports do not regress an order or create another buyer communication; increased partial refunds and partial-to-full transitions use stable order/status/cumulative-cent keys. The buyer email and notification show the meaningful cumulative refunded amount.

Every verified paid, non-manual-review event re-attempts only the stable deduplicated buyer/seller communication set. A replay of an already processed event may perform that same nonblocking communication recovery, but does not repeat coupon accounting, earnings changes, payout-ledger preparation, transfers, delivery unlock, or payment transaction logging. Processed refund replay likewise re-attempts only the current authoritative transition communication.

`OperationalErrorSanitizer` removes stack traces, markup, controls, email addresses, authorization values, common API-key header/assignment variants, Stripe signatures/secrets/identifiers, credential assignments, database DSNs/passwords, URL userinfo, token-bearing URLs, and unsubscribe-token-shaped values before Phase 10.5 diagnostics reach protected logs or database error fields. Webhook administrator alerts remain controlled copy and never receive exception text.
