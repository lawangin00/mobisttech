# MT-7.5 W04 unknown-to-paid receipt settlement — 23 September 2026

- A synthetic signed JazzCash `unknown` event preserves the original hold with reconciliation required. An attempted customer retry is denied before creating a second charge. A later distinct, valid paid event for the same payment intent settles the order once, releases the hold and creates one sale; exact paid event replay is idempotent with two immutable receipts (unknown then paid).
- Focused synthetic OrderPaymentTransactions test 1/1 PASS (14 assertions); complete class 17/17 PASS (140 assertions); W04 suite 25/25 PASS (441 assertions). Scoped Pint and diff check PASS.
- This is synthetic callback/reconciliation contract evidence only, not a live provider or automatic reconciliation approval. W04 IN PROGRESS, MT-7.5 15/27 DONE / 12 OPEN; H-02 authentic processor/refund/settlement HOLD unchanged.
