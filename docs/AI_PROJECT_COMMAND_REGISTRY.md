# Mobisttech - AI Project Command Registry

**Registry version:** MT-1.1-r19
**Applied universal implementation epochs:** `U-CI-2026-09-19-1`
**Universal baseline (review provenance):** Registry 1.36 / System 7.12
**Bootstrap baseline (review provenance):** 1.14
**Last-reviewed universal commit:** `2ac3fd8472248dd41f8f627041f5cc8b0304fd87`
**Roadmap specification:** 1.12
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
**Owner-approved response layout:** `docs/PROJECT_RESPONSE_FORMAT.md`

## Purpose

This is a **delta-only** project registry. At runtime it inherits the current canonical Universal Registry; the version/commit fields above record only the last compatibility review and do not freeze older universal semantics. It contains only Mobisttech-specific rules and must not embed a copied Universal Registry snapshot.

## Project-specific rules

- This is the new independent Mobisttech project. Do not resume or advance old DP-* task position.
- `C:\mobisttech` is this project's exact local hard boundary when local execution is explicitly selected. Canonical remote is only `lawangin00/mobisttech`.
- Protected legacy sources are `C:\mobiST\mobiST-POS` and `C:\mobiST\mobiST-Website`. Their files, Git metadata, remotes, databases and services are read-only evidence for this project; source tests/builds may run only in isolated exported copies.
- Current user instruction has highest authority. Project Goal defines required functionality; approved Preferences and requirements addendum define implementation boundaries. Legacy source documents are technical evidence only; old architecture/command semantics do not carry into this project.
- `Initialize Project` for this project completes only MT-0.1. Do not rerun initialization merely because Goal/Preferences/addendum/control files are reconciled later.
- `Y` / `Proceed`: continue an active `In Progress` point, otherwise execute the first verified Pending point. After one point is fully completed, verified, committed and pushed, stop; do not automatically start the next point.
- Chat/Work/Local Work handoffs are exclusively user-initiated through existing explicit manual commands. Do not propose an automatic conversation-surface handoff, infer one from a task type, or impose proposal/acceptance/decline dependencies on continuing work. An actual missing technical capability may justify suggesting an explicit **execution-mode** command; it must never cause an automatic mode change or handoff.
- Audit/Verify/Sync/Checkpoint/manual-handoff mutation scope is limited to this monorepo. Never synchronize, repair, pull/fetch, repoint or mutate protected legacy source repositories as a side effect of this project's commands.
- Authorized implementation on the user-selected execution mode plus intended commit/push to this project's remote are within point scope. Destructive operations, live production/customer-data cutover, real external messaging and provider activation require separate authorization.
- Never replace/repoint/overwrite this remote with an existing repository. Never force-push.
- Project ID/workspace/identity/remote/manual-handoff mismatch is a hard stop before mutation. Never substitute a similarly named/sibling project.
- Directional manual handoffs must verify Project ID `282dba2f-a2d9-47e8-aa8d-e499fbe1706c`, canonical remote `lawangin00/mobisttech` and intended pushed checkpoint.
- `Switch Project` is the only normal operation that may intentionally release this Session Project Lock.
- Roadmap Markdown is authoritative. Live Completed/In Progress/Pending position and progress counts belong only in the ledger.
- Routine execution must not edit the roadmap or read/regenerate its DOCX. Regenerate/content-check/render-verify the same-basename DOCX only when roadmap structure/content materially changes.
- Detailed evidence belongs in Git-tracked documentation; normal user-facing completion/status output remains concise.
- Roman Urdu is default only for assistant chat/UI; Git-tracked project docs/technical artifacts use standard English unless explicitly overridden.

## Inherited universal ordinary response format (binding)

All ordinary Mobisttech execution, continuation, completion, incomplete-point, checkpoint, status and progress replies follow the current owner-approved shared five-line contract in `lawangin00/references/UNIVERSAL_PROJECT_RESPONSE_FORMAT.md`, as incorporated into the current Universal Registry. `docs/PROJECT_RESPONSE_FORMAT.md` preserves the owner's local precedent and renderer guidance; it is not an independent override of the shared layout. Include a compact DONE count on `Current` only when relevant and verified. Current stage/point status, actually completed work and first pending action must reflect the live ledger without advancing MT-7.5 or inventing a completion. The current universal format supersedes conflicting older chat/project presentation examples; specialized commands, explicit different-answer requests and all safety/identity gates retain their semantics.

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

All universal aliases, collision rules, execution-intent isolation, read-only mutation barriers, evidence budgets, manual-handoff rules, identity/session-lock semantics, Loop-Guard and final-audit defaults not explicitly specialized above are inherited from the **current canonical Universal Registry** at runtime. The shared owner-approved universal ordinary response format is inherited without a project-specific layout override; project-specific facts and verified progress values remain local. The review-provenance fields at the top are informational only and never pin execution to an older universal release.

`COMPATIBILITY_HOLD: none`.

Future universal control fixes auto-apply on canonical first-load/Refresh/new session. This does not advance/reopen Mobisttech roadmap implementation, alter application data, or replace this project's genuine delta rules.

## GitHub-only execution status (21-Sep-2026)

- Canonical global state `lawangin00/references/UNIVERSAL_EXECUTION_MODE.json` has been created and read back at `2ac3fd8472248dd41f8f627041f5cc8b0304fd87`: `global_mode=GITHUB`, `exceptions={}`. The canonical state, not this project copy, governs later user-issued mode transitions.
- GitHub-only website pilot: `bd18cffc2c56f849060b8fbedbec2583fede45c5`, run `35546044796`, successful checkout, pinned dependency installation, website typecheck, lint and production build. This focused pass is not final backend, browser or release acceptance.
- The historical private-repository Actions rejection must not be reported as a current website CI blocker; use actual fresh run evidence for the current public repository and exact candidate.
- GitHub plugin and GitHub Actions are the default for authorized website development, builds and automated tests. Local-only acceptance remains pending until genuinely performed following an explicit execution-mode command or exact scoped exception; do not assume a global mode switch implies local acceptance.
- Preserve the existing MT-7.5 In Progress point and all unrelated gates; mode-policy reconciliation does not restart or close MT-7.5.
