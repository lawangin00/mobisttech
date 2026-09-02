# mobiST Tech - AI Project Command Registry

**Registry version:** MT-1.1-r8
**Universal baseline:** Registry 1.13 / System 7.6
**Date:** 2026-09-02
**Repository root:** `C:\mobisttech`
**Remote:** `lawangin00/mobisttech` (independent, private)
**Project ID:** `282dba2f-a2d9-47e8-aa8d-e499fbe1706c`
**Project identity:** `docs/PROJECT_IDENTITY.json`
**Universal control bootstrap:** `lawangin00/references/UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md`
**Canonical Goal:** `docs/PROJECT_GOAL.md`
**Binding Preferences:** `docs/PROJECT_PREFERENCES.md`
**Approved addendum:** `docs/PROJECT_REQUIREMENTS_ADDENDUM_v1.1.md`
**Addendum traceability:** `docs/REQUIREMENTS_ADDENDUM_v1.1_RECONCILIATION.md`
**Source of Truth:** `docs/PROJECT_SOURCE_OF_TRUTH.md`
**Roadmap:** `docs/PROJECT_IMPLEMENTATION_ROADMAP.md`
**Word mirror:** `docs/PROJECT_IMPLEMENTATION_ROADMAP.docx`
**Ledger:** `docs/PROJECT_IMPLEMENTATION_STATUS.md`

## Project-specific rules - apply before the universal baseline

- This is a new project; do not resume or advance the old DP-* task position. Protected source paths are `C:\mobiST\mobiST-POS` and `C:\mobiST\mobiST-Website`. Their files, Git metadata, remotes, databases and services must not be mutated; source inspection is read-only. Source tests/builds may run only in isolated exported copies.
- Audit/Verify/Sync/Checkpoint and handoff mutation scope is limited to the new monorepo. Source-ledger synchronization, push, pull/fetch, bug fixes or remote repointing are forbidden even if inherited legacy documents say otherwise.
- Current user instruction has highest authority. Project Goal defines required functionality and approved Preferences define implementation boundaries; read and apply both plus the approved requirements addendum. Addendum adoption does not rerun initialization; explicit roadmap dependencies/document order resolve the next point. Legacy source documents are technical evidence only; old two-database architecture and old command semantics do not carry into the new project.
- Initialize completes only MT-0.1. Y/Proceed continues a verified current In Progress point, otherwise executes the first Pending point. After one point is fully completed, verified, committed and pushed, stop; do not automatically start the next point.
- Before initialization can complete, collect/read the approved Goal and Preferences, preserve them in canonical files and reconcile them with Source of Truth/roadmap/ledger/registry/decisions. Reuse existing approved input and ask only for genuinely missing critical information. Account-level instructions and the current Git-backed global initialization/roadmap rules apply; do not duplicate those global rules into Goal or Preferences.
- Authorized local implementation and intended new-repository commit/push are within point scope. Destructive operations, live production/customer-data cutover, real external messaging and provider activation require separate authorization; do not request unnecessary permission for readiness or safe isolated tests.
- Never replace/repoint/overwrite the new remote with an existing repository. Creation must follow an existence check and use an independent private repository. Never force-push.
- System 7.6 uses the stable loader at `lawangin00/references/UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md`. On the first project-control invocation in every new/unbound chat/session, fresh-read that bootstrap and the current Universal Registry when accessible, then resolve and bind exactly Project ID `282dba2f-a2d9-47e8-aa8d-e499fbe1706c` before loading alias-specific state; reuse that Session Project Lock for later aliases.
- `C:\mobisttech` is this project's exact local hard boundary. The protected legacy `C:\mobiST\mobiST-POS` and `C:\mobiST\mobiST-Website` repositories belong to a different Project ID and must never substitute for this project because of name similarity, sibling discovery or remembered context.
- Directional handoffs are project-bound. Verify this Project ID, canonical remote `lawangin00/mobisttech` and the intended pushed commit using the System 7.6 structured handoff contract; any identity/workspace/remote/checkpoint mismatch hard-stops before mutation.
- `Switch Project` / `VP:SWITCH-PROJECT` is the only normal read-only operation that may intentionally release this session lock and bind another verified Project ID. `Refresh` fresh-reads the canonical control bootstrap, current Universal Registry and this project registry within the already locked Project ID; it never switches projects.
- Every completed point records applicable focused/full gates, exact pending state, source-boundary verification and Git evidence. Never mark partial work Complete.
- Roadmap Markdown is the authoritative structural plan. Live Completed/In Progress/Pending state, last/current/next position and progress counts belong only in `docs/PROJECT_IMPLEMENTATION_STATUS.md`. Routine point/stage progress must not edit the roadmap or regenerate the DOCX. Regenerate, content-check and render-verify the same-basename DOCX only when roadmap structure/content materially changes. Helper: `tools/docs/build_roadmap_docx.py`.
- On the first project-control invocation in a new/unbound session, fresh-read the canonical control bootstrap and Universal Registry first, resolve and lock `docs/PROJECT_IDENTITY.json`, then load this registry once and reuse the Project ID lock plus loaded bootstrap/registry semantics. Refresh Registry/Refresh/VP:REFRESH-REGISTRY refreshes the bootstrap and applicable registries only within this locked Project ID and must not advance roadmap implementation.
- Alias resolution uses normalized full-message matching with exact-match precedence. `N` resolves only to `N` / `VP:STOP`, `Next` only to `Next` / `VP:NEXT`, and `Y` only to `Y` / `Proceed` / `VP:PROCEED`; never use prefix, substring, abbreviation, edit-distance, semantic, autocomplete or fuzzy expansion to turn one registered alias into another.
- `N` / `VP:STOP` is terminal for the current turn and overrides the default completion/incomplete prompt protocol. After STOP, never repeat `Proceed? Y/N`. If no roadmap point is `In Progress`, return only `Stopped.` and `Next pending: <exact point identifier> - <exact title>`. If a point is `In Progress`, return only `Stopped.`, `In Progress: <exact point identifier> - <exact title>` and `Next action: <exact recovery action>`.
- Roman Urdu is the default only for assistant chat/UI communication. Git-tracked project documentation and technical artifacts must use standard English unless the user explicitly requests another language for a specific artifact. Preserve user-supplied source documents in their original language/content unless transformation is explicitly authorized. Minimal completion labels and exact technical/task identifiers remain unchanged.
- Detailed evidence belongs in Git-tracked documentation; normal user-facing completion/status output remains concise.

