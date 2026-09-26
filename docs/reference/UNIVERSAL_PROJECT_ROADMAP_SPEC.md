# Universal Project Roadmap Specification

**Specification version:** 1.18
**System version:** 7.14
**Canonical remote:** `lawangin00/references`
**Canonical path:** `UNIVERSAL_PROJECT_ROADMAP_SPEC.md`
**Control bootstrap:** `UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md`

## Purpose

This specification defines the durable structure and lifecycle of executable project roadmaps created or maintained by the Universal Verified Project Command System. It is discovered through `UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md`, but this specification remains the canonical authority for roadmap structure/lifecycle; account-level Custom Instructions do not define roadmap behavior.

A roadmap is the durable structural execution plan, not the live progress ledger and not chat memory. It must be Git-tracked and tied to the project's durable `docs/PROJECT_IDENTITY.json`, canonical Source of Truth, implementation ledger, Git history and actual implementation. The implementation ledger owns current Completed/In Progress/Pending position and progress counts.

## Canonical Markdown + archival Word snapshots

- The canonical roadmap must be a Markdown `.md` file.
- Each initialized project must create one Git-tracked `.docx` archival snapshot with the same basename/location as the canonical Markdown roadmap. Example: `docs/PROJECT_IMPLEMENTATION_ROADMAP.md` + `docs/PROJECT_IMPLEMENTATION_ROADMAP.docx`.
- Markdown is authoritative and live. The DOCX is a human-readable archival snapshot and must not become an independently edited source of truth.
- DOCX is excluded from normal project-control runtime reads whenever canonical Markdown exists. Do not open, parse, render, compare or load DOCX tooling merely because Markdown changed.
- Structural/material roadmap changes are complete when canonical Markdown and the required live control artifacts are reconciled; they do **not** require same-checkpoint DOCX regeneration.
- DOCX regeneration/rendering occurs only at approved archival checkpoints: initial project baseline; clean project completion/FINAL-AUDIT; completed `Update Project` batch when the roadmap materially changed since the previous snapshot; explicit external handoff/submission requiring Word; or explicit user request.
- Between archival checkpoints, DOCX may intentionally lag behind Markdown. That lag is not a discrepancy and must not block execution, verification, commit/push, or completion of intermediate roadmap changes.
- At an archival checkpoint, regenerate the DOCX from current canonical Markdown so the snapshot preserves stages, point IDs/titles, scopes, acceptance intent, dependencies, tables/lists and HOLD/Deferred information in readable Word formatting. Live point/stage status remains ledger-owned unless the snapshot context explicitly requires it.
- DOCX visual verification must follow the Universal Registry visual-QA artifact-routing rule: inspect rendered page images/PDFs through a tool that has direct access to their actual storage surface. A remote C:\\... artifact must not be sent to an unrelated sandbox/browser viewer unless it is explicitly transferred/materialized there and the destination is verified.
- A viewer/path-routing failure must not be misreported as a render failure and must not justify skipping required page-by-page visual QA. Correct the storage/surface routing or report the minimum real access blocker.
- Routine execution progress (for example Pending -> In Progress -> Completed, last/current/next point, stage progress and completion counts) must be recorded in `docs/PROJECT_IMPLEMENTATION_STATUS.md` only and must not trigger a roadmap or DOCX update.
- If Markdown and DOCX differ between archival checkpoints, Markdown wins and no immediate regeneration is required. At the next approved archival checkpoint, regenerate the snapshot from current Markdown.

## Project identity boundary

- Roadmap discovery, creation and reuse must occur only after the canonical control bootstrap and Universal Registry have been loaded, the exact alias scope is resolved, and a project-bound workflow has an explicit/verified Project ID under the Project Identity Gate. Universal-only aliases never trigger roadmap/project discovery.
- An explicitly selected workspace/root is a hard project boundary. Never reuse a roadmap from a parent, sibling or similarly named repository merely because the selected root is empty or lacks project-control files.
- Every initialized project must record the same immutable Project ID in `docs/PROJECT_IDENTITY.json`, the project registry and implementation ledger.
- Multi-repository roadmaps may span multiple canonical repositories only when those repositories carry the same verified Project ID or are explicitly listed as members in the canonical identity definition.
- Existing projects backfilled with durable Project Identity do not need roadmap restructuring merely because the control-plane identity/bootstrap model changes.

## Creation and reuse

- `Initialize Project` must resolve the exact project target/identity before inspecting project evidence for roadmap reuse or creation. `Update Project` must bind the existing exact Project ID before inspecting impact or mutating roadmap structure.
- Reuse a valid existing canonical roadmap instead of creating a duplicate or renumbering completed work.
- If no adequate roadmap exists, create one using a repository-consistent path, preferably `docs/PROJECT_IMPLEMENTATION_ROADMAP.md`.
- Create the initial same-basename archival `.docx` snapshot at the same baseline checkpoint.
- Record the Project ID, identity path and both roadmap paths in `docs/PROJECT_IMPLEMENTATION_STATUS.md`.
- A project should normally have one active canonical roadmap at a time.
- A completed/closed roadmap may remain in Git history or documentation while a later major stage activates a new roadmap; the ledger must identify the currently active one.

