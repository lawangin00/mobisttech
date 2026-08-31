# mobiST Tech - AI Project Command Registry

**Registry version:** MT-1.0
**Universal baseline:** Registry 1.6 / System 7.2
**Date:** 2026-08-31
**Repository root:** `C:\mobisttech`
**Remote:** `lawangin00/mobisttech` (independent, private)
**Canonical Goal:** `docs/PROJECT_GOAL.md`
**Source of Truth:** `docs/PROJECT_SOURCE_OF_TRUTH.md`
**Roadmap:** `docs/PROJECT_IMPLEMENTATION_ROADMAP.md`
**Word mirror:** `docs/PROJECT_IMPLEMENTATION_ROADMAP.docx`
**Ledger:** `docs/PROJECT_IMPLEMENTATION_STATUS.md`

## Project-specific rules - universal baseline se pehle apply karein

- Yeh naya project hai; old DP-* task position ko resume/advance nahi karna. Protected source paths `C:\mobiST\mobiST-POS` aur `C:\mobiST\mobiST-Website` hain. Unki files/Git metadata/remotes/databases/services ko mutate nahi karna; read-only source inspection only. Source tests/builds bhi sirf isolated exported copies mein hon.
- Audit/Verify/Sync/Checkpoint aur handoffs ka mutation scope sirf new monorepo hai. Old source ledger synchronization, push, pull/fetch, bug fixes ya remote repointing forbidden hai, chahe inherited legacy docs kuch aur kahein.
- Current user instruction highest authority hai. Project Goal required functionality define karta hai. Source legacy docs technical evidence hain; old two-database architecture aur old command semantics new project par inherit nahi hote.
- Initialize sirf MT-0.1 complete karta hai. Y/Proceed verified current In Progress point continue kare, warna first Pending point execute kare. Aik point fully complete/verified/committed/pushed hone par stop; next point automatic start nahi.
- Authorized local implementation aur intended new-repository commit/push point scope mein hain. Destructive operations, live production/customer-data cutover, real external messaging aur provider activation separately authorized hon; readiness aur safe isolated tests ke liye unnecessary permission na maangein.
- New remote ko kisi existing repository se replace/repoint/overwrite nahi karna. Creation pehle existence check ke baad independent private repository mein ho. Never force-push.
- Every completed point records focused/full applicable gates, exact pending state, source-boundary verification and Git evidence. Partial work Complete mark nahi karna.
- Roadmap Markdown authoritative hai. Same-basename DOCX regenerate/content-check/render-verify in the same checkpoint. Regeneration helper `tools/docs/build_roadmap_docx.py` hai.
- Registry first alias par once load aur session mein reuse karein. Refresh Registry/Refresh/VP:REFRESH-REGISTRY forced fresh reload hai; roadmap implementation advance nahi hoti.
- User-facing general language Roman Urdu hai; minimal completion labels aur exact technical/task identifiers unchanged rahenge. Detailed evidence docs mein ho.

## Adopted universal definitions

Neeche canonical universal registry ka verified content snapshot hai; sirf Markdown hard-break trailing spaces normalize kiye gaye hain. Project rules above explicit specialization hain; baqi semantics unchanged hain. Baseline Git blob: `fb0e0e5e516ecc570e46e949277768202892e9d5`. Roadmap specification v1.0 Git blob: `004ff996e0e691941369ce2b7f6e505ab8533312`.

---

# Universal Verified Project Command Registry

**Registry version:** 1.6
**System version:** 7.2
**Canonical remote:** `lawangin00/references`
**Canonical path:** `UNIVERSAL_PROJECT_COMMAND_REGISTRY.md`
**Roadmap specification:** `UNIVERSAL_PROJECT_ROADMAP_SPEC.md`

## Purpose

This is the Git-backed universal baseline for project-control aliases. It defines exact default behavior for new projects and provides a recovery source when a project-specific registry does not yet exist.

It is not project state. A project's own Git-tracked registry may specialize these definitions. Within an initialized project, the project-specific registry wins where it explicitly differs.

## Authority model

1. Project-specific Source of Truth + roadmap define required work.
2. `docs/PROJECT_IMPLEMENTATION_STATUS.md` defines verified project position.
3. Git history + actual code/data prove implementation.
4. `docs/AI_PROJECT_COMMAND_REGISTRY.md` defines project-specific alias behavior.
5. This universal registry is the fallback/bootstrap baseline only.

Conversation memory and uploaded Project Source snapshots are non-authoritative.
## Bootstrap and interpretation rules

- Alias matching is case-insensitive and may tolerate minor punctuation/spacing differences.
- `Y` and `Proceed` are synonyms.
- Any short message beginning with `VP:` is an explicit collision-safe alias invocation.
- Do not treat ordinary conversation as a command merely because it contains an alias word.
- On the first project alias in a chat/session, read the current Git-backed project registry once and treat that verified copy as the session authority.
- On later aliases in the same chat/session, reuse the already verified registry semantics without any repeated registry freshness probe or reread.
- After an intentional registry update, run `Refresh Registry`, `Refresh` or `VP:REFRESH-REGISTRY` once in each already-open chat/session that should adopt the new behavior. New chats/sessions load the latest registry on their first project alias.
- A refresh force-reads the latest current Git-backed project registry, replaces the session's previously loaded registry semantics, preserves the current project position and stops without advancing implementation.
- If a requested refresh cannot access the current registry, state only the minimum access/handoff action required; do not pretend the refresh succeeded.
- If multiple plausible projects exist, return concise numbered options instead of guessing.
- If an uploaded/Project Source snapshot conflicts with newer Git-backed project state, Git-backed state wins; identify the stale snapshot.
- Never repeat work already verified complete.
- Never mark partial work complete.
- Read-only aliases never authorize implementation, commits, pushes or unrelated mutations.
- If a required repository, registry, ledger or tool is inaccessible, state only the minimum access/handoff action required.

