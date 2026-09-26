# mobiST Tech — enforced CI execution behavior

Canonical authority: `lawangin00/references/UNIVERSAL_EXECUTION_MODE.json`, `UNIVERSAL_EXECUTION_MODE_POLICY.md` v1.9 and `UNIVERSAL_CI_REQUEST_DISPATCH_POLICY.md` v1.4. This project inherits current universal rules through `docs/AI_PROJECT_COMMAND_REGISTRY.md`.

## Execution routes and routine testing

- `Shift to LDC`: Local Desktop Commander primary; fallback Local MCP Coder, then Remote Desktop Commander. Routine verification runs on the authorized local PC.
- `Shift to MCP`: Local MCP Coder primary; fallback Local Desktop Commander, then Remote Desktop Commander. Routine verification runs on the authorized local PC.
- `Shift to RDC`: Remote Desktop Commander primary; fallback Local Desktop Commander, then Local MCP Coder. Routine verification runs on the authorized local PC.
- `Shift to Git`: GitHub is the source/code execution route **and** routine automated verification runs through source-bound GitHub CI. Local PC access is not required for this route.
- These are the only execution-route commands. No compatibility aliases are recognized.

## Hosted GitHub CI

- `.github/workflows/ci.yml` and `.github/workflows/github-only-website.yml` remain request-only through `.github/ci-requests/**`; ordinary source pushes alone start zero Actions.
- In Git route, authorized stage/sub-stage verification may use `reason: stage` and must consume the terminal hosted result before claiming the gate passed.
- In LDC/MCP/RDC routes and Local Work, routine `reason: stage` hosted requests are rejected because routine verification belongs on the local PC.
- Local routes may use hosted CI only for `milestone`, `project_completion`, or `necessary` independent verification with `authorization_ref`, `local_evidence`, and `hosted_only_need`.
- Generic `workflow_dispatch` is not authorization. The execution controller must not use it to bypass the source-bound request gate.
- `.github/workflows/deployment-rehearsal.yml` remains a separately scoped manual Linux milestone rehearsal whose own preflight requires ledger-recorded authorization and local evidence.
- A stream/poll timeout never creates a duplicate CI request or parallel run. Resume the exact existing run and consume its terminal outcome.

Regression command after safe local sync: `py -3 tools/ci-mode-gate.py --self-test`.

This control reconciliation does not complete or reopen a product roadmap point, authorize provider/production activation, or weaken identity, security, payment, fixture-isolation or LOOP_GUARD requirements.

## Risk-based local test scheduling and exclusive fixture lifecycle

This section applies when the selected route is LDC, MCP, RDC, or verified Local Work. Apply `docs/PROJECT_EXECUTION_EFFICIENCY_POLICY.md` before every stage/family test decision. Small changes use focused + impacted-neighbor checks; reuse unchanged valid PASS evidence with explicit rationale. Full joined local regression remains mandatory at applicable family/stage closure, final release, or justified material cross-system risk. Mutable test-schema setup, migration, tests, cleanup and verification run sequentially under the same exclusive DB lock (`py -3 tools/run_isolated_test.py --db-id mobisttech_test@127.0.0.1:13306 --cwd backend -- <test command>`) or independently equivalent isolation. Never overlap independent suites on that database or reset unowned residual rows.