## `Update Project` lifecycle

- `Update Project` / `VP:UPDATE` applies to an existing verified Project ID when the user introduces a new requirement, changes an existing requirement, or requests post-completion enhancements. It requires an already verified Session Project Lock or an explicit current target; it must never auto-select a project from recent conversation, memory or ambient accessible repositories.
- Resolve the Update Goal first and Update Preferences/constraints separately. Preserve the original Goal/Preferences/source documents; record the update as an addendum/change set unless the user explicitly authorizes rewriting an existing source document.
- Before changing the roadmap, perform an impact analysis against current Source of Truth, roadmap, ledger and implementation evidence. Classify affected work as reuse, extension, new work or minimum required reopening.
- Completed points remain closed historical evidence by default. Reopen a completed point only when the update directly invalidates or changes that point's accepted implementation/acceptance contract. Do not reopen or redo unrelated completed work merely because a new feature was added.
- If the project is still active, integrate the smallest justified new pending point(s)/stage while preserving stable existing IDs/titles and the current `In Progress` point unless the update directly changes it.
- If the project was already complete, preserve its prior completion and final-audit history. Add a new post-completion update stage or activate a new update roadmap, with a new equivalent final verification gate for the update scope; never erase the earlier closure. The update-scope final gate must apply the universal GitHub Actions milestone-verification policy when an applicable cloud verification workflow exists.
- Any structural/material roadmap update requires immediate canonical Markdown plus ledger/Source-of-Truth reconciliation before implementation begins. DOCX regeneration is deferred to the completed update-batch archival checkpoint unless an explicit Word deliverable is required earlier.
- After the update control baseline is committed/pushed where practical, stop. Implementation begins only after a later explicit `Y` / `Proceed`. When the update batch later completes, regenerate/render/verify the archival DOCX only if that batch materially changed the roadmap since the previous snapshot.

## Required roadmap structure

The roadmap should contain, as applicable:

1. Project ID reference plus project/stage identity and purpose;
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

## In Progress interruption and Finalization Fence lifecycle

- The implementation ledger owns durable `In Progress` position. A control-turn cutoff/fence never converts partial work to `Pending`, never discards valid working-tree/checkpoint progress, and never authorizes starting the next roadmap point.
- Before a planned or fence-driven pause, preserve the exact current point and first genuinely pending action in the ledger/checkpoint where practical. Already-written source/code remains durable working-tree evidence even when docs/commit/push are not yet complete.
- A later `Y` / `Proceed` / `Resume` continues the same point from that first genuinely pending action after reconciling working-tree, ledger and any retrievable process result. For a ChatGPT stream/recovery/polling timeout, `Resume` first performs read-only recovery of the exact durable checkpoint and any already-known process/CI run; the wrapper timeout alone is not a task/run failure and must not trigger duplicate work or dispatch. An active run is followed to terminal, a terminal result is consumed as-is, and only genuinely non-retrievable process evidence is rerun. The whole point must not restart from zero unless authoritative evidence proves the partial implementation invalid.
- A long-running build/test/process result is not assumed durable merely because the process was started. If its result is not retrievable on continuation, rerun only that process/verification step while preserving valid implementation work.
- Finalization Fence pauses are routine execution-state changes and therefore update the implementation ledger/checkpoint only; they do not structurally mutate the roadmap or trigger an archival DOCX snapshot.

## Execution-attempt and Loop-Guard lifecycle

- After a logical task first fails, incompletely terminates, or is retried, `docs/PROJECT_IMPLEMENTATION_STATUS.md` must preserve a compact durable attempt record: logical task/scope, attempt number, terminal outcome, failure class/signature, relevant source/harness/infrastructure/fixture identity where applicable, material change since the prior attempt, `LOOP_GUARD` state, and exact permitted next action.
- The universal trigger is mandatory: the same materially equivalent failure twice, or three unsuccessful/incomplete attempts for the same logical task, activates `LOOP_GUARD`; missing terminal result/evidence is `ORCHESTRATION_FAIL` and counts as unsuccessful.
- An active `LOOP_GUARD` blocks another materially equivalent execution until recent attempts are reconciled, the affected workflow is reviewed as a whole, and a materially different failure-relevant strategy is justified. A new chat, handoff, timestamp, VM name, process restart or attempt number does not reset or satisfy this requirement.
- After a second same-class stale assertion/assumption failure, perform one comprehensive compatibility/reconciliation sweep instead of continuing one-assertion-at-a-time patch/retry behavior.
- Structured handoffs and interruption recovery carry the loop-state fields above so Chat/Work and Cloud/Local transitions resume the same attempt history rather than resetting it.
- When the workflow passes, clear the active loop state and preserve the successful procedure/regression/preflight where useful so equivalent future work reuses known-good execution instead of rediscovering it.
- Attempt/loop-state updates are live ledger/checkpoint state, not structural roadmap mutations, and do not trigger archival DOCX regeneration.