## `Initialize Project` / `VP:INITIALIZE`

If no project registry/ledger exists, inspect accessible repos/workspace, README/docs, Git history, branches/remotes, files and supplied requirements first. Reuse verified existing goals instead of asking the user to repeat them. Load this universal registry when accessible. Ask only for missing critical information, one question at a time when needed.

Establish the minimum durable Git-tracked control system:
- canonical Source of Truth/requirements;
- implementation roadmap/task plan ending in `FINAL-AUDIT` or an explicit equivalent, following `UNIVERSAL_PROJECT_ROADMAP_SPEC.md` when accessible;
- synchronized Git-tracked DOCX roadmap mirror with the same basename;
- `docs/PROJECT_IMPLEMENTATION_STATUS.md` recording both roadmap paths;
- `docs/AI_PROJECT_COMMAND_REGISTRY.md` based on this universal registry plus project-specific rules;
- README/NOTICE pointers when useful.

Preserve valid existing docs. Commit/push when authorized. If this global registry is inaccessible, do not invent its exact definitions; request the minimum access or use the retained fallback initialization contract.
## `Y` / `Proceed` / `VP:PROCEED`

If an executable roadmap point is already verified `In Progress`, continue that same point from its first genuinely pending action. Otherwise verify the previously reported completion against the project ledger, roadmap, Git and actual implementation, reconcile any discrepancy, then start the first genuinely pending roadmap point. Once execution begins, continue that same roadmap point through required implementation, verification, applicable documentation/ledger reconciliation and intended commit/push whenever technically possible. Do not stop merely because the point is long or has multiple substeps. Return `In Progress` only when continuation is genuinely prevented by a hard blocker, unavailable dependency/tool/access, safety boundary, usage/runtime interruption, or another condition that cannot be resolved in the current run. Do not repeat completed work.

## `N` / `VP:STOP`

Stop after the current safe checkpoint. Do not start the next roadmap point. Preserve verified project state.

## `Resume` / `VP:RESUME`

Recover after a limit, timeout, crash, restart, stale/new session or interrupted task. Read the project registry and ledger first, identify/read the active Source of Truth and roadmap, verify Git history plus working tree/code, reconcile interrupted work, and continue from the first genuinely pending action.

## `Chat to Work` / `VP:CHAT-WORK`

Reconstruct authoritative project state in Work from registry, ledger, source/roadmap and Git/code evidence. Continue from the first genuinely pending point. Do not rely on the previous Chat conversation as project state.

## `Work to Chat` / `VP:WORK-CHAT`

Reconstruct authoritative project state in Chat from registry, ledger, source/roadmap and Git/code evidence. Continue from the first genuinely pending point. Do not rely on the previous Work conversation as project state.

## `Cloud Work to Local Work` / `VP:CLOUD-LOCAL`

Discover the relevant local workspace, compare pushed remote Git state with the local working tree and project control files, reconcile any difference, then continue from the first genuinely pending point.

## `Local Work to Cloud Work` / `VP:LOCAL-CLOUD`

Verify intended local checkpoints were pushed, reconstruct the latest remote/project state accessible in Cloud Work, reconcile any discrepancy, then continue from the first genuinely pending point.
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

## `Refresh Registry` / `Refresh` / `VP:REFRESH-REGISTRY`

Read-only force refresh. Fresh-read the latest current Git-backed project registry, replace any registry semantics previously loaded in this chat/session, preserve the current project position, and stop. Do not advance the roadmap, edit application files, commit or push. Return only `Registry refreshed: <exact registry version>` when successful.

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

`Incomplete` and `Next action` must each be one concise line. Do not include bullet lists, hashes, migration batches, test counts, backup metadata, completed sub-work or detailed technical evidence unless the user explicitly asks for details; keep such evidence in Git-tracked project documentation/logs. Always show `Proceed? Y/N` for incomplete work. In that context, `Y` / `Proceed` continues the same verified `In Progress` point from its `Next action`; `N` stops and waits. Never advance to the next roadmap point until the current point is verified complete.

## Final-audit rule

Every new project roadmap must end with `FINAL-AUDIT` or explicitly designate an equivalent final sign-off point. The final audit must independently verify all authorized requirements, roadmap/ledger claims, Git/code/data, migrations/recovery, applicable tests/build/security/integration gates, documentation, HOLD/Deferred boundaries and repository synchronization. A real gap reopens remediation; only a clean final audit permits `Project complete: 100%`.

## Registry governance

- New universal aliases or changed universal semantics must be updated here first and versioned in the Universal Project Command System reference document.
- Existing project registries do not magically update when this file changes. They must be deliberately synchronized when the new universal behavior is intended for that project.
- Project-specific aliases belong in the project registry; they do not require account-level Custom Instructions when invoked through an explicit `VP:` form that the project's registry defines.
- Common plain-language aliases intended to work in fresh contexts should also be listed in the account-level Custom Instructions bootstrap.
- Roadmap creation/maintenance must follow `UNIVERSAL_PROJECT_ROADMAP_SPEC.md`: canonical Markdown remains authoritative and its same-basename DOCX mirror must be regenerated/verified in the same checkpoint whenever the roadmap changes.
- Keep this file Git-backed. A local clone is a convenience; the GitHub remote is the durable master.