Documentation revision MT-1.1-r8 synchronizes Universal Registry 1.13 / System 7.6 and adopts the stable Git-backed `UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md` loader architecture while preserving durable Project Identity, Session Project Lock, project-bound handoff verification, mismatch hard-stop and Switch Project semantics. This control-plane sync does not advance or reopen roadmap implementation; already-open sessions that loaded r7 or older must run one Refresh to adopt r8/System 7.6 semantics.

## Adopted universal definitions

This revision deliberately synchronizes the project registry to Universal Registry 1.12 / System 7.5. Exact alias semantics remain deterministic and the project now has immutable identity/session binding: every first alias in a new/unbound session resolves Project ID before execution, handoffs verify Project ID plus canonical remote checkpoint, mismatches hard-stop and only Switch Project can intentionally rebind. The optimized roadmap lifecycle, approved requirements, exact point order and current implementation position remain unchanged; no roadmap implementation point is advanced or reopened by this identity backfill.

The canonical universal registry snapshot below is copied from `lawangin00/references/UNIVERSAL_PROJECT_COMMAND_REGISTRY.md`. Project-specific rules above are explicit specializations; all other semantics remain unchanged. Control bootstrap Git blob: `557326fa17dad92b99d2b327ff0a9fe36d0dc9e9`. Baseline Git blob: `ac5e61fb96651f9aa81628d1bfae521ee4fd10d3`. Roadmap specification v1.3 Git blob: `3cbb9af3f55bd1c9f814dc49497bec2f3ad549f2`.

