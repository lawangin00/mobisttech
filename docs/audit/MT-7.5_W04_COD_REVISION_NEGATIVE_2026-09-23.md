# MT-7.5 W04 — COD revision isolation and invalid-snapshot negative acceptance (23-Sep-2026)

Status: bounded target-only negative regression PASS; W04 remains IN PROGRESS and MT-7.5 remains 15/27 DONE, 12 OPEN. This is not concurrency acceptance or external-provider approval.

## Exact scope

- Added one independent synthetic MySQL transaction test in `backend/tests/Feature/W04CodPolicyAdministrationHttpTest.php`.
- A fully permissioned Admin cannot publish a draft belonging to a different configuration domain; HTTP 409 and its foreign draft remains unchanged.
- An otherwise latest COD draft with a string instead of a boolean `cod_enabled` cannot be published; HTTP 409 and its draft remains unchanged.
- Both denials leave the existing available COD channel unchanged. All synthetic rows are contained in the test's database transaction.
- Existing protected COD draft/publish/reenable behavior remains covered by the unchanged neighboring test.

## Local terminal evidence

- Focused new negative method: PHPUnit 1/1 PASS, 9 assertions.
- Complete W04 COD HTTP class: PHPUnit 2/2 PASS, 48 assertions.
- Modified PHP test: syntax PASS; scoped Laravel Pint `--test` PASS (1 file); `git diff --check` PASS.
- Project-only local isolated MySQL was initially stopped and started for these tests; restore its stopped state after verification.

## Still pending

- Independent multi-session concurrent draft/publish race acceptance is NOT established by serial HTTP assertions or the unique `(domain, version)` database constraint. Keep it pending, inspect locking strategy and use a bounded genuine concurrency test before claiming acceptance.
- W04 remaining source disposition and approved checkout/payment negatives remain open. Genuine JazzCash, Easypaisa and hosted-card merchant setup, callbacks, refunds and settlement remain EXTERNAL UNVERIFIED / H-02 HOLD. No production/customer/provider data or protected legacy repo was touched.
