# mobiST Tech - AI Project Command Registry

**Registry version:** MT-1.1-r12
**Universal baseline:** Registry 1.17 / System 7.7
**Adopted universal commit:** `8efa64bc494aaa33cea053a37f1f946a4aa01acd`
**Roadmap specification:** 1.5
**Date:** 2026-09-02
**Repository root:** `C:\mobisttech`
**Remote:** `lawangin00/mobisttech` (independent, private)
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

This is a **delta-only** project registry. It inherits Universal Registry 1.17 / System 7.7 from the pinned universal release above and contains only mobiST Tech-specific rules. It must not embed a copied Universal Registry snapshot.

## Project-specific rules

- This is the new independent mobiST Tech project. Do not resume or advance old DP-* task position.
- `C:\mobisttech` is this project's exact local hard boundary. Canonical remote is only `lawangin00/mobisttech`.
- Protected legacy sources are `C:\mobiST\mobiST-POS` and `C:\mobiST\mobiST-Website`. Their files, Git metadata, remotes, databases and services are read-only evidence for this project; source tests/builds may run only in isolated exported copies.
- Current user instruction has highest authority. Project Goal defines required functionality; approved Preferences and requirements addendum define implementation boundaries. Legacy source documents are technical evidence only; old architecture/command semantics do not carry into this project.
- `Initialize Project` for this project completes only MT-0.1. Do not rerun initialization merely because Goal/Preferences/addendum/control files are reconciled later.
- `Y` / `Proceed`: continue an active `In Progress` point, otherwise execute the first verified Pending point. After one point is fully completed, verified, committed and pushed, stop; do not automatically start the next point.
- Audit/Verify/Sync/Checkpoint/handoff mutation scope is limited to this monorepo. Never synchronize, repair, pull/fetch, repoint or mutate protected legacy source repositories as a side effect of this project's commands.
- Authorized local implementation plus intended commit/push to this project's remote are within point scope. Destructive operations, live production/customer-data cutover, real external messaging and provider activation require separate authorization.
- Never replace/repoint/overwrite this remote with an existing repository. Never force-push.
- Project ID/workspace/identity/remote/handoff mismatch is a hard stop before mutation. Never substitute a similarly named/sibling project.
- Directional handoffs must verify Project ID `282dba2f-a2d9-47e8-aa8d-e499fbe1706c`, canonical remote `lawangin00/mobisttech` and intended pushed checkpoint.
- `Switch Project` is the only normal operation that may intentionally release this Session Project Lock.
- Roadmap Markdown is authoritative. Live Completed/In Progress/Pending position and progress counts belong only in the ledger.
- Routine execution must not edit the roadmap or read/regenerate its DOCX. Regenerate/content-check/render-verify the same-basename DOCX only when roadmap structure/content materially changes.
- Detailed evidence belongs in Git-tracked documentation; normal user-facing completion/status output remains concise.
- Roman Urdu is default only for assistant chat/UI; Git-tracked project docs/technical artifacts use standard English unless explicitly overridden.

## System 7.7 performance specialization

- Use the universal first-bind/session-cache fast path. After successful binding, later aliases reuse loaded universal + this project delta; do not reread control registries on each alias.
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

All aliases, collision rules, exact-alias execution-intent isolation, read-only mutation barriers, STOP/completion output, lazy evidence budgets, strict read-only handoff grammar, identity/session-lock semantics and final-audit defaults not explicitly overridden above are inherited from Registry 1.16 / System 7.7 at `ffe19168247b919bb2017f2dc79c3402cb0cd6d6`.

Later universal releases do not auto-apply to this project. Update this declared baseline deliberately after compatibility review. A control-plane registry sync does not advance/reopen roadmap implementation.
