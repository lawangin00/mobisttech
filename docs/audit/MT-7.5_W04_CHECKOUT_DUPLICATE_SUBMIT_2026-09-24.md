# MT-7.5 / W04 — checkout same-tick double-submit browser acceptance (24-Sep-2026 PKT)

Status: bounded Website checkout regression corrected; MT-7.5 / W04 remains IN PROGRESS (15/27 DONE, 12 OPEN). Authentic external providers, credential lifecycle, signed callbacks, refunds and settlement remain H-02 HOLD.

## Reproduced failure and attempt record

Attempt 1: extended the existing synthetic hosted-initiation failure-to-owned-order browser case with two same-tick Place order DOM clicks while the browser-only order response was delayed 350 ms. Before the fix, the focused Playwright case failed because **two** `POST /api/customer/orders` requests were issued (expected one). This was a duplicate browser request despite an existing idempotency key; the fixture did not create two real backend orders and no live payment or merchant system was contacted. Failure signature: checkout submit's React `submitting` state had not synchronously updated before the second click.

Attempt 2 (material change): `website/src/components/customer-checkout-form.tsx` now takes an immediate synchronous `submittingRef` lock before the request, retaining the existing disabled/busy UI and releasing the lock in `finally`. No gateway setting, provider adapter, order/payment backend transaction, idempotency-key contract or approved tender changed. The existing browser test still checks that a gateway initiation failure keeps exactly one created order recoverable through its owned-order link, and that two subsequent same-tick Continue payment clicks issue only one continuation request. No paid-provider acceptance is inferred.

## Scoped verification

- Before correction: focused browser 1/1 FAIL; duplicate order POST count 2, expected 1; isolated test teardown completed.
- After correction: focused browser 1/1 PASS; entire adjacent checkout browser suite 6/6 PASS with disposable fixture teardown (COD, failed external initiation and recovery, unsafe redirect, all channels unavailable, cancelled payment, digital-only mode).
- Backend TypeScript typecheck PASS; Website TypeScript typecheck PASS; optimized Next.js Website production build PASS; `git diff --check` PASS.
- Browser-only stubbed hosted-provider interactions establish UI single-flight behavior, not authentic gateway authorization, refund, settlement, provider disable policy or production/customer-data acceptance. Keep the W04 and H-02 gates open.
