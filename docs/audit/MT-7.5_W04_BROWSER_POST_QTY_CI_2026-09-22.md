# MT-7.5 W04 — post-quantity-fix hosted browser checkpoint (22 September 2026)

Status: W04 IN PROGRESS. This is a factual diagnostic checkpoint, not W04 closure or production/payment-provider acceptance.

## Exact source and terminal CI result

- Test-only stock-card assertion correction: `1ffe59f2036382ef22c1b7573eff19ac8fb91f3d`; exact-source full CI request: `dbec2d9f6b3974440e0373451c385fc8aa91e735`; full hosted run: `35644067503`, terminal FAILURE at POS and Admin Playwright.
- Isolated first-outlet browser: **1/1 PASS**; its cleanup seeders returned but diagnostic reported `CI_DISPOSABLE_RESIDUAL_TABLES=publication_versions:3`. The immediately following dedicated disposable `migrate:fresh` PASSED before the shared browser run. Do not call the isolated fixture residue-free.
- Composer/PHP Pint (276 files), backend regression (341 tests / 10,059 assertions), MySQL concurrency/reset (23 tests / 1,684 assertions), backend and Website typechecks/builds, public budget and tracked-secret checks PASSED. Independent Website-only static workflow `35644067500` PASSED.
- The shared POS/Admin browser suite ended **15 failed / 18 passed / 1 skipped**, plus one global teardown error outside the test count. W01/P02/W03 family-specific standalone browser gates were intentionally SKIPPED by this W04 CI request. Website checkout/customer/public browser steps and full final schema/cleanup were SKIPPED after shared browser failure; no complete W04 browser acceptance is established.

## First observed independent failure boundaries

- The first shared failure is `mt75-fresh-next-smoke.spec.ts` sitemap-241 journey: `Sitemap241E2eSeeder` aborted its baseline guard. The test's seed helper captures stderr with `stdio: 'pipe'`, obscuring the exact failed predicate in hosted logs. The preceding fresh publication/cart, owned COD checkout/cancellation and stock-hold release browser tests passed in the same run. The sitemap seeder requires a specific three-product/three-public-listing outlet baseline, but the actual offending predicate and fixture lineage are not yet proven.
- `PosMasterDataLinkedE2eSeeder` aborts with the explicit guard `Scoped test expects an empty accessory category baseline.` after earlier browser journeys; this is a fixture baseline conflict, not grounds to remove the guard. The master-data lifecycle test cannot find its workspace and other master-data tests encounter unresolved login/navigation state.
- Several `pos-shell`, POS stock-control, preference-memory, P02 variants and Website commerce-admin tests time out waiting for `/internal/admin/pos` after login. Their actual post-login URL/status/response must be established from isolated traces before attributing these failures to authentication or loosening real security rules.
- `PosWarrantyIntakeE2eSeeder` aborts with an unspecified HttpException. `PosShellE2eCleanupSeeder` also aborts during global teardown; final cleanup gate did not run. Diagnose both predicates against the shared fixture state without touching production data.

## First pending bounded remediation

1. Expose the sitemap seed guard's exact failed predicate in synthetic CI diagnostics without weakening baseline assertions or leaking sensitive values; isolate the sitemap journey from earlier persistent fixtures if the exact state proves collision.
2. Identify which preceding synthetic test creates the accessory baseline, inspect the real post-login URL/status and ensure seeded owner/outlet state matches each test's preconditions; isolate conflicting fixtures or correct narrowly stale test navigation, preserving permission checks.
3. Verify teardown residue/cleanup on a disposable schema, then run the focused failing browsers before requesting another necessary full CI. Continue the original 52 Website source files / 16 routes payment disposition and negative payment/recovery acceptance; external JazzCash, Easypaisa and hosted-card provider verification remains HOLD and default OFF.