---

# Universal Verified Project Command Registry

**Registry version:** 1.13
**System version:** 7.6
**Canonical remote:** `lawangin00/references`  
**Canonical path:** `UNIVERSAL_PROJECT_COMMAND_REGISTRY.md`
**Control bootstrap:** `UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md`
**Roadmap specification:** `UNIVERSAL_PROJECT_ROADMAP_SPEC.md`

## Purpose

This is the Git-backed universal authority for project-control aliases and default semantics. It is loaded through `UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md`, which is the stable account-level entry point and loading contract. This registry defines exact default behavior for new projects and provides a recovery source when a project-specific registry does not yet exist.

It is not project state. A project's own Git-tracked registry may specialize these definitions. Within an initialized project, the project-specific registry wins where it explicitly differs.

## Authority model

0. `UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md` defines the stable loading/discovery contract only; it is not project state and does not replace this registry.
1. `docs/PROJECT_IDENTITY.json` defines the durable project identity, repository group and project boundary.
2. Project-specific Source of Truth + roadmap define required work.
3. `docs/PROJECT_IMPLEMENTATION_STATUS.md` defines verified project position.
4. Git history + actual code/data prove implementation.
5. `docs/AI_PROJECT_COMMAND_REGISTRY.md` defines project-specific alias behavior.
6. This universal registry is the fallback/bootstrap baseline only.

Conversation memory and uploaded Project Source snapshots are non-authoritative.
## Bootstrap and interpretation rules

- Alias resolution uses normalized full-message matching. Trim leading/trailing whitespace; matching is case-insensitive; harmless punctuation/spacing tolerance may be applied only after full-message normalization and must never transform one registered alias into another.
- Exact normalized alias matches take precedence over any tolerant interpretation.
- Never use prefix, substring, abbreviation, edit-distance, semantic, autocomplete or fuzzy expansion to resolve one registered alias as another.
- Single-token aliases are collision-protected: `N` resolves only to `N` / `VP:STOP`; `Next` resolves only to `Next` / `VP:NEXT`; `Y` resolves only to `Y` / `Proceed` / `VP:PROCEED`. Therefore `n` must never resolve to `Next`, and `next` must never resolve to `N` / `VP:STOP`.
- An explicit `VP:` alias takes precedence whenever present and remains the collision-safe form.
- `Y` and `Proceed` are synonyms.
- Any short message beginning with `VP:` is an explicit collision-safe alias invocation.
- Do not treat ordinary conversation as a command merely because it contains an alias word.
- On the first project-control invocation in a new/unbound chat/session, fresh-read `UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md`, then this registry, resolve Project ID, and only then load the bound project registry once as the project-specific session authority.
- On later aliases in the same chat/session, reuse the loaded bootstrap/registry semantics and Session Project Lock without repeated freshness checks.
- After an intentional bootstrap/registry update, run `Refresh Registry`, `Refresh` or `VP:REFRESH-REGISTRY` once in each already-open session that should adopt the change. New chats/sessions load the latest bootstrap/registry on their first project-control invocation.
- A refresh force-reads the canonical bootstrap, this Universal Registry and the latest registry for the already locked Project ID, replaces session-loaded semantics, preserves the current project position/Project ID and stops without advancing implementation.
- If a requested bootstrap/registry refresh cannot access the required canonical source, state only the minimum access/handoff action required; do not pretend the refresh succeeded.
- If multiple plausible projects exist, return concise numbered options instead of guessing.
- If an uploaded/Project Source snapshot conflicts with newer Git-backed project state, Git-backed state wins; identify the stale snapshot.
- Never repeat work already verified complete.
- Never mark partial work complete.
- Read-only aliases never authorize implementation, commits, pushes or unrelated mutations.
- Default language applies only to assistant chat/UI communication: use Roman Urdu unless explicitly overridden for the current scope. Git-tracked project documentation and technical artifacts (including README files, Source of Truth, roadmaps, status ledgers, command registries, specifications/evidence and code comments) must use standard English unless the user explicitly requests another language for that artifact. Preserve user-supplied source documents in their original language/content unless transformation is explicitly authorized.
- If a required repository, registry, ledger or tool is inaccessible, state only the minimum access/handoff action required.

