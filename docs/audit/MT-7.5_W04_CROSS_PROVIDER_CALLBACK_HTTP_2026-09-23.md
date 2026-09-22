# MT-7.5 W04 synthetic signed callback HTTP boundary — 23 September 2026

- Exercised the public callback HTTP transport with separately registered synthetic JazzCash/Easypaisa verifiers. A correctly signed Easypaisa receipt targeting a JazzCash reference is denied 404; the same payload sent to JazzCash fails signature verification 409.
- A validly signed JazzCash receipt with a mismatched amount is denied 409 with no receipt created. The correct paid callback succeeds 200; the same callback repeats idempotently 200; a changed signed event reusing the paid event ID is rejected 409, with one receipt and one sale.
- Focused cross-provider test 1/1 PASS (23 assertions), W04 25/25 PASS (435 assertions), OrderPaymentTransactions 17/17 PASS (130 assertions); scoped PHP Pint and diff whitespace PASS.
- Synthetic verifier is test-only. Authentic merchant contracts and real callbacks/refunds/settlement remain H-02 HOLD, W04 IN PROGRESS, MT-7.5 15/27 DONE / 12 OPEN.
