# MT-7.5 W04 — Unrecognized external provider mode: authenticated Website HTTP gate (23-Sep-2026 PKT)

Scope: synthetic customer acceptance only, without contacting any wallet/card network or activating real merchants.

`ApiContractTest::test_w04_external_unrecognized_mode_blocks_authenticated_checkout_and_milestone_http` registers synthetic adapters for all three approved external channels, configures exact boolean enabled and nonblank synthetic merchant but unrecognized `production` mode, then checks authenticated Website checkout and project channel APIs. Both expose the immutable channel ordering and report all external modes unavailable. Each of the three channels fails both POST checkout and POST milestone with HTTP 409 and without orders/payments/reservations/idempotency mutations. COD remains available. This isolates the external mode allowlist from absent-adapter or absent-merchant failures at the genuine HTTP boundary.

Verification: focused 1/1 PASS (17 assertions); `ApiContractTest.php` 22/22 PASS (826 assertions); W04 filter 27/27 PASS (473 assertions); scoped Pint and git diff --check PASS. External provider contracts, vendor-signed payloads, authentic refunds/settlement and merchant credentials remain H-02 HOLD. W04 IN PROGRESS; 15/27 DONE / 12 OPEN.