## Bootstrap loading contract

- Account-level Custom Instructions should remain a small stable loader that points to `lawangin00/references/UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md`; they should not duplicate this registry.
- On the first project-control invocation in a new/unbound chat/session, fresh-read the canonical bootstrap first, then this Universal Registry, then resolve Project ID before alias-specific execution.
- The account loader may treat a short standalone command-like message in a clear project/repository context as a bootstrap trigger without deciding its meaning from memory. Exact alias recognition happens only after this current registry and the bound project registry are loaded. Any short `VP:` message is always a bootstrap trigger.
- Changing this bootstrap, this Universal Registry, the Roadmap Specification or a project registry normally must not require account-level Custom Instructions changes. Account settings need revision only if the canonical bootstrap path or fundamental loader trigger changes.
- `Refresh Registry`, `Refresh` and `VP:REFRESH-REGISTRY` must fresh-read the canonical control bootstrap plus the current Universal Registry and the already locked project's registry, preserving the same Project ID and project position. Refresh never switches projects.
- If the canonical private bootstrap is inaccessible, do not execute aliases from remembered/cached definitions; request only the minimum access/handoff action required.

## Project identity and session binding

- Every initialized project must have a Git-tracked `docs/PROJECT_IDENTITY.json`. Its `project_id` is an immutable UUID assigned once to the logical project, not derived from the project name or folder name.
- The identity file must record at least: schema version, project ID, project name, project type (`single-repository` or `multi-repository`), canonical repository remote(s), repository visibility, default branch(es), known local root(s) when applicable, initialization date, and active identity status.
- Multi-repository members of one logical project must carry the same project ID and synchronized identity definition across the repositories that carry project-control documentation.
- Every registered project-control alias, plain or `VP:` form, is identity-gated. On the first project alias in a new/unbound chat or session, resolve the exact project identity before alias-specific execution. This applies to all aliases, including read-only aliases and handoff aliases.
- Identity resolution precedence is: (1) a valid structured project-bound handoff invocation; (2) an explicitly selected workspace/root containing a valid project identity; (3) an explicitly selected repository/project source containing a valid project identity; (4) verified accessible project identity candidates. Never select a project by name similarity, nearest folder, sibling/parent relationship, remembered conversation context or fuzzy matching.
- An explicitly selected local workspace/root is a hard project boundary. If it is empty, treat it as a genuinely new project target. Never substitute a parent, sibling or similarly named existing project. If it is non-empty but project identity is ambiguous, stop and ask whether to adopt/select an existing project or use that root for a new project; do not guess.
- If an unbound session has multiple plausible identities, return concise numbered options including project name, Project ID and canonical remote(s), then wait for the user's selection. If none is usable, request only the minimum project-selection/access action.
- Once identity is resolved, bind the chat/session to that exact `project_id` (Session Project Lock). Reuse that identity for all later project aliases in the same chat/session without repeating identity discovery. Registry refresh does not change the session's project identity.
- A project identity mismatch between the session lock, selected workspace, identity file, canonical remote or structured handoff is a hard stop. Report the expected and observed identities concisely and perform no project mutation. Never silently switch, repoint or continue another project.
- `Switch Project` / `VP:SWITCH-PROJECT` is the only normal alias that intentionally releases the current Session Project Lock. It is read-only: resolve/select another verified identity, bind the session to it, load that project's registry, and stop without advancing implementation.
- Existing initialized projects that predate this rule must be backfilled non-destructively with a stable project identity before they adopt System 7.6 session-lock/handoff semantics. Backfilling identity does not reopen completed roadmap work.

## `Initialize Project` / `VP:INITIALIZE`

Initialization is identity-gated and starts with a Project Target Gate before Goal/Preferences resolution.

