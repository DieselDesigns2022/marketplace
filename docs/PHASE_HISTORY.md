# Phase History

## Original MVP — completed 2026-06-22

Completed:

- Repository initialized.
- Baseline MVC-style marketplace skeleton created.
- Core router, database, helper, bootstrap, layout, controllers, and views added.
- Baseline schema created.
- Public, auth, buyer, seller, and admin foundations established.

Tested:

- Baseline route and page rendering sufficient for phase continuation.

Lessons learned:

- Keep the front controller and route list as the authoritative map of implemented pages.

## Phase 1 — Designer Applications

Completed:

- Seller application submission.
- Admin application review.
- Approval/denial states.
- Designer record creation from approved applications.

Tested:

- Application form submission.
- Admin review decisions.
- Seller access after approval.

Lessons learned:

- Application status and seller role transitions need careful manual testing.

## Phase 2 — Storefront Management

Completed:

- Seller storefront settings.
- Public storefront pages.
- Designer avatar/banner/profile metadata.
- Store follows.
- Admin designer management.

Tested:

- Storefront editing and public display.
- Slug behavior.
- Follow/unfollow behavior.

Lessons learned:

- Store slugs must be protected from conflicts and public display regressions.

## Phase 3 — Product Management

Completed:

- Seller product creation/editing.
- Product preview images.
- Protected product file metadata.
- Product tags/categories/licenses/AI disclosure/SEO fields.
- Product moderation states.
- Admin product detail review.

Tested:

- Product form saving.
- Product checkbox persistence.
- Admin product moderation.

Lessons learned:

- Product forms have many status-affecting fields and require regression tests after edits.

## Phase 4 — Shopping Cart, Orders & Downloads / Marketplace Polish

Completed:

- Cart and checkout workflow.
- Mock order creation.
- Order items, seller earnings, and platform commissions.
- Buyer purchases/order detail/downloads.
- Protected download route.
- Admin order pages.
- Marketplace polish and bug fixes.

Tested:

- Cart add/remove/update.
- Checkout.
- Buyer order and download access.
- Admin order review.

Lessons learned:

- Protected downloads must be tested as both authorized and unauthorized users.

## Phase 4.5 — Codebase Standardization

Completed:

- Reformatted compressed code for readability.
- Preserved behavior as the intent.
- Fixed formatting regressions.
- Restored public product previews and sell page.

Tested:

- Formatting checks and regression fixes.
- Public product previews.
- Sell page.

Lessons learned:

- Formatting-only work can still cause regressions; manual smoke tests are required.
- Codex branch/PR conflicts must be handled one step at a time.
- Verify GitHub branch and PR target before merge.

## Phase 5 — Project Documentation

Completed:

- Added root documentation and `docs/` documentation set.
- Documented current implementation versus future/planned blueprint items.

Tested:

- Documentation-only checks should confirm no application code changed.

Lessons learned:

- Documentation must be updated with each future phase.