## Execution-mode switching and roadmap continuity

- `Shift to LDC` / `VP:SHIFT-TO-LDC`, `Shift to MCP` / `VP:SHIFT-TO-MCP`, `Shift to RDC` / `VP:SHIFT-TO-RDC`, and `Shift to Git` / `VP:SHIFT-TO-GIT` are the only universal explicit Normal Chat route commands; verified Local Work remains native `DIRECT_LOCAL`. `Execution Status` is read-only. Route changes never alter project binding, surface, completed/pending work or roadmap structure.
- A temporary project-specific exception changes only the selected verified Project ID's effective source/local route. It never changes the default for other projects and never authorizes an automatic surface handoff.
- A genuine route-specific capability blocker may be recorded against the existing `In Progress` point without adding/reopening roadmap work. Suggest only the appropriate **Shift to LDC/MCP/RDC/Git** command; never auto-switch route or conversation.
- Existing manual Chat/Work handoff aliases remain separate, exclusively user-initiated commands. The control system contains no automatic Chat/Work/Local Work handoff recommendation/acceptance/decline mechanism.

## Roadmap mutation rules

A roadmap is not recreated or edited for routine execution progress. Modify it only when verified project reality changes the plan itself, such as an approved requirement/scope change, a newly discovered dependency, justified remediation, changed scope/acceptance intent, HOLD/Deferred change, or a new major execution stage. Ordinary point/stage completion updates the ledger only.

Do not silently rewrite, renumber or reopen completed work. Do not add speculative scope simply because it may be useful later. Record material changes in Git history and reconcile the implementation ledger.

## Multi-repository projects

When one project spans multiple repositories and each repository carries project-control documentation, first verify that the repositories are members of the same `docs/PROJECT_IDENTITY.json` definition and share the same immutable Project ID. Keep the identity file, canonical roadmap Markdown and project registry synchronized across the relevant repositories unless the project deliberately records a different single canonical location. When an archival DOCX checkpoint is reached, synchronize that generated snapshot across repositories that are defined to carry it. A similarly named repository with a different or missing Project ID is not part of the project group.

## Mandatory mode-aware verification and new-project adoption

- Every newly initialized roadmap references `UNIVERSAL_EXECUTION_MODE_POLICY.md`, `UNIVERSAL_CI_REQUEST_DISPATCH_POLICY.md`, and fresh `UNIVERSAL_EXECUTION_MODE.json`. Route is live universal state resolved with the immutable Project ID; never bake stale route into identity/roadmap/DOCX. Verify exact four-command routing, local connector/fallback behavior, Git-route routine hosted CI, and local-route milestone/final hosted gates before recording `U-EXEC-ROUTE-2026-09-26-2`.
- Each stage/sub-stage names its actual quality gates and execution venue. Normal Chat follows source route + selected local backend/exception; verified Local Work runs DIRECT_LOCAL. In Git route, routine automated verification runs through source-bound hosted GitHub CI. In LDC/MCP/RDC and Local Work, routine verification runs locally.
- Ordinary source pushes alone start no GitHub Actions. In Git route the controller creates an exact-source CI request for required routine stage/sub-stage verification. In LDC/MCP/RDC and Local Work, hosted verification is limited to a major milestone, project completion/final audit/release, or a documented necessary independent check with prior local evidence and authorization.
- Final audit/project completion requires the applicable exact-source independent hosted acceptance when the project has a relevant workflow, plus all required local/GUI/VM/customer evidence. A missing runner, tailored workflow, identity, write access or terminal verdict keeps completion open and cannot yield `Project complete: 100%`.
- Initialization must derive relevant hosted and local test commands from the project's real stack, not use a generic placeholder `echo PASS` workflow. A non-code project may record a verified not-applicable CI receipt. New project adoption means rules plus tested project-specific wiring on the selected repository; universal Markdown alone does not install runners in arbitrary repos.

## FINAL-AUDIT requirement

Every new roadmap must end with `FINAL-AUDIT` or explicitly designate an equivalent. That gate independently verifies Project ID/remote identity, requirements, roadmap/ledger, Git/code/data, migrations/recovery, all route-appropriate tests/build/security/integration gates, documentation, HOLD/Deferred boundaries and repository sync, plus one non-duplicate exact-source GitHub Actions project-completion/final verification when applicable. Before declaring completion, regenerate/render/verify the final archival DOCX snapshot from canonical Markdown.

A real gap reopens remediation. Only a clean final audit may permit `Project complete: 100%`.
