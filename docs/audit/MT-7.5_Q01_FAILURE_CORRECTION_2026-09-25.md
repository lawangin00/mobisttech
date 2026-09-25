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
