# MT-7.5 W04 — Hosted continuation reference binding (23-Sep-2026)

- Test-first reproduced that a persisted cached hosted response could carry a different `reference` from the bound payment's `gateway_order_reference`, and `initiate()` returned its URL unchallenged.
- `safeContinuation` now checks the response reference against the bound payment reference for cached and fresh responses, rejecting an inconsistent continuation without mutating receipts or sales. The original persisted reference remains available for late signed reconciliation.
- Synthetic regression before fix FAIL 1/1; after fix focused 3/3 PASS (18 assertions); joined W04/OrderPayment/ApiContract 82/82 PASS (1583 assertions), scoped Pint PASS. No authentic provider approval/activation or full unfiltered backend PASS claimed.
- W04 IN PROGRESS; MT-7.5 15/27 DONE, 12 OPEN. Genuine provider contract, merchant credentials, signed vendor callbacks, refund/settlement remain H-02 HOLD.
