# MT-7.5 W04 browser-fixture isolation checkpoint

Status: IN PROGRESS. This record does not close W04, reopen completed W03, or certify authentic provider acceptance.

## Terminal evidence from previous clean hosted run

- Full CI run `35638577839` on `dd3c6064c9205f18b70bcada594225969a42009b` ended in FAILURE at `POS and Admin Playwright`: 16 failed, 18 passed, 1 skipped and one global teardown error. No Website checkout/customer/public acceptance or final cleanup ran after that browser failure.
- CI request validation, hosted PHP Pint, full backend regression (341 tests), MySQL race/reset tests, backend build, Website build and static security/budget gates all passed independently. Separate Website CI run `35638577782` succeeded. These facts do not imply browser or provider acceptance.
- Following test-only loopback public-throttle correction, the fresh Website publication/cart, authenticated COD customer, and out-of-stock reservation/cancellation journeys passed. The first observed browser failure became the guarded `Sitemap241E2eSeeder` baseline during the 241-item sitemap test. Seeder expects exactly three public POS listings, a specific three-product fresh outlet baseline, and no offline listing; the exact violated predicate remains unproven because its guard does not identify a failing clause.
- `mt75-fresh-next-smoke.spec.ts` creates the synthetic first outlet through `mt75-fresh-owner@example.invalid`, and `pos-first-outlet-fresh-owner.spec.ts` separately asserts that same owner still has no outlet and attempts to create the identical first outlet. Running these two independent creation journeys in one shared test database is a definite fixture collision, not evidence of defective production owner authorization.
- Other observed failures include admin navigation/login timeouts, POS master-data/claim seeders rejecting nonempty synthetic baselines and teardown residue. Their causal relationship to the fresh-owner collision has not yet been demonstrated, and the production identity limiter has not been changed.

## Scoped committed correction and current verification

- Workflow-only commit `c4b2e47f6d8bded49b83116178b302086ec219f2` adds a separate clean-database invocation for `pos-first-outlet-fresh-owner.spec.ts`, resets the disposable testing schema after its guarded teardown, and omits only that exact test title from the subsequent shared POS/Admin suite. Commit diff confirms production PHP, identity, checkout and payment services are unchanged. No destructive action targets the user's local machine or a production database.
- Exact-source full CI request `a5f2ac6d3381b6b27e354442f74c778923e2cfca` started hosted run `35640923317`. Its terminal result and first-outlet standalone outcome are pending, not PASS. Keep default-OFF JazzCash, Easypaisa and card and authentic provider HOLD.

## First genuinely pending actions

1. Verify run `35640923317` first-outlet standalone result and monolithic-browser failure boundary; if first-outlet teardown fails, isolate its own fixture cleanup before further full-suite runs.
2. Diagnose the exact guarded sitemap baseline predicate and remaining independent P02/claim/login fixture collisions using counts/isolated browser evidence; preserve actual security limits and test assertions.
3. Complete original 52 Website payment source-file / 16 source-route disposition and scoped W04 negative owner/reference/cross-provider/replay/refund/mode and failed-hosted-initiation recovery acceptance. Synthetic tests never satisfy authentic provider external HOLD.
