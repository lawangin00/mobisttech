# MT-7.5 W04 — concurrent COD draft-versus-publish acceptance (23-Sep-2026)

Status: bounded local synthetic MySQL PASS; W04 remains IN PROGRESS, 15/27 DONE and 12 OPEN. No authentic provider, external merchant or money operation was performed.

## Exact candidate and acceptance

- Extends `W04CodTwoWorkerRaceTest` and its existing independent PHP/Laravel worker to race a newer COD draft creation against publication of the prior latest draft through the actual `WebsitePaymentAdministration` and production MySQL-scoped revision lock.
- One new draft must succeed with a distinct next revision. Competing publication may succeed if it reaches the lock first, or return HTTP 409 when the new draft wins. In both orders, exactly one earlier published revision survives, the new revision remains draft, and version numbering is monotonic with no duplicate revision.
- Existing two-draft and same-revision two-publish races remain intact. The worker now returns its action label so independent completion order cannot be confused with a particular request.
- Test uses only `APP_ENV=testing`, the isolated `mobisttech_test` database and its guarded disposable synthetic Admin/revisions/audit cleanup; previously published business policies and protected legacy repos were not modified.

## Terminal local evidence

- New expanded two-worker race: 1/1 PASS, 36 assertions.
- Joined COD HTTP and race class: 4/4 PASS, 98 assertions; scoped Pint `--test` PASS for the two touched PHP files; PHP syntax PASS and exact isolated MySQL initially stopped state restored.
- This is genuine concurrent *application-service* execution across independently bootstrapped processes, not a claim of two separately authenticated parallel HTTP requests. HTTP/CSRF and prior protected Admin Chromium evidence remain separate; complete Website payment parity and external provider H-02 gates remain open.
