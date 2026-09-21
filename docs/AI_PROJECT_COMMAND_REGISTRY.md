# mobiST Tech - AI Project Command Registry

**Registry version:** MT-1.1-r17
**Applied universal implementation epochs:** `U-CI-2026-09-19-1`
**Universal baseline (review provenance):** Registry 1.33 / System 7.12
**Bootstrap baseline (review provenance):** 1.12
**Last-reviewed universal commit:** `c9e1050db1ce498c509dfefc3f77d911c8d41b7d`
**Roadmap specification:** 1.11
**Date:** 2026-09-21
**Repository root:** `C:\mobisttech`
**Remote:** `lawangin00/mobisttech` (independent, public)
**Project ID:** `282dba2f-a2d9-47e8-aa8d-e499fbe1706c`
**Project identity:** `docs/PROJECT_IDENTITY.json`
**Canonical Goal:** `docs/PROJECT_GOAL.md`
**Binding Preferences:** `docs/PROJECT_PREFERENCES.md`
**Approved addendum:** `docs/PROJECT_REQUIREMENTS_ADDENDUM_v1.1.md`
**Addendum traceability:** `docs/REQUIREMENTS_ADDENDUM_v1.1_RECONCILIATION.md`
**Source of Truth:** `docs/PROJECT_SOURCE_OF_TRUTH.md`
**Roadmap:** `docs/PROJECT_IMPLEMENTATION_ROADMAP.md`
**Word mirror:** `docs/PROJECT_IMPLEMENTATION_ROADMAP.docx`
**Ledger:** `docs/PROJECT_IMPLEMENTATION_STATUS.md`

## Purpose

This is a **delta-only** project registry. At runtime it inherits the current canonical Universal Registry; the version/commit fields above record only the last compatibility review and do not freeze older universal semantics. It contains only mobiST Tech-specific rules and must not embed a copied Universal Registry snapshot.

## Project-specific rules

- This is the new independent mobiST Tech project. Do not resume or advance old DP-* task position.
- `C:\mobisttech` is this project's exact local hard boundary. Canonical remote is only `lawangin00/mobisttech`.
- Protected legacy sources are `C:\mobiST\mobiST-POS` and `C:\mobiST\mobiST-Website`. Their files, Git metadata, remotes, databases and services are read-only evidence for this project; source tests/builds may run only in isolated exported copies.
- Current user instruction has highest authority. Project Goal defines required functionality; approved Preferences and requirements addendum define implementation boundaries. Legacy source documents are technical evidence only; old architecture/command semantics do not carry into this project.
- `Initialize Project` for this project completes only MT-0.1. Do not rerun initialization merely because Goal/Preferences/addendum/control files are reconciled later.
- `Y` / `Proceed`: continue an active `In Progress` point, otherwise execute the first verified Pending point. After one point is fully completed, verified, committed and pushed, stop; do not automatically start the next point.
- Chat/Work/Local Work handoffs are exclusively user-initiated through existing explicit manual commands. Do not propose an automatic conversation-surface handoff, infer one from a task type, or impose proposal/acceptance/decline dependencies on continuing work. An actual missing technical capability may justify suggesting an explicit **execution-mode** command after the universal mode policy is verified; it must never cause an automatic mode change or handoff.
- Audit/Verify/Sync/Checkpoint/manual-handoff mutation scope is limited to this monorepo. Never synchronize, repair, pull/fetch, repoint or mutate protected legacy source repositories as a side effect of this project's commands.
- Authorized local implementation plus intended commit/push to this project's remote are within point scope. Destructive operations, live production/customer-data cutover, real external messaging and provider activation require separate authorization.
- Never replace/repoint/overwrite this remote with an existing repository. Never force-push.
- Project ID/workspace/identity/remote/manual-handoff mismatch is a hard stop before mutation. Never substitute a similarly named/sibling project.
- Directional manual handoffs must verify Project ID `282dba2f-a2d9-47e8-aa8d-e499fbe1706c`, canonical remote `lawangin00/mobisttech` and intended pushed checkpoint.
- `Switch Project` is the only normal operation that may intentionally release this Session Project Lock.
- Roadmap Markdown is authoritative. Live Completed/In Progress/Pending position and progress counts belong only in the ledger.
- Routine execution must not edit the roadmap or read/regenerate its DOCX. Regenerate/content-check/render-verify the same-basename DOCX only when roadmap structure/content materially changes.
- Detailed evidence belongs in Git-tracked documentation; normal user-facing completion/status output remains concise.
- Roman Urdu is default only for assistant chat/UI; Git-tracked project docs/technical artifacts use standard English unless explicitly overridden.

## Project performance specialization

- Use the current canonical universal first-bind/session-cache fast path. After successful binding, later aliases reuse the loaded current universal + this project delta; do not reread control registries on each alias except where the universal Refresh/new-session contract requires it.
- On local-capable surfaces, verify remote HEAD once when first-bind/Refresh requires freshness, then read control files from the exact matching local Git commit.
- `Refresh` reads only identity + universal bootstrap/registry + this delta registry and stops. It must not read Goal, Preferences, addendum, Source of Truth, roadmap, ledger, code or DOCX.
- `Next` is ledger-first; roadmap only if exact next ID/title is missing/inconsistent.
- `Status` is ledger + lightweight Git state first; deeper evidence only on discrepancy.
- `Progress` reads ledger + Markdown roadmap; no DOCX/Source of Truth/code by default.
- `Sync Check` reads Git metadata only after identity/session binding.
- `Question` uses only minimum authoritative evidence.
- `Y` / `Proceed` reads only active-point evidence rather than auditing the whole project.
- Historical/Word/QA artifacts are not runtime dependencies.

## Universal inheritance and governance

All universal aliases, collision rules, execution-intent isolation, read-only mutation barriers, completion/output protocols, evidence budgets, manual-handoff rules, identity/session-lock semantics, Loop-Guard and final-audit defaults not explicitly specialized above are inherited from the **current canonical Universal Registry** at runtime. The review-provenance fields at the top are informational only and never pin execution to an older universal release.

`COMPATIBILITY_HOLD: none`.

Future universal control fixes auto-apply on canonical first-load/Refresh/new session. This does not advance/reopen mobiST Tech roadmap implementation, alter application data, or replace this project's genuine delta rules.

## Current MT-7.5 CI status (19-Sep-2026)
GitHub API confirms lawangin00/mobisttech is now PUBLIC. The earlier run 35442617990 billing rejection is HISTORICAL, not a verified current blocker. Continue local MT-7.5 work normally. At a genuinely required CI milestone, check the exact latest candidate and dispatch ONE fresh manual public-repo run if no equivalent accepted success exists. Report a current billing blocker only if that new post-public run itself fails before runner startup; do not repeat the old billing warning in current Incomplete/Next action outputs.

## GitHub-only execution migration (21-Sep-2026)

- Exact user-approved scope: global GitHub/RDC mode commands; manual Chat/Work handoffs only; no automatic conversation handoff suggestion; this active project and `lawangin00/mobist-pos-ims` are the only application-project repositories to be reconciled.
- GitHub-only website pilot: `bd18cffc2c56f849060b8fbedbec2583fede45c5`, run `35546044796`, successful checkout, pinned dependency installation, website typecheck, lint and production build. This focused pass is not final backend, browser or release acceptance.
- Canonical universal global-mode migration is **In Progress**, not yet activated or verified. Do not infer a global GitHub switch solely from this project-delta update. The canonical global state file must be present, authoritative, and freshly verified before claiming a global transition.
- Website code changes can use the GitHub plugin and verified GitHub-hosted tests; keep existing MT-7.5 progress and all unrelated gates intact. Continue from the first genuinely pending approved action; do not restart or close MT-7.5 because of execution-mode policy changes.
