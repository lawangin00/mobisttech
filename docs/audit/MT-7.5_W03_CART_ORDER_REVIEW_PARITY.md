# MT-7.5 W03 cart, order and review parity

Status: COMPLETE. W03 is closed; finite checklist is 15/27 complete and 12 open.

## Reused current evidence

- Customer account APIs and the Next.js account UI already preserve owned order history, signed historical-order access, guest-to-customer commerce state and purchase-qualified review submission.
- `OrderPaymentTransactionsTest`, `ApiContractTest`, `CustomerEngagementTest`, `customer.spec.ts`, `checkout.spec.ts` and the accepted fresh Website lifecycle already cover authoritative repricing/stock validation, idempotent checkout replay, payment/cancellation ownership and a pending customer review from an eligible purchased item.
- W01 supplies the accepted independent Admin/Customer realms and signed historical-order boundary. X01 supplies the accepted reservation/idempotency/race behavior. Those gates are reused and are not reopened.

## Verified current gap

- `website.orders.manage` exists and is assigned to the Customer Support role, but no current protected Admin route, controller/service projection or React management surface consumes that permission for Website order management.
- Customer review creation is purchase-qualified and public product output exposes only approved reviews, but no current Admin moderation route/UI exists to list pending reviews, approve/reject with an optional reply, or preserve moderator audit evidence.
- No current Website-order Admin CSV/status workflow exists. Existing POS operational-report CSV is a separate outlet-report contract and cannot substitute for Website order export.

## Bounded implementation order

1. ~~Add one Laravel-owned Website commerce administration service guarded by `website.orders.manage`, with bounded order listing/detail, an explicit allowed status transition matrix, formula-safe CSV export and pending-review listing/moderation.~~ Implemented and focused-test accepted.
2. Add a focused React surface using the new protected Admin JSON routes; do not create another identity realm or business backend.
3. Prove direct permission/outlet/ownership denials, immutable financial snapshots, idempotent/concurrent status behavior, review purchase qualification and approved-only public projection with focused backend tests.
4. Join the existing real Customer cart/checkout/order/review browser flow to the real Admin status/export/moderation UI, then reconcile the exact source route/file disposition before closing W03.

Authentic payment/provider delivery, old customer import and production orders remain excluded or separately external-unverified. No legacy source or production data is changed by this audit.

## Protected Admin commerce backend checkpoint — 21-Sep-2026

- `WebsiteCommerceAdministration` now consumes `website.orders.manage` for bounded Website-order listing/search/filter, strict optimistic `pending -> processing -> ready -> dispatched -> completed` fulfillment transitions, preserved financial/payment fields, formula-safe CSV, pending/approved/rejected review queues and one-way approve/reject moderation with optional Admin reply.
- Five unified Admin-realm JSON routes expose those operations. Status and moderation writes lock their row and append identity audit evidence; stale order versions return 409 and completed moderation cannot be rewritten.
- Focused service/route-registry verification plus the neighboring complete order transaction suite passed **16/16 (113 assertions)**; scoped PHP syntax/Pint and route registration passed.
- Attempt 1 added ordinary `actingAs` HTTP assertions, but the project's custom account-session middleware correctly returned 401 because no canonical `account_sessions` record existed. No service assertion failed. Materially corrected verification uses direct permissioned service behavior plus exact registered route inspection; full browser/API session evidence remains for the React acceptance slice.
- W03 remains IN PROGRESS for the Admin React surface and joined Customer-to-Admin browser workflow; finite count remains **14/27 DONE, 13 OPEN (51.85%)**.

## Protected Admin commerce UI checkpoint — 21-Sep-2026

- The existing Platform Administration shell now exposes a permission-filtered Website commerce entry and a focused React/Inertia workspace for bounded order search/status filtering, the next allowed fulfillment transition, formula-safe CSV download, review-state filtering and one-way approve/reject moderation with an optional reply.
- The page reuses the unified Admin realm and the five accepted Laravel JSON routes; it introduces no second identity realm or business backend. TypeScript typecheck, production Vite build (589 modules), focused W03 backend verification (1/1, 16 assertions), scoped Pint and diff whitespace checks passed.
- W03 remains IN PROGRESS because a dedicated synthetic browser fixture and real hosted Chromium acceptance must still join status advancement, CSV download and review moderation. Finite count remains **14/27 DONE, 13 OPEN (51.85%)**.