1. Resolve the target.
   - If the current surface has an explicitly selected workspace/root with a valid `docs/PROJECT_IDENTITY.json`, bind that existing project.
   - If the explicitly selected workspace/root is empty, treat only that exact root as the genuinely new project target. Do not search siblings/parents for a substitute project.
   - If there is no explicit project root/repository/identity in an unbound session, ask first: `1. Start a new project` or `2. Continue an existing project`. Do not inspect similarly named repositories and choose one automatically.
   - For `Continue an existing project`, discover verified identity files and return numbered project choices when more than one exists. Bind only the selected identity.
   - For `Start a new project` with local filesystem access, ask for the exact new project root path when it is not already selected, verify/create that root, and treat it as the hard boundary.
   - For `Start a new project` on a cloud-only surface with no local filesystem, establish the project through a new private GitHub repository after Goal/Preferences resolution; that remote becomes the canonical cloud project target and a local root may be established later during a verified handoff/clone.

2. For a genuinely new project, resolve the Project Goal first.
   - Reuse a verified Goal already supplied by the user when available.
   - Otherwise ask the user for the Project Goal.

3. Resolve Project Preferences separately after the Goal.
   - Reuse verified Preferences already supplied by the user when available.
   - Otherwise explicitly ask the user for Project Preferences.
   - The user may explicitly state that there are no additional Preferences, including `None` or `Skip`.

4. Ask for missing Goal and Preferences one question at a time. Do not require either to be embedded in the initialization command. Do not finalize canonical project-control documents until both are resolved.

5. Establish an independent private GitHub remote for every genuinely new project.
   - Derive/propose a repository name from the confirmed project identity/Goal and check the intended GitHub owner for an exact repository-name collision before creation.
   - Never adopt, overwrite, repoint or reuse an existing repository merely because its name is identical or similar. If the intended name already exists, present concise distinct-name options and resolve the new remote name before proceeding.
   - Create the repository as private using an authorized GitHub creation mechanism available on the current surface. If remote creation is unavailable, initialization remains incomplete and must report the minimum action needed; never mark initialization complete without the independent private remote.
   - For local projects, initialize Git in the exact project root when needed and connect only the newly established private remote. Never repoint another project's Git metadata/remotes.

6. Create the durable project identity only after the target and canonical private remote are resolved. Generate a new immutable UUID `project_id` and create `docs/PROJECT_IDENTITY.json`; for a multi-repository project, synchronize the same identity across all canonical member repositories.

For an existing project, reuse its verified identity, Goal and Preferences and ask only for genuinely missing critical information. Never treat an existing completed sibling/similarly named project as the selected project unless its identity was explicitly selected or verified by the target gate.

Establish the minimum durable Git-tracked control system:
- `docs/PROJECT_IDENTITY.json`;
- canonical Source of Truth/requirements;
- implementation roadmap/task plan ending in `FINAL-AUDIT` or an explicit equivalent, following `UNIVERSAL_PROJECT_ROADMAP_SPEC.md` when accessible;
- synchronized Git-tracked DOCX roadmap mirror with the same basename;
- `docs/PROJECT_IMPLEMENTATION_STATUS.md` recording the Project ID, identity path and both roadmap paths;
- `docs/AI_PROJECT_COMMAND_REGISTRY.md` recording the Project ID and based on this universal registry plus project-specific rules;
- README/NOTICE pointers when useful.

For a genuinely new project, create/verify an initial commit and push the identity/control baseline to the new private canonical remote before initialization may be marked complete. Preserve valid existing docs for existing projects. If this global registry is inaccessible, do not invent its exact definitions; request the minimum access or use the retained fallback initialization contract.

## `Y` / `Proceed` / `VP:PROCEED`

If an executable roadmap point is already verified `In Progress`, continue that same point from its first genuinely pending action. Otherwise verify the previously reported completion against the project ledger, roadmap, Git and actual implementation, reconcile any discrepancy, then start the first genuinely pending roadmap point. Once execution begins, continue that same roadmap point through required implementation, verification, applicable documentation/ledger reconciliation and intended commit/push whenever technically possible. Do not stop merely because the point is long or has multiple substeps. Return `In Progress` only when continuation is genuinely prevented by a hard blocker, unavailable dependency/tool/access, safety boundary, usage/runtime interruption, or another condition that cannot be resolved in the current run. Do not repeat completed work.

