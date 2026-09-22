# MT-7.5 W04 — cached hosted redirect disable/intent binding (23-Sep-2026)

Status: bounded synthetic W04 fail-closed continuation fix; not provider activation or W04 closure.

- Root cause: `OrderTransactions::initiate()` returned the stored provider URL/reference as soon as `gateway_order_reference` was present, before checking current provider availability or matching current merchant/environment to the original immutable payment intent. Disabling an external channel therefore did not block reuse of an already issued redirect.
- Added runtime `PaymentProviders::assertAvailable()` and exact stored merchant/mode comparison **before** returning a cached redirect or issuing a fresh initiation. COD remains noninitiable, disabled/unregistered providers remain unavailable, and the payment/receipt/reservation rows are unchanged by denied continuations.
- Test-first regression reproduced the bypass (1/1 FAILED, 2 assertions), then after the scoped service change passed (1/1 PASS, 16 assertions). Tests cover disable, merchant switch, environment switch, no receipt/sale or payment-row mutation, and restored-configuration idempotent continuation. No real provider URL is contacted.
- Scoped Pint PASS for both changed PHP files; joined W04/API/OrderPayment filtered regression **65/65 PASS, 1424 assertions**. The full monolithic CI and genuine provider contracts/refunds/settlement are not proven.
- Operational separation: this gate blocks *new/replayed hosted continuation* while disabled. The separate already-initiated callback-versus-emergency-disable/credential-retention contract remains OPEN under H-02; this test does not resolve an already paid customer's external reconciliation.
