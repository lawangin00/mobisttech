# MT-7.5 W04 customer payment retry boundaries — 23 September 2026

- An authenticated Customer's failed external payment retry without valid CSRF is HTTP 419 with no additional payment attempt; the same owner with CSRF can retry.
- After a synthetic signed provider receipt successfully collects the retried order, another authenticated retry returns HTTP 409 with unchanged payment attempt count. This distinguishes a rejected duplicate charge from a genuine paid order.
- Focused checkout HTTP 1/1 PASS (67 assertions). Full API 20/20 PASS (798 assertions), W04 25/25 PASS (424 assertions), OrderPaymentTransactions 17/17 PASS (130 assertions); scoped PHP Pint/diff check PASS.
- Provider events are synthetic, genuine merchant and external settlement/refunds remain H-02 HOLD. W04 IN PROGRESS; MT-7.5 15/27 DONE and 12 OPEN.
