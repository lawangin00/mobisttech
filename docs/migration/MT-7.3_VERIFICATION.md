# MT-7.3 - Monorepo CI and reproducible build gates

Status: Complete. The exact clean-checkout application candidate passed every required remote CI gate; project closure was reconciled and pushed as documentation only.

## Authoritative scope

- Canonical private repository: `lawangin00/mobisttech`, branch `main`; protected legacy POS/Website repositories remain read-only.
- Canonical GitHub Actions workflow: `.github/workflows/ci.yml`, using disposable MySQL 8.4 `mobisttech_test` on port 13306 and Redis on port 16379.
- Required gates: reproducible dependency installation, Composer/Pint, backend typecheck/build/full regression, MySQL race/reset, Website typecheck/lint/production build, public budgets and tracked-secret scan, Chromium POS/Admin, Website checkout, Customer/Project, public content, per-mode performance, final schema and runtime-artifact checks.
- External production messaging/providers are disabled in CI; no production/customer data is used.

## CI-only defect and material remediation

- Prior clean-checkout candidate `ab3be43` failed at the Customer/Project Product Alerts POST (HTTP 409), while local Edge passed. The CI environment template explicitly set the unsubscribe-signing override to an empty string, preventing fallback to the generated CI `APP_KEY`.
- Candidate `5e29f3c4386ae78591421b298e116d07bf6aa469` removes only that CI override and adds the non-disclosing `tools/ci/verify-engagement-signing.php` bootstrap preflight immediately after ephemeral key generation. Production signing, authorization, CSRF, rate limits and acceptance assertions are unchanged.
- Focused local CustomerEngagement service regression passed 4/4 (37 assertions); signing preflight rejected an intentionally invalid test configuration and accepted the local testing-key fallback; PHP syntax and `git diff --check` passed.

## Clean-checkout acceptance

- Candidate workflow run: https://github.com/lawangin00/mobisttech/actions/runs/35435159329, job `105876462598`.
- Terminal run conclusion: FAILURE at Website public-content Chromium (3/4 passed). Exact upstream `/api/v1/website-profile` and `/api/v1/catalogue/products?limit=12` returned HTTP 429 during the long storefront test, causing its digital-only homepage heading assertion at line 99 to fail. The test combines independent desktop/mobile/mode-switch journeys after three other public tests on one loopback-IP public rate bucket.
- Earlier gates all PASS, including generated signing guard, full backend 253/253, MySQL race/reset 22/22, builds/budgets/secrets, POS/Admin 10/10, Website checkout 2/2, and Customer/Project 4/4. The former Product Alerts HTTP 409 is resolved. Website performance, final schema and no-runtime-artifact gates were skipped.
- Test-only correction: clear only the disposable Laravel testing cache at explicit independent storefront journey boundaries; production 120/min public rate limit, production code and acceptance assertions remain unchanged. Local focused Website public group PASS 4/4 (Edge, 117.01s), including synthetic fixture teardown. The corrected remote Chromium, performance, final schema and artifact gates subsequently PASSed on the accepted application candidate.
- Closure requires full remote green status and a final project identity, intended diff, ledger, remote and local-cleanliness reconciliation. Do not advance MT-7.4 during this point.

## Final-schema UTC contract failure and correction

- Exact candidate `7cfc3798c2f17f37c353a83083ed1bfd2e79e81e`, run `35435647602`, job `105877712686`: terminal FAILURE solely at the final `verify_mysql_schema.php api` check with `Strict SQL and UTC session are required.` All previous gates passed, including Website public Chromium 4/4 and the Website performance/request budgets; tracked runtime-artifact gate was skipped.
- The verifier explicitly requires `@@session.time_zone = '+00:00'` and `STRICT_TRANS_TABLES`. The Laravel MySQL configuration already enforced strict SQL but omitted the session timezone, leaving it dependent on the runner's MySQL server default. This was a real application-session invariant gap, not an acceptance threshold to relax.
- Corrective change pins the Laravel MySQL connection timezone to `+00:00` while retaining strict SQL and all prior production/test security and rate limits; focused `SharedSchemaTest` now asserts both session invariants. PHP syntax and diff whitespace gates passed. Focused local live-MySQL test could not connect because no isolated MySQL test server was listening on `127.0.0.1:13306`; it failed before assertions, so local DB acceptance is NOT claimed. The next clean-checkout remote run must independently prove the new regression and final schema/artifact gates.

## Final exact-candidate acceptance and closure

- Canonical project: `mobiST Tech`, UUID `282dba2f-a2d9-47e8-aa8d-e499fbe1706c`; private remote `lawangin00/mobisttech`, branch `main`. Protected legacy POS/Website repositories were not modified.
- Accepted application commit: `e056d4258c506f9c6746f8b5ba2fa192c62f52be`; clean-checkout GitHub Actions workflow run [35436344644](https://github.com/lawangin00/mobisttech/actions/runs/35436344644), job `105879531751`, terminal `completed/success` on 2026-09-19. All required job steps returned `success`, including isolated MySQL setup, generated-signing guard, Composer/PHP style, backend TypeScript/build/full regression with UTC/strict MySQL regression, race/reset, Website TypeScript/lint/production build, public budgets/secret scan, POS/Admin Chromium, Website checkout, Website Customer/Project, Website public content, performance/request budgets, final `verify_mysql_schema.php api`, no tracked runtime artifacts, and container cleanup.
- Previous isolated-local MySQL connection refusal was not counted as a local database PASS; the exact remote disposable MySQL full backend regression and final schema gate independently passed. No production data, live provider execution, production security/rate threshold relaxation, or source-project write is claimed.
- Project ledger and Q01/F01 parity register now reflect MT-7.3 Complete; MT-7.4 remains Pending and was not opened. Closure commit updates documentation only, following the successful application candidate; its push does not replace the accepted application SHA or its proven CI result.