## Hosted browser candidate — 21-Sep-2026

- Added a guarded `mobisttech_test`-only W03 fixture/cleanup pair and one real Admin browser journey using the protected Full Access owner. The candidate advances `MT75-W03-ORDER` from pending to processing, downloads the generated CSV and approves its purchase-qualified pending review with an Admin reply.
- Fixture setup/cleanup completed against the canonical POS browser baseline; Playwright discovery found exactly 1 test, production build passed (589 modules), scoped Pint, TypeScript and the focused-CI allowlist self-test passed. The conventional local browser runner remains under its recorded orchestration LOOP_GUARD and was not rerun.
- W03 remains IN PROGRESS pending the exact hosted Chromium result and final route/source disposition. Counts remain **14/27 DONE, 13 OPEN (51.85%)**.
- Hosted attempt 1 (`35621674736`) passed every prerequisite and fixture cleanup but stopped at the page heading: HTTP 200 was returned while the canonical Inertia resolver omitted the new page entry. The bounded correction registers that page in `app.tsx`; no business/API/security contract changed.
- Hosted attempt 2 (`35622310316`) reached the hard public-bundle gate after both builds passed, then stopped because synchronous registration made the largest backend JS 616,781 bytes against the 614,400-byte limit. The bounded correction keeps resolver registration but dynamically imports this isolated page into its own chunk; no budget waiver is used.
- Corrected production build emitted a separate 8,250-byte commerce page chunk and passed the unchanged backend JS hard limit at 609,908 / 614,400 bytes plus all other public budgets.

## Source disposition and family closure — 21-Sep-2026

The immutable inventory assigns **29 Website source files and 16 routes** to W03. Every item is dispositioned below; shared-family items retain their independent W04/W06/W07/Q01 gates.

| Source inventory group | Count | Verified disposition |
|---|---:|---|
| Admin order/review controllers | 2 files | Reused rules and adapted them into `WebsiteCommerceAdministration`, five protected JSON routes and one permission-filtered React/Inertia workspace. The separate server-rendered detail endpoint is retired; bounded list rows, Customer-owned history and canonical document access provide the required views without a duplicate order authority. |
| Customer cart/order/review controllers | 3 files | Reused ownership, qualification and order-history rules through the existing Laravel REST services and Next Customer UI. Session cart persistence is retired in favor of server-authoritative quote/repricing and idempotent checkout. |
| Order/review models | 3 files | Consolidated into the shared Laravel order/item/review projections and relationship contracts. |
| Order/customer/review migrations | 3 files | Migrated into the shared `orders`, `order_items`, `product_reviews`, Customer identity and snapshot schema; no legacy synchronization backend remains. |
| Admin, cart, invoice, order and shared preview Blade views | 13 files | Replaced by the React Admin workspace, Next Customer/cart/order UI and canonical document renderer. Shared W06/W07 presentation responsibilities remain assigned to those families. |
| Source behavior/security/performance tests | 5 files | Replaced by current focused backend, Customer browser, X01 concurrency, signed-history and W03 hosted browser evidence; Q01 retains only final exact-candidate regression. |
| Admin order/review routes | 6 routes | Adapted to unified Admin identity: bounded orders, formula-safe CSV, forward-only status and immutable review moderation. |
| Cart/checkout routes | 6 routes | Adapted to REST/Next quote, quantity/remove and idempotent checkout contracts. |
| Review/order-history routes | 4 routes | Adapted to purchase-qualified review submission and Customer-owned signed history/invoice/payment access. |

Exact-source hosted run `35622831675` passed request validation, clean setup, PHP style, backend and Website production builds, unchanged public bundle budgets, secret scan, real Chromium W03 **1/1**, final schema/cleanup, clean tracked-artifact and container-stop gates. The browser advanced the guarded paid order from pending to processing, downloaded the generated CSV and approved its purchase-qualified pending review with an Admin reply.

W03 is **DONE**. Finite checklist: **15/27 DONE, 12 OPEN (55.56%)**. Next independent ready family: **17/W04 Website payments**.
