# MT-7.5 W04 — invalid external gateway mode cannot mutate checkout or milestone state (23-Sep-2026)

Status: bounded synthetic backend acceptance. W04 stays IN PROGRESS, checklist 15/27 DONE and 12 OPEN; no merchant or provider activation.

Following the strict gateway mode allowlist in `PaymentProviders`, a new independent `W04UnavailableChannelTransactionGateTest` registers an explicitly synthetic adapter for each JazzCash, Easypaisa and hosted card, configures boolean enabled and a nonblank synthetic merchant, but supplies the unsupported `production` mode. This isolates mode rejection from the already-proven unregistered-adapter failure.

For all three gateways, the channel remains unavailable; both physical Website checkout and Customer project-milestone payment throw `provider_unavailable` *before* any order, payment, reservation or idempotency row is created. No synthetic provider initiation occurs. Focused `W04UnavailableChannelTransactionGateTest` 2/2 PASS, 21 assertions; joined W04 23/23 PASS, 395 assertions; neighboring `OrderPaymentTransactionsTest` 16/16 PASS, 121 assertions. Modified PHP syntax and scoped Pint PASS; diff whitespace check PASS. Isolated project-owned MySQL initially stopped and is restored to stopped after verification.

This proves malformed configuration fail-closed in two distinct business entrypoints; it does not prove full Customer browser/operating-mode parity, provider sandbox or refund settlement. H-02 remains EXTERNAL UNVERIFIED.