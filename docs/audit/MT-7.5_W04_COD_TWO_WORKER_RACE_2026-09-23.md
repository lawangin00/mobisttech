# MT-7.5 W04 — independent two-application-worker COD revision race (23-Sep-2026)

Status: bounded local, disposable-MySQL race acceptance PASS; W04 remains IN PROGRESS, 15/27 DONE and 12 OPEN. Does not establish external provider, merchant, real money or complete family acceptance.

## Scope and safety

- `backend/tests/Feature/W04CodTwoWorkerRaceTest.php` launches two independent PHP/Laravel application workers through `Symfony Process`; `backend/tests/Support/W04CodRaceWorker.php` boots each application separately and invokes the real `WebsitePaymentAdministration` service.
- The parent holds the production COD-scoped MySQL named lock until both workers reach the start barrier, then releases it. Independent database connections contend for the unchanged production lock; no simulated/mock lock or serial in-process method call is used.
- Only `APP_ENV=testing` and database `mobisttech_test` are accepted; the test refuses an existing COD revision baseline rather than overwriting a pre-existing policy. It creates a UUID synthetic Admin with only scoped permissions, records test-only revisions and identity audits, and deletes its identified rows/account in a finally block.
- Two competing drafts must both succeed with distinct versions 1/2 and exactly two revisions; two competing publishes of the same latest draft must yield exactly one successful publication and one HTTP 409 with exactly one published revision. Test verifies zero COD revisions after identified fixture cleanup.

## Local evidence

- Attempt 1 failed on test-only nondeterministic worker completion order `[2,1]` versus positional expectation `[1,2]`; the actual two drafts both succeeded. Correction sorts observed versions and chooses the highest-version draft rather than assuming process completion order. No production behavior or database lock was changed.
- Corrected independent race: PHPUnit 1/1 PASS, 22 assertions; joined existing COD HTTP + two-worker regression 4/4 PASS, 84 assertions. Both new PHP files pass syntax and scoped Pint; diff whitespace check passes.

## Remaining boundary

- Test verifies two independent Laravel application processes invoking the service on real isolated MySQL, not two full authenticated HTTP request workers or live merchant transactions. HTTP authorization, CSRF and role boundaries remain covered by separate W04 COD tests and accepted browser evidence. Complete W04 payment-parity review, external provider identity/contract approval and H-02 remain pending.
