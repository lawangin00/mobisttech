# MT-7.5 27/Q01 Failure Classification and Correction

Date: 25-Sep-2026 PKT  
Gate: `27/Q01`  
State: **IN PROGRESS — corrected candidate awaiting exact-source full clean-checkout rerun**

## Hosted Q01 attempt

GitHub Actions run `36101561600` was source-bound to request commit `365fea2e1ba14e61eaaf3050b79095fd0415084b` and candidate `6bd7e05910b9558577132e63d41d07f30e1723c4`.

- request validation: PASS;
- isolated MySQL/bootstrap/dependency installation: PASS;
- first failing gate: Composer/PHP style;
- exact failure: Pint `ordered_imports` in `backend/routes/identity.php`;
- all later backend/build/browser/performance/schema gates were skipped by CI after the style failure.

Correction is semantic-neutral: only import order/line-ending normalization in that route file. Scoped Pint on the corrected route plus the affected focused test file: **2 files PASS**.

## Local full-backend attempt

The same Q01 local full-backend worker completed **440/441 tests PASS (11,705 assertions)** and failed one test in `PlatformAdministrationInterfaceTest`:

- exact SQL failure: duplicate unique outlet code `527`;
- helper generated random 3-digit outlet codes without checking the already-created outlets in the same transactional test;
- this is a synthetic fixture collision, not a production outlet allocation failure.

Correction: when a fixture-specific code is not supplied, the test helper now retries until the randomly generated code is unused in the current database transaction. Production outlet allocation code is untouched.

Focused corrected acceptance on a fresh disposable MySQL schema:
- `PlatformAdministrationInterfaceTest`: **6/6 PASS (240 assertions)**;
- scoped Pint: **PASS**;
- PHP syntax / git whitespace: PASS.

## LOOP_GUARD / rerun basis

This is not an unchanged Q01 retry. The next full hosted run is materially different because it contains:
1. the exact hosted Pint import-order correction;
2. the exact locally observed random-fixture collision correction.

No production business behavior was weakened or bypassed. A new exact-source Q01 milestone request is justified once these corrections are committed/pushed. If that run produces a different failure signature, classify it before any further retry.

**27/Q01 remains OPEN [E].**
## Request-orchestration correction

The first materially changed request commit `e7dc4f5c763d013509966df5a71ba9d5610958ed` did **not** reach clean checkout. Run `36121109557` failed only in request validation because Windows PowerShell 5.1 wrote the JSON with a UTF-8 BOM; `ci-mode-gate.py` intentionally reads strict UTF-8 and rejected it with `JSONDecodeError: Unexpected UTF-8 BOM`. This is an orchestration/file-encoding failure, not a candidate test failure.

The next request must be a new request-only child of a non-request source commit, must point `source_commit` exactly to that parent, and must write JSON as UTF-8 **without BOM**. Do not modify the failed request in-place as a new trigger because the gate requires request `source_commit == HEAD^`.
## Browser-fixture and stale-mock correction

Hosted run `36121283422` passed request/bootstrap, style, backend/build/race/budget/secret and preliminary browser gates, then POS/Admin Playwright ended 42 PASS / 5 FAIL / 1 skipped. Four H01 failures were a single harness omission: their exact-owned guarded fixture seeders were never invoked by the consolidated Playwright lifecycle. Each H01 spec now owns its seeder in `beforeAll` and guarded cleanup in `afterAll` through `h01-fixture.ts`; focused combined execution proved all eight H01 tests PASS and exact disposable residue `none`.

The fifth failure, `platform-administration.spec.ts`, was also stale fixture/test contract rather than production behavior: trace proved `/internal/admin/platform` and mocked `/platform/data` both HTTP 200, while React crashed because historical mock data omitted current `website_credentials`; after adding the empty array the test advanced to a strict-mode media upload selector that now matches both upload and replacement inputs. The selector was bound to the primary upload (`.first()`), matching current accepted Website media tests. Final isolated Platform Administration browser acceptance: **1/1 PASS (1.3m)** with `CI_DISPOSABLE_RESIDUAL_TABLES=none`. Backend TypeScript typecheck and diff check PASS.

These are materially different browser-harness corrections; no production route, auth, inventory, policy, payment or business behavior was weakened. **27/Q01 remains OPEN pending one new exact-source full clean-checkout run.**
