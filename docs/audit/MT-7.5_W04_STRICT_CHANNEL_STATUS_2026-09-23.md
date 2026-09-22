# MT-7.5 W04 — effective COD policy and Admin channel status boolean parity (23-Sep-2026)

Status: bounded synthetic backend acceptance, W04 remains IN PROGRESS (15/27 DONE, 12 OPEN). This follows provider strict `enabled === true` at commit `614dbcd` and does not authorize a real merchant, gateway or transaction.

Root cause: `PaymentProviders::assertAvailable` rejects string/numeric truthy flags, while the Admin overview and `codEnabled()` still cast a configured string `"false"` to true, misleadingly reporting an external channel as enabled or COD as enabled even when checkout refused it. The overview now reports external `enabled` only for an exact boolean `true`. COD policy's default and published-snapshot/config conjunction both require an exact boolean `true`, consistent with the provider gate.

New synthetic `W04AdminPaymentOverviewTest` verifies malformed COD/JazzCash flags yield four disabled/unavailable channels and that explicitly re-enabling COD with a real boolean restores COD availability. Focused 3/3 PASS (17 assertions), full focused W04 suite 22/22 PASS (383 assertions), neighboring `OrderPaymentTransactionsTest` 16/16 PASS (121 assertions). Modified PHP passed scoped Pint, syntax and diff whitespace checks; isolated MySQL was initially stopped and must be stopped after tests.

Outstanding W04 payment/refund/owner/mode browser acceptance and H-02 official merchant contracts, credentials, authentic callback/refund/settlement remain OPEN. This is a parity fix, not evidence of provider readiness or family closure.