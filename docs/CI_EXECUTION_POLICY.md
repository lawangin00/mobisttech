# mobiST Tech — enforced CI execution behavior

Canonical authority: `lawangin00/references/UNIVERSAL_EXECUTION_MODE.json`, `UNIVERSAL_EXECUTION_MODE_POLICY.md` v1.8 and `UNIVERSAL_CI_REQUEST_DISPATCH_POLICY.md` v1.3. This project inherits current universal rules through `docs/AI_PROJECT_COMMAND_REGISTRY.md`.

## Execution routes and routine testing

- `Shift to LDC`: Local Desktop Commander primary; fallback Local MCP Coder, then Remote Desktop Commander.
- `Shift to MCP`: Local MCP Coder primary; fallback Local Desktop Commander, then Remote Desktop Commander.
- `Shift to RDC`: Remote Desktop Commander primary; fallback Local Desktop Commander, then Local MCP Coder.
- `Shift to Git`: GitHub is the source/code execution route, but routine verification still runs on the authorized local PC through the selected local connector/fallback chain.
- Legacy `Shift to Local` maps to LDC; legacy `Shift to GitHub` maps to Git.
- Routine verification runs on the authorized local PC in every route, including Git. This includes code, browser, PHP, build, regression and focused checks. Ordinary source pushes generate zero GitHub Actions.

## Hosted GitHub CI

- `.github/workflows/ci.yml` and `.github/workflows/github-only-website.yml` are request-only. Their `tools/ci-mode-gate.py` rejects routine/stage hosted requests in every route.
- Hosted CI is allowed only for a legitimate major milestone, project completion/final audit/release, or a documented genuinely necessary independent hosted check.
- Allowed request reasons are `milestone`, `project_completion`, and `necessary`. Every request must carry exact-source binding plus `authorization_ref`, `local_evidence`, `hosted_only_need`, and explicit execution surface.
- `workflow_dispatch` is not authorization. The execution controller must not use it to bypass the source-bound request gate.
- `.github/workflows/deployment-rehearsal.yml` remains a separately scoped manual Linux milestone rehearsal. Its preflight already requires exact ledger-recorded authorization and local evidence before expensive work; it is not a routine CI path.
- A local PASS, new source SHA, earlier hosted failure, `Y`, `Proceed`, or `Resume` does not independently authorize hosted reruns.
- On an already-created CI run, a stream/poll timeout never creates a duplicate. Resume the exact run and consume its terminal outcome.

Regression command after safe local sync: `py -3 tools/ci-mode-gate.py --self-test`.

This control reconciliation does not complete or reopen a product roadmap point, authorize provider/production activation, or weaken identity, security, payment, fixture-isolation or LOOP_GUARD requirements.

## Risk-based local test scheduling and exclusive fixture lifecycle

Apply `docs/PROJECT_EXECUTION_EFFICIENCY_POLICY.md` before every stage/family test decision. Small changes use focused + impacted-neighbor checks; reuse unchanged valid PASS evidence with explicit rationale. Full joined local regression remains mandatory at applicable family/stage closure, final release, or justified material cross-system risk. Mutable test-schema setup, migration, tests, cleanup and verification run sequentially under the same exclusive DB lock (`py -3 tools/run_isolated_test.py --db-id mobisttech_test@127.0.0.1:13306 --cwd backend -- <test command>`) or independently equivalent isolation. Never overlap independent suites on that database or reset unowned residual rows.