## `N` / `VP:STOP`

Stop after the current safe checkpoint. Do not start the next roadmap point. Preserve verified project state. This command is terminal for the current turn and overrides the default completion/incomplete prompt protocol: after STOP, do not ask `Proceed? Y/N` again.

If no roadmap point is currently `In Progress`, return only:

`Stopped.`  
`Next pending: <exact point identifier> - <exact title>`

If a roadmap point is already `In Progress`, return only:

`Stopped.`  
`In Progress: <exact point identifier> - <exact title>`  
`Next action: <exact recovery action>`

## `Resume` / `VP:RESUME`

Recover after a limit, timeout, crash, restart, stale/new session or interrupted task. Read the project registry and ledger first, identify/read the active Source of Truth and roadmap, verify Git history plus working tree/code, reconcile interrupted work, and continue from the first genuinely pending action.

## Project-bound handoff contract

Directional surface handoffs must be bound to the durable Project ID; project names alone are not sufficient.

- Preferred transfer form is a structured one-line invocation using the exact documented grammar: `VP:<HANDOFF> | PROJECT-ID=<uuid> | REPOS=<owner/repo>@<commit>[,<owner/repo>@<commit>...]`. Structured handoff fields are parsed only by this exact grammar; do not use fuzzy interpretation.
- In a source session already bound to a project, a directional handoff must verify `docs/PROJECT_IDENTITY.json`, canonical repository set and intended pushed checkpoint(s). If intended state is not pushed and an authorized safe checkpoint/push can be created, create/verify it; otherwise stop with the exact unblock action. Then return the project-bound structured handoff invocation for the destination and do not advance roadmap implementation on the source surface.
- In a new/unbound destination session, a valid structured handoff invocation must verify Project ID, canonical remote membership and every supplied commit before binding the destination session. After verification, load that project's registry/ledger/source/roadmap and reconcile/continue according to the handoff alias.
- A plain handoff alias in an unbound destination session cannot guess the source project. Run the normal Project Identity Gate first; if multiple projects are accessible, ask for numbered selection.
- If the structured Project ID, selected workspace identity, canonical remote or supplied checkpoint disagree, hard-stop with a concise mismatch report. Never substitute another project or nearest repository.

## `Chat to Work` / `VP:CHAT-WORK`

After the project-bound handoff identity is verified, reconstruct authoritative project state in Work from the verified identity, registry, ledger, source/roadmap and Git/code evidence. Continue from the first genuinely pending point. Do not rely on the previous Chat conversation as project state.

## `Work to Chat` / `VP:WORK-CHAT`

After the project-bound handoff identity is verified, reconstruct authoritative project state in Chat from the verified identity, registry, ledger, source/roadmap and Git/code evidence. Continue from the first genuinely pending point. Do not rely on the previous Work conversation as project state.

## `Cloud Work to Local Work` / `VP:CLOUD-LOCAL`

Verify the project-bound identity first. Resolve the local workspace only if its `docs/PROJECT_IDENTITY.json` matches the handoff Project ID and canonical remote set; otherwise hard-stop. Compare pushed remote Git state with the verified local working tree/project control files, reconcile any difference, then continue from the first genuinely pending point.

## `Local Work to Cloud Work` / `VP:LOCAL-CLOUD`

Verify the project-bound identity first. Verify intended local checkpoints were pushed and that the cloud-accessible canonical remote(s) carry the same Project ID and supplied checkpoint(s); otherwise hard-stop. Reconstruct the latest verified remote/project state in Cloud Work, reconcile any discrepancy, then continue from the first genuinely pending point.
## `Audit Chat Batch` / `VP:AUDIT-CHAT`

