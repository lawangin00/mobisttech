# MT-7.5 W04 — immutable refund replay and changed-evidence denial (23-Sep-2026)

Status: bounded synthetic refund idempotency acceptance; W04 remains IN PROGRESS (15/27 DONE, 12 OPEN), H-02 external refund settlement remains HOLD.

Expanded the accepted cross-order refund regression. After rejecting a different paid order's payment, a valid COD manual refund for the correct order/return/payment succeeds. Repeating the *same* operation key with the same amount and evidence digest returns the same refund identity/result and creates no second refund. Reusing that key with a different SHA-256 evidence digest is rejected without changing either order or producing a second refund. The first focused assertion compared JSON-decoded associative key insertion order rather than semantic equality; corrected it to `assertEquals` without changing production code or weakening any refund guard.

Focused 1/1 PASS, 9 assertions. Neighboring OrderPaymentTransactions 17/17 PASS, 130 assertions; W04 24/24 PASS, 404 assertions; scoped PHP Pint PASS. All records synthetic in isolated transaction. This does not verify genuine external provider refunds or bank settlement.