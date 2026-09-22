# MT-7.5 W04 paid-order cancellation HTTP acceptance — 23 September 2026

- Scope: add a synthetic authenticated Customer HTTP negative to the existing four-channel checkout/retry flow, **after** a signed synthetic JazzCash paid event settled the retried order. No production payment or external provider code changed.
- Paid order cancellation using a fresh idempotency key returns HTTP 409 (`api_409`). Order status, payment status, collected payment, sale count and immutable receipt count remain unchanged. Existing owner/foreign-owner and CSRF checks remain in the same test.
- Focused payment checkout HTTP: 1/1 PASS (55 assertions). Whole ApiContractTest: 19/19 PASS (769 assertions). Joined W04: 24/24 PASS (407 assertions). Scoped PHP Pint and git diff check PASS.
- This is synthetic provider evidence, not authentic wallet/card, external refund or settlement acceptance. W04 IN PROGRESS, 15/27 DONE and 12 OPEN; H-02 HOLD unchanged.