Use after Chat completed a batch since the last independently Work-verified boundary. Audit every completed task/subtask/checkpoint after that boundary against source, roadmap, ledger, Git, code/data, migrations, contracts and applicable tests/build/security/recovery evidence. Fix verified gaps within authorized scope, reconcile and push corrections where appropriate, then stop at the first genuinely pending roadmap point and ask `Proceed? Y/N`.

## `Audit Project` / `Audit` / `Full Audit` / `VP:AUDIT-PROJECT`

Audit the entire authorized project regardless of surface. Verify all requirements, roadmap/ledger claims, Git/code/data, migrations/recovery, tests/builds, security, integrations, docs, HOLD/Deferred items and repository synchronization. Fix verified gaps within scope, reconcile/push intended corrections, then stop at the first genuinely pending point and ask `Proceed? Y/N`. Do not automatically start a new feature after the audit.

## `Status` / `VP:STATUS`

Read-only. Report active project/stage, last verified completed point, current/in-progress point if any, first pending point, blockers/HOLD items and relevant repository synchronization. Do not implement anything.

## `Progress` / `VP:PROGRESS`

Read-only. Verify the complete active canonical roadmap, project ledger and relevant Git/code state before reporting progress. Count all numbered executable roadmap points and report:

- overall completed/total and remaining/total counts plus task-count percentage;
- deliverable-weighted percentage only when it can be responsibly estimated, clearly labeled as an estimate;
- one compact stage table with columns `Stage`, `Complete`, `Remaining`, `Total`, `Status`;
- `Complete` for closed stages and `Pending` for untouched future stages;
- for the active stage, include `Last: <exact completed ID>` and `Next: <exact pending ID>` inside the `Status` cell, and include `In Progress: <exact ID>` there when applicable.

Do not repeat the full pending-point list or explain each remaining point. Do not show a separate HOLD/Deferred summary unless a HOLD directly blocks the active/current point; `Blockers` owns the general HOLD/Deferred view. Do not show `Proceed? Y/N`. Do not implement or mutate anything.

## `Next` / `VP:NEXT`

Read-only. Report only the first genuinely pending roadmap point with exact identifier/title. Do not implement it.

## `Verify` / `VP:VERIFY`

Independently verify the last completed/current checkpoint. Fix only defects proven within that checkpoint and authorized scope, reconcile/push such corrections where appropriate, then stop without starting the next feature.
## `Checkpoint` / `VP:CHECKPOINT`

Record the exact `In Progress` state in the ledger and create/push a safe Git checkpoint where practical. Never mark partial work complete.

## `Sync Check` / `VP:SYNC`

Read-only. Report relevant local/remote/multi-repository branch, working-tree and ahead/behind state. Do not automatically pull or push.

## `Surface` / `Route` / `VP:SURFACE`

Read-only. Assess repository access, cross-repo scope, architecture complexity, migrations, security, rollback/recovery risk, debugging depth and verification workload. Recommend another available surface/reasoning level only when it materially improves safety, capability or efficiency.

## `Blockers` / `VP:BLOCKERS`

Read-only. Report active blockers, HOLD/Deferred items, unresolved decisions and exact action/external input needed to unblock each item.

## `Project Changes` / `Change Log` / `VP:CHANGES`

Read-only unless a project registry explicitly says otherwise. Build a verified, user-readable implementation history from Git history plus the project ledger/current canonical roadmap. Separate completed changes from pending/planned work. If historical evidence is incomplete, state the earliest reliable baseline instead of inventing history.

## `Question` / `Question Answer` / `VP:QUESTION`

Read-only pause for clarification. Answer the user's project question using only the minimum authoritative project evidence needed. Do not advance the roadmap, edit files, commit or push unless the same request explicitly asks for an action. Preserve the current project position after answering.

## `Switch Project` / `VP:SWITCH-PROJECT`

Read-only intentional session rebind. Release the current Session Project Lock, discover/resolve the requested verified Project ID, bind the session to that identity, load its current Git-backed project registry and stop. Do not advance a roadmap, edit project files, commit or push. If the target identity is ambiguous, return numbered project choices instead of guessing.

