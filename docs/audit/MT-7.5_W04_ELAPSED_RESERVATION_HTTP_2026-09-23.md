# MT-7.5 W04 — authenticated elapsed-reservation HTTP acceptance (23-Sep-2026)

Status: bounded local synthetic customer HTTP parity; not W04 closure.

- A signed-in Customer with CSRF created two synthetic JazzCash physical orders in isolated local MySQL. The first had never been externally initiated; the second already had an immutable hosted reference. Setting each payment-linked reservation deadline in the past without running the independent expiration scheduler made subsequent owned `POST /api/v1/payments/{payment}/initiate` return HTTP 409 (`api_409`), without issuing/reissuing a reference or writing a receipt/sale.
- Focused API test 1/1 PASS (15 assertions); scoped Pint PASS. The underlying expiry gate was separately implemented and test-first verified in `MT-7.5_W04_ELAPSED_RESERVATION_HOSTED_GATE_2026-09-23.md`.
- Real provider network activation and already-open hosted page revocation were not exercised. Genuine vendor, refund and settlement operations H-02 HOLD, W04 IN PROGRESS and MT-7.5 15/27 DONE / 12 OPEN.
