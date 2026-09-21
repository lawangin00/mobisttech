# MT-7.5 W03 cart, order and review parity

Status: IN PROGRESS. W03 is open; finite checklist remains 14/27 complete and 13 open.

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
