# MT-7.3 - Monorepo CI and reproducible build gates

Status: In Progress until the exact clean-checkout GitHub Actions candidate has a terminal full PASS and the ledger/remote closure is reconciled.

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
- Test-only correction: clear only the disposable Laravel testing cache at explicit independent storefront journey boundaries; production 120/min public rate limit, production code and acceptance assertions remain unchanged. Local focused Website public group PASS 4/4 (Edge, 117.01s), including synthetic fixture teardown. The corrected exact remote Chromium CI candidate and later performance/schema/artifact gates must independently PASS before closure.
- Closure requires full remote green status and a final project identity, intended diff, ledger, remote and local-cleanliness reconciliation. Do not advance MT-7.4 during this point.
