# MT-7.5 W04 paid-order historical-access mode parity — 23 September 2026

- Synthetic signed external receipt settles an owned Customer order before a Website mode change from hybrid to digital_only. Paid order remains accessible through the authenticated historical order API, while new cancellation remains HTTP 409 with no sale/receipt or order/payment state mutation.
- Focused checkout API 1/1 PASS (61 assertions), full ApiContractTest 19/19 PASS (775 assertions), joined W04 24/24 PASS (407 assertions). Scoped PHP Pint and diff whitespace PASS.
- The mode toggle is isolated transactional test data; this is not an authentic external provider, external refund, payment reconciliation or full Website browser acceptance. W04 IN PROGRESS, 15/27 DONE / 12 OPEN, H-02 HOLD unchanged.