## `Refresh Registry` / `Refresh` / `VP:REFRESH-REGISTRY`

Read-only force refresh within the already locked Project ID. Verify the current identity still matches; fresh-read `UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md`, this Universal Registry and that project's latest Git-backed registry; fresh-read the Roadmap Specification when the invoked/current workflow requires it. Replace session-loaded bootstrap/registry semantics, preserve the current project position and Session Project Lock, and stop. A mismatch hard-stops; Refresh never switches projects. Do not advance the roadmap, edit application files, commit or push. Return only `Registry refreshed: <exact registry version>` when successful.

## `Help` / `VP:HELP`

Show registered aliases and a one-line meaning for each. Do not inspect or mutate application implementation beyond the minimum project/registry identification needed.
## Default completion protocol

After a point is fully completed and verified:

`Completed: <exact point identifier> - <exact title>`  
`Next: <exact next point identifier> - <exact title>`  
`Proceed? Y/N`

If it completes a stage, also include:

`Stage complete: <exact stage identifier/title>`

If incomplete:

`In Progress: <exact point identifier> - <exact title>`  
`Incomplete: <concise verified blocker or remaining work>`  
`Next action: <exact recovery action>`  
`Proceed? Y/N`

`Incomplete` and `Next action` must each be one concise line. Do not include bullet lists, hashes, migration batches, test counts, backup metadata, completed sub-work or detailed technical evidence unless the user explicitly asks for details; keep such evidence in Git-tracked project documentation/logs. Always show `Proceed? Y/N` for incomplete work. In that context, `Y` / `Proceed` continues the same verified `In Progress` point from its `Next action`; `N` stops and waits. When `N` / `VP:STOP` is invoked, the STOP output contract above overrides this default protocol and `Proceed? Y/N` must not be repeated. Never advance to the next roadmap point until the current point is verified complete.

## Final-audit rule

Every new project roadmap must end with `FINAL-AUDIT` or explicitly designate an equivalent final sign-off point. The final audit must independently verify all authorized requirements, roadmap/ledger claims, Git/code/data, migrations/recovery, applicable tests/build/security/integration gates, documentation, HOLD/Deferred boundaries and repository synchronization. A real gap reopens remediation; only a clean final audit permits `Project complete: 100%`.

## Registry governance

- New universal aliases or changed universal semantics must be updated here first and versioned in the Universal Project Command System reference document. Loader/discovery changes must also update `UNIVERSAL_PROJECT_CONTROL_BOOTSTRAP.md`.
- System 7.6 project identity/session-lock rules apply to every registered project-control alias on the first alias in a new/unbound chat/session; project-specific registries may tighten but must not weaken mismatch hard-stop or identity isolation.
- Genuinely new projects require an independent private GitHub remote plus a pushed identity/control baseline before initialization can complete.
- Existing initialized projects adopting System 7.6 must receive a non-destructive `docs/PROJECT_IDENTITY.json` backfill and registry synchronization; this control-plane update does not reopen completed roadmap work.
- Existing project registries do not magically update when this file or the bootstrap changes. They must be deliberately synchronized when new universal behavior is intended for that project; account-level Custom Instructions should remain unchanged unless the bootstrap path/trigger contract itself changes.
- Project-specific aliases belong in the project registry; they do not require account-level Custom Instructions when invoked through an explicit `VP:` form that the project's registry defines.
- Common plain-language aliases are discovered from the current Git-backed registry after the stable account loader triggers the canonical bootstrap; they do not require a permanent exhaustive list in account-level Custom Instructions.
- Roadmap creation/maintenance must follow `UNIVERSAL_PROJECT_ROADMAP_SPEC.md`: canonical Markdown remains authoritative; routine execution status/progress is ledger-only and must not edit the roadmap or regenerate its DOCX. Regenerate/verify the same-basename DOCX only when the roadmap itself changes structurally or materially.
- Keep this file Git-backed. A local clone is a convenience; the GitHub remote is the durable master.
