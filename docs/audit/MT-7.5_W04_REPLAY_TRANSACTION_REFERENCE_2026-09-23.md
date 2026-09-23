# MT-7.5 / W04 verified receipt replay transaction-reference binding — 23-Sep-2026

- Synthetic test-first regression: a correctly signed replay of the same event ID, original payload digest, payment and paid outcome was accepted when `transaction_reference` changed. The synthetic fixture computes its digest without this field; a valid signature by itself did not enforce immutable stored receipt identity.
- `OrderTransactions::applyReceipt` now also checks the persisted receipt transaction reference on event-ID replay and rejects mismatches without changing payment, receipt or sale. Original identical verified replays remain idempotent.
- Focused 2/2 PASS (29 assertions); joined `W04|OrderPayment|ApiContract` 84/84 PASS (1,593 assertions); scoped Pint PASS. Synthetic-only; no real vendor contract, credential rotation, refund or settlement acceptance claimed. W04 IN PROGRESS; MT-7.5 15/27 DONE, 12 OPEN; H-02 HOLD.
