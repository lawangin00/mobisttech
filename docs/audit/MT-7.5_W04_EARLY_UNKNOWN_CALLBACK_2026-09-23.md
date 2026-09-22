# MT-7.5 W04 — unknown verified result during hosted initiation (23-Sep-2026)

Status: additional synthetic in-flight event acceptance; H-02 genuine providers remain HOLD and W04 remains IN PROGRESS.

- A signed synthetic provider `unknown` callback arrives while `initiate()` is in flight and echoes the immutable local payment ID. The result keeps a single receipt, a pending order, a held stock reservation and zero sale. The initiation return persists its provider reference without issuing a stale hosted redirect or starting a second charge.
- Focused in-flight matrix 5/5 PASS (49 assertions) and scoped Pint/PHP syntax PASS. This does not establish vendor-specific early-callback correlation, merchant rotation resolution, real refund or settlement.
- Operational OPEN: an unknown result must enter provider-supported reconciliation, not customer retry. Provider-generated-only early references cannot be mapped before reference persistence without a documented provider correlation/retry contract.
