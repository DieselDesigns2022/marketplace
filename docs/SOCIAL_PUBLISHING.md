# Phase 13.6 — Social product publishing

## Deployment configuration

Apply `database/migrations/2026_09_21_phase_13_6_social_product_posting.sql`. PHP must provide cURL, JSON, and Sodium. Set every variable in the social-publishing section of `.env.example`; the credential encryption key must be a base64-encoded 32-byte random value and must remain stable between deploys. Changing it makes stored connections unreadable. Use HTTPS callback URLs and register them exactly with each provider.

## Provider applications

### Meta / Facebook and Instagram

Create a Meta developer app, configure Facebook Login for Business, and register both callback URLs. Request review/business verification as Meta requires for `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `instagram_basic`, and `instagram_content_publish`. After authorization, the seller must intentionally choose from every permitted Facebook Page, or from every eligible professional Instagram account connected to those Pages; no first-page fallback is used. The service uses the Graph API version configured by `META_GRAPH_API_VERSION`, Page photo publishing for Facebook, and the Instagram media-container/content-publish flow. Preview images and the storefront must be publicly reachable over HTTPS for provider ingestion.

Meta Page discovery follows provider cursors without following credential-bearing `paging.next` URLs. Facebook and Instagram remain independent Creative Moth connections. Disconnecting either one retains shared Meta authorization while the other remains connected; provider-level Meta revocation is attempted only after the seller removes the last dependent Meta connection.

### Pinterest

Create a Pinterest developer app, register the Pinterest callback URL, and obtain approval for `boards:read`, `pins:read`, and `pins:write`. Sellers choose one of the authorized account's boards. Pins contain a title, description, public preview image, and Creative Moth product destination URL.
Pinterest board discovery follows provider bookmarks so the selection includes every authorized board; tokens remain in authorization headers.
Pinterest automatic posting cannot be enabled without a currently authorized board. Reconnecting Pinterest clears the prior board and disables automatic posting until the seller selects and saves a board authorized for the new connection.

## Security and operations

OAuth begins with a seller-authenticated CSRF-protected POST and callbacks require a one-time, ten-minute, cryptographically random state. Provider credentials are Sodium-encrypted at rest and are never rendered. Ownership and eligible product/image status are rechecked server-side. Provider errors are sanitized before persistence and seller notifications. Admins with promotions permissions may independently stop each integration at `/admin/social-publishing`.

Automatic posting uses one shared post-commit transition dispatcher across Admin moderation, IP-risk approval, seller save, and single/batch submission paths. It runs only when a product moves from a non-published state into `approved` or `published`; an ordinary edit or save of an already approved/published product does not dispatch. A unique product/platform automatic key provides a second duplicate-prevention layer. A failed automatic attempt is logged and not silently retried; the seller receives a notification and may explicitly retry. Manual posts and retries are always separate audit entries.

Creative Moth accepts up to 5,000 characters in the manual caption field. It prepares a separate payload for every selected platform and may shorten the entered caption to fit that provider: Facebook output is bounded safely within Facebook's supported 63,206-character provider limit (the current Creative Moth UI does not accept captions that long), Instagram output is limited to 2,200 characters, and Pinterest description output is limited to 500 characters. Facebook and Instagram receive exactly one appended Creative Moth product reference; Pinterest keeps the destination URL in the Pin link rather than duplicating it in the description.

Posting history is seller-scoped and remains visible if its related product is later deleted. A missing product is labeled `Deleted product #ID`. Retaining an attempt does not make a deleted or otherwise ineligible product retryable: retry is offered only while the current product still exists and has an eligible `approved` or `published` status.

A provider response is successful only when it contains the expected Facebook post/photo ID, Instagram container and published-media IDs, or Pinterest Pin ID. A successful HTTP response missing those identifiers is recorded as a failed attempt with sanitized response-error details.

## Testing

Provider end-to-end tests require real reviewed sandbox/live apps, eligible Page/professional-account/board access, and publicly reachable product images. Local fake-transport checks cover request construction, cursor/bookmark pagination, independent Meta revocation decisions, secret-free URLs, destination validation, OAuth state and centralized transition decisions, stale eligibility errors, caption limits, and URL de-duplication; they do not claim external delivery. The guarded database suite contains disposable transactional fixtures for ownership, persistence, retry linkage, notification, kill-switch, history, and automatic-idempotency behavior, but it remains skipped unless `APP_ENV=test`, `RUN_DISPOSABLE_DB_TESTS=1`, and a migrated disposable database are explicitly configured. The test checks `APP_ENV=test` before loading the application or opening any database connection.
