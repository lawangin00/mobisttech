# MT-7.5 W04 Easypaisa MA inquiry intent binding — 23-Sep-2026

- Preparatory sandbox-only REST transport now has `inquireForPayment(orderId, expectedAmount)`, which compares the provider inquiry against the original exact local amount and MA payment mode; mismatches reject without settling orders.
- Even a response with `transactionStatus=PAID` returns `verified_for_settlement=false`. No direct MA checkout flow, callback authentication, charge confirmation, refund, or production activation is implemented by this checkpoint.
- Focused synthetic transport suite: 6/6 PASS (21 assertions); scoped Pint PASS. Existing external providers remain OFF and Easypaisa transport remains unregistered in the hosted-checkout registry.
- W04 IN PROGRESS, MT-7.5 15/27 DONE / 12 OPEN; H-02 HOLD pending merchant-specific API choice, authenticated IPN/inquiry settlement evidence, full customer initiation UX, and sandbox credentials.