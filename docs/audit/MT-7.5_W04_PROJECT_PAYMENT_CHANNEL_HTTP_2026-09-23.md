# MT-7.5 W04 project payment channel HTTP negative parity — 23 September 2026

- Authenticated Customer project-payment channel list in published digital_only Website mode advertises exactly JazzCash, Easypaisa and hosted card, all default unavailable. Anonymous list read rejects access; COD is excluded.
- Project-milestone initiation with COD yields HTTP 422, and each unavailable external channel yields HTTP 409. No order, payment or idempotency row is created. All fixtures and provider calls are synthetic; this is not an authentic provider integration.
- Focused HTTP 1/1 PASS (17 assertions), full API 20/20 PASS (792 assertions), W04 family 25/25 PASS (424 assertions); scoped PHP Pint and diff check PASS.
- W04 IN PROGRESS, MT-7.5 15/27 DONE and 12 OPEN. H-02 authentic merchant/vendor/callback/refund/settlement HOLD unchanged.
