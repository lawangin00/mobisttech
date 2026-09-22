# MT-7.5 W04 — authenticated parallel COD HTTP-kernel race acceptance (23-Sep-2026)

Status: bounded target-only HTTP-kernel race PASS. W04 remains IN PROGRESS; finite family checklist stays 15/27 DONE, 12 OPEN. Not genuine merchant, provider, live network-server or production acceptance.

## Scope

- New `backend/tests/Feature/W04CodHttpKernelRaceTest.php` creates two separate, permissioned synthetic Admins/outlets and authenticates both through the existing Admin CSRF-cookie, login and outlet-selection HTTP routes, using isolated database-backed sessions.
- `backend/tests/Support/W04CodHttpRaceWorker.php` boots two independent PHP/Laravel HTTP kernels, dispatches the actual protected COD draft/publish routes with each actor's separate session cookie and CSRF token, and reports actual HTTP response status and bounded nonsecret revision data. Neither authorization nor CSRF middleware is bypassed.
- The parent gates both independent workers on the production MySQL COD revision lock. Concurrent HTTP draft calls must both return 201 with unique versions 1/2; simultaneous attempts to publish the same latest revision must yield exactly one 200 and one 409 with exactly one published row.
- Refuses a nonempty target COD policy baseline; accepts only APP_ENV=testing and database `mobisttech_test`. Temporary session-bearing worker job files and ready markers are removed on success/failure. Test-owned revisions and audits are removed before either synthetic Admin because a draft owner and publisher can be different. Account sessions, session rows, assignments and synthetic outlets are cleaned only by their exact test-created actor/outlet IDs.

## Verification and corrected fixture issue

- First focused race PASS 1/1 (27 assertions). Joined run then exposed test-only fixture cleanup ordering: the publisher belonged to a different synthetic Admin than the draft creator; deletion of the publisher first was correctly rejected by a foreign-key constraint, leaving one synthetic COD row and causing the next test's baseline guard to fail. Production COD behavior did not fail.
- Exact residual record and both affected synthetic accounts/outlets were independently identified; cleanup was limited to those exact disposable-database IDs. The fixture cleanup now deletes all revisions created by either test actor before deleting either actor. No FK constraints, application authorization, timeout or production COD logic were weakened.
- Reconciled joined `W04CodHttpKernelRaceTest`, `W04CodPolicyAdministrationHttpTest` and `W04CodTwoWorkerRaceTest`: **5/5 PASS, 125 assertions**. Both new PHP files passed syntax and scoped Pint; no COD revision or synthetic HTTP-race Admin remained in the isolated test database after the joined run.

## Evidence boundary / next action

This is parallel independent PHP HTTP-kernel dispatch with genuine Admin session/CSRF middleware and shared isolated MySQL, **not** a two-socket PHP web-server/load-test result. Keep W04 open for any required real network-worker transport validation, remaining payment/mode/owner/refund parity and actual external merchant contract/credential authorization (H-02 HOLD). External adapters remained default OFF; no real customer/payment/provider data or protected legacy repositories touched.
