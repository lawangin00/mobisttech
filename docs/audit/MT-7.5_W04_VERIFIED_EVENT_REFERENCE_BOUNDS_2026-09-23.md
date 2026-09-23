# MT-7.5 W04 verified callback reference bounds (23-Sep-2026)

- Test-first found synthetic adapter-verified `event_id`, `transaction_reference` and `order_reference` strings longer than the 255-character payment/receipt schema boundary could reach the receipt path.
- Provider registry now rejects oversized references at the verified-event boundary before payment lookup or receipt write; no real gateway is enabled or accepted by this synthetic fixture.
- Focused registry/cross-provider tests: 4/4 PASS (47 assertions). Joined W04/OrderPayment/ApiContract: 85/85 PASS (1596 assertions). Scoped Pint PASS.
- W04 IN PROGRESS, MT-7.5 15/27 DONE / 12 OPEN; genuine provider contract, callback disable/reconciliation, credentials and settlement H-02 HOLD.
