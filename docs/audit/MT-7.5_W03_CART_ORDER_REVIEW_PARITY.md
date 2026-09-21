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

1. Add one Laravel-owned Website commerce administration service guarded by `website.orders.manage`, with bounded order listing/detail, an explicit allowed status transition matrix, formula-safe CSV export and pending-review listing/moderation.
2. Add protected Admin JSON routes and a focused React surface using that service; do not create another identity realm or business backend.
3. Prove direct permission/outlet/ownership denials, immutable financial snapshots, idempotent/concurrent status behavior, review purchase qualification and approved-only public projection with focused backend tests.
4. Join the existing real Customer cart/checkout/order/review browser flow to the real Admin status/export/moderation UI, then reconcile the exact source route/file disposition before closing W03.

Authentic payment/provider delivery, old customer import and production orders remain excluded or separately external-unverified. No legacy source or production data is changed by this audit.
