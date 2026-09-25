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

## Clean-checkout receipt and Website public-content correction

Exact-source request run `36125516204` passed request validation, style, full backend, MySQL race/reset, builds, budgets, secret scan and pre-browser gates, then exposed a clean-checkout harness precondition: the ignored repo-root `.local/` directory does not exist in a fresh runner. The four guarded H01 seeders attempted database fixture creation before persisting their exact-owner receipts and failed on `file_put_contents(.../.local/...)`. The partially inserted H01 inventory fixture then explained the neighboring `32` -> `33 products` drift and the final outlet-delete FK cleanup failure. Source commit `65ef44adbe3aba4131477100b1b8f4ee44780d00` now creates the ignored `.local/` directory in `h01-fixture.ts` before invoking any guarded H01 seeder, without changing production behavior.

The follow-up exact-source request run `36126672244` proved that correction through the previously failing POS/Admin phase: request/style/full backend/race/build/budget/secret, fresh-owner, POS/Admin, Website checkout and Website customer/project gates all passed. It then failed later in Website public-content only at `dynamic-content.spec.ts` because the current Website media library exposes both the primary upload and replacement upload controls, while two historical W06 tests still used a strict broad `input[type="file"]` locator. This is the same stale selector class already corrected in `platform-administration.spec.ts`; source commit `fa0596af079f1dff6ccd1fb725e29465b4c08a16` binds both W06 uploads to the primary file input with `.first()`. The automatically triggered GitHub-only Website verification run `36126672245` succeeded on the same request SHA. No third full Q01 run is credited here; **27/Q01 remains OPEN [E] pending one materially changed exact-source full clean-checkout terminal PASS.**

## Final schema residual classification

Exact-source run `36132616755` passed request validation, Composer/Pint, backend TypeScript/build, full backend regression, MySQL race/reset, Website build/budgets/secret scan, fresh-owner browser, POS/Admin, Website checkout, Website customer/project, Website public-content and Website performance. It failed only at `Final schema and cleanup gate`: `Unexpected business rows remain in disposable schema`. The immediately preceding diagnostics showed exactly `CI_DISPOSABLE_RESIDUAL_TABLES=publication_versions:1` after public-content/performance.

The residual is the synthetic `cms.presentation` publication marker: `W06PresentationE2eCleanupSeeder` already proves every `website.presentation` revision, navigation item and owned `cms.presentation.*` setting belongs to the guarded W06 fixture and deletes those rows, but did not remove their shared `publication_versions` marker. The cleanup now deletes `cms.presentation` only after an additional fail-closed proof that no presentation revision, navigation item or `cms.presentation.*` setting from any actor remains. Scoped PHP syntax, Pint and Git whitespace checks PASS.

A fresh local joined Website public-content/performance/schema worker was started for focused verification, but the local run did not reach a terminal result within the bounded work window and was safely stopped; its owned DB lock was released only after PID ownership verification. It is **not** credited as PASS. `27/Q01` therefore remains OPEN pending one exact-source terminal full clean-checkout PASS.
