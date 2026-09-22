# MT-7.5 W04 — authenticated cancellation/hosted-initiation HTTP parity (23-Sep-2026)

Status: synthetic Customer HTTP boundary evidence only; W04 remains IN PROGRESS.

- After a real authenticated Customer login with session and CSRF, two synthetic JazzCash commerce orders were created against isolated local MySQL; one was cancelled before external initiation and one after an initial hosted reference. Each subsequent owned `POST /api/v1/payments/{payment}/initiate` was rejected HTTP 409 with `api_409` without changing the original reference or recording any receipt/sale.
- Focused API test 1/1 PASS (17 assertions), scoped Pint PASS. This independently checks the HTTP owner/CSRF/customer controller boundary of the earlier `MT-7.5_W04_CANCELLED_EXTERNAL_INITIATION_GATE_2026-09-23.md` service and browser evidence.
- No genuine JazzCash adapter, real wallet transaction, live gateway callback, void/refund or settlement was used. External H-02 HOLD and 15/27 DONE, 12 OPEN stage count remain unchanged.
