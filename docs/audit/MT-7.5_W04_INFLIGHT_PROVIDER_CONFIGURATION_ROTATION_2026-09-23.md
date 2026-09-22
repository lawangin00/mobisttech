# MT-7.5 W04 — provider configuration rotated during hosted initiation (23-Sep-2026)

Status: bounded synthetic fail-closed continuation checkpoint; external providers remain default-OFF and W04 remains OPEN.

- A deployment channel can be disabled or its merchant/environment rotated after the local preflight but before a synthetic provider returns. The old implementation would return the new hosted redirect because it checked provider availability only before the network call.
- Recheck current provider availability, merchant and environment after the network returns and after committing the returned provider reference for future signed reconciliation. Do not issue a redirect when current configuration mismatches the immutable original payment intent.
- Test-first disabled-provider scenario failed before fix, then three deterministic mid-network negative cases passed: disabled channel, rotated merchant and rotated mode. Reference persists for each, no receipt/sale is forged, and restoring original configuration permits idempotent cached continuation.
- Focused 1/1 PASS (15 assertions); joined W04/OrderPayment/API 73/73 PASS (1511 assertions); scoped Pint PASS. No real provider operation, external callback-after-disable approval or settlement proved.
