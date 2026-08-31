# Universal Project Roadmap Specification

**Specification version:** 1.1
**System version:** 7.2
**Canonical remote:** `lawangin00/references`
**Canonical path:** `UNIVERSAL_PROJECT_ROADMAP_SPEC.md`

## Purpose

This specification defines the durable structure and lifecycle of executable project roadmaps created or maintained by the Universal Verified Project Command System.

A roadmap is the durable structural execution plan, not the live progress ledger and not chat memory. It must be Git-tracked and tied to the project's canonical Source of Truth, implementation ledger, Git history and actual implementation. The implementation ledger owns current Completed/In Progress/Pending position and progress counts.

## Canonical Markdown + Word mirror

- The canonical roadmap must be a Markdown `.md` file.
- The same roadmap must also have a Git-tracked `.docx` companion with the same basename in the same documentation location.
- Example: `docs/PROJECT_IMPLEMENTATION_ROADMAP.md` + `docs/PROJECT_IMPLEMENTATION_ROADMAP.docx`.
- Markdown is authoritative. The DOCX is a human-readable mirror and must not become an independently edited source of truth.
- The DOCX must preserve the same structural roadmap content: stages, point IDs/titles, scopes, acceptance intent, dependencies, tables/lists and HOLD/Deferred information in readable Word formatting. Live point/stage status belongs in the implementation ledger and need not be duplicated in the roadmap.
- Whenever the Markdown roadmap legitimately changes structurally or materially, regenerate and verify the DOCX mirror in the same checkpoint before the roadmap update is considered complete.
- Routine execution progress (for example Pending -> In Progress -> Completed, last/current/next point, stage progress and completion counts) must be recorded in `docs/PROJECT_IMPLEMENTATION_STATUS.md` only and must not trigger a roadmap or DOCX update.
- If Markdown and DOCX differ, Markdown wins and the DOCX must be regenerated.
## Creation and reuse

- `Initialize Project` must inspect existing project evidence before creating a roadmap.
- Reuse a valid existing canonical roadmap instead of creating a duplicate or renumbering completed work.
- If no adequate roadmap exists, create one using a repository-consistent path, preferably `docs/PROJECT_IMPLEMENTATION_ROADMAP.md`.
- Create the synchronized `.docx` mirror at the same time.
- Record both roadmap paths in `docs/PROJECT_IMPLEMENTATION_STATUS.md`.
- A project should normally have one active canonical roadmap at a time.
- A completed/closed roadmap may remain in Git history or documentation while a later major stage activates a new roadmap; the ledger must identify the currently active one.

## Required roadmap structure

The roadmap should contain, as applicable:

1. project/stage identity and purpose;
2. canonical Source of Truth reference;
3. scope and execution boundaries;
4. stable stages/phases;
5. stable independently verifiable executable point IDs and exact titles;
6. concise scope/acceptance intent for each point;
7. dependencies and cross-repository effects where relevant;
8. explicit HOLD/Deferred items;
9. stage exit/acceptance criteria where useful;
10. a mandatory `FINAL-AUDIT` point or an explicitly designated equivalent final sign-off gate.
## Point design and status rules

- Preserve existing valid IDs/titles in an active project.
- Avoid meaningless micro-tasks and avoid bundling unrelated high-risk work into one point.
- Use sub-points only when they materially improve execution or verification.
- Never mark partial work complete.
- The implementation ledger is authoritative for live Completed/In Progress/Pending state, last/current/next point and progress counts; Git/code evidence proves the claims.
- Roadmaps should normally omit live point/stage status markers so routine progress does not create roadmap churn. HOLD/Deferred items remain in the roadmap when they are structural scope/dependency constraints.

## Roadmap mutation rules

A roadmap is not recreated or edited for routine execution progress. Modify it only when verified project reality changes the plan itself, such as an approved requirement/scope change, a newly discovered dependency, justified remediation, changed scope/acceptance intent, HOLD/Deferred change, or a new major execution stage. Ordinary point/stage completion updates the ledger only.

Do not silently rewrite, renumber or reopen completed work. Do not add speculative scope simply because it may be useful later. Record material changes in Git history and reconcile the implementation ledger.

## Multi-repository projects

When one project spans multiple repositories and each repository carries project-control documentation, keep the canonical roadmap Markdown and DOCX mirror synchronized across the relevant repositories unless the project deliberately records a different single canonical location.

## FINAL-AUDIT requirement

Every new roadmap must end with `FINAL-AUDIT` or explicitly designate an existing equivalent. That gate must independently verify authorized requirements, roadmap/ledger consistency, Git/code/data state, migrations/recovery, applicable tests/build/security/integration checks, documentation, HOLD/Deferred boundaries and repository synchronization.

A real gap reopens remediation. Only a clean final audit may permit `Project complete: 100%`.
