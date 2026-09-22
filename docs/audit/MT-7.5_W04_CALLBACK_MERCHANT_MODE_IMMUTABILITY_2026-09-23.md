# MT-7.5 W04 callback merchant/mode immutable-intent boundary — 23 September 2026

- Added synthetic HTTP callback negative checks after durable JazzCash payment initiation. Rotating the configured merchant or changing provider mode before a signed paid callback rejects the event HTTP 409 and writes no receipt; restoring original merchant/mode admits the original valid callback exactly once.
- Focused cross-provider test 1/1 PASS (29 assertions), W04 family 25/25 PASS (441 assertions), OrderPaymentTransactions 17/17 PASS (130 assertions); scoped Pint and diff check PASS.
- This verifies intent binding under synthetic signed adapter, not official processor credential rotation or live reconciliation. H-02 provider/refund/settlement HOLD unchanged; W04 IN PROGRESS at MT-7.5 15/27 DONE and 12 OPEN.
