# MT-0.1 - Approved Preferences reconciliation

Date: 2026-08-31 | Scope: VP:VERIFY, MT-0.1 only

## Verified defect and correction

Original initialization closure `28588c59463e8cf2f3a13be97df49ae3021ec83b` marked MT-0.1 complete without collecting/registering the approved Preferences. During this verification, the user's complete approved Preferences were read in full and preserved byte-for-byte in `docs/PROJECT_PREFERENCES.md`. Original SHA-256: `e37c3c2fd6a30ba7211ce73854c79501181b327a98ce7acafa5938ee9941d402`.

The already approved Goal remains unchanged: `7bce00947418d18151756ea7176b51546b0cbc8ae00c02fda3c1bf7c1344908f`. No architecture conflict was found between Goal and Preferences. Missing authority pointers, the reuse-before-rewrite decision, justified infrastructure, incremental verification and Control group actions were reconciled into initialization/control documentation. No source/application migration or detailed MT-1.1 inventory was executed during this remediation.

## Initialization decisions comparison

The `C:\mobisttech` single Git root, backend/website/tools/mobist-control/brand/docs/.github layout, independent private `lawangin00/mobisttech` remote, Laravel-only shared backend, one master MySQL database, internal Inertia POS and separate API-consuming Next.js Website are compatible with the approved documents. Private repository visibility was a reasonable existing initialization choice and was not changed by the Preferences. No remote repointing or repository recreation was required.

Read-only preflight verified the new local HEAD and live remote `main` were equal at `28588c59463e8cf2f3a13be97df49ae3021ec83b`; the working tree was clean. GitHub confirmed private visibility and default branch `main`. Protected-source snapshot comparison passed. Component paths contained only the original `.gitkeep` placeholders; no application runtime existed yet.

Current source-logo approval inventory, exact runtime versions, Redis role decisions and data/API design remained pending implementation/design work. The Preferences record that the legacy Control logo is older and must not be treated as approved; this verification did not migrate logo assets or claim a new approval.

## All 39 approved Preferences coverage

The table below maps every original numbered Preference. "Represented" means the required control/acceptance condition is documented; it does not mean target functionality has already been implemented.

| Preference | Approved intent | Reconciled control / acceptance |
|---|---|---|
| 1 | Incremental controlled migration | Source of Truth binding preferences; roadmap execution rules |
| 2 | Original repositories untouched | Existing source boundary retained; SOURCE_SNAPSHOT checks |
| 3 | New implementation only in C:\mobisttech | Existing root/AGENTS/registry retained |
| 4 | Single monorepo and requested structure | Existing root and component placeholders verified; no nested Git |
| 5 | Independent remote; originals never repointed | Existing private new remote verified; source origins unchanged |
| 6 | Preserve working functionality; no gratuitous rebuild | Explicit reuse-before-rewrite rule added to authority docs/roadmap |
| 7 | Assess reuse/adapt/refactor/migrate before rewrite | MT-1.1 acceptance expanded; verified rewrite reason required |
| 8 | Approved stack; unavoidable blocker exception | Source of Truth and roadmap explicit blocker/deviation rule; Goal conflict must be resolved |
| 9 | Laravel backend is sole business authority | Existing architecture retained; parallel-backend prohibition explicit |
| 10 | Internal React/TypeScript/Inertia/Tailwind POS | Existing backend boundary and MT-4 preserved |
| 11 | Separate Next.js Website | Existing website boundary and MT-5 preserved |
| 12 | Website consumes defined Laravel REST APIs | Existing MT-3.4 and Website contracts retained |
| 13 | No Node/Express or other business backend | Explicit Source of Truth/AGENTS/roadmap guard added |
| 14 | One master MySQL | Existing data authority retained |
| 15 | No dual authoritative databases/two-way sync | Existing migration prohibition retained |
| 16 | All shared transactional records one authority | Existing requirement map, MT-2 and MT-3 retained |
| 17 | POS changes reach Website through shared API | Existing transactional flow and MT-5.1 freshness acceptance retained |
| 18 | Website orders processed through shared backend | Existing transactional flow and MT-2.7/MT-5.3 retained |
| 19 | Preserve integrations, auth, CMS, commerce, POS and Dynamic Platform | Existing requirement map/MT-1.1/MT-7.5 retained; preservation rule clarified |
| 20 | Controlled MySQL data migration; preserve relationships/constraints/IDs | Existing MT-1.2, MT-2.1 through MT-2.7 and MT-7.1 retained; explicit data invariant added |
| 21 | Redis only for justified useful roles | Source of Truth and MT-1.3 clarified; no blanket infrastructure mandate |
| 22 | Incremental parity/data/integration/regression | Roadmap execution rules explicit; no deferral of all checks until final audit |
| 23 | Conventional maintainable design; avoid needless microservices | Explicit Source of Truth/AGENTS simplicity guard |
| 24 | Logical app separation; coordinated shared resources | Existing structure retained; Source of Truth clarifies shared tooling/CI/commits |
| 25 | One root brand; no duplicated Brand Kits | Existing brand boundary and MT-6.1 retained |
| 26 | Approved master/reference branding assets | Existing brand ownership and asset acceptance retained |
| 27 | Framework runtime derivatives allowed | Existing runtime-copy/master distinction retained |
| 28 | One canonical tools/mobist-control | Existing Control path and MT-6 retained |
| 29 | Consolidate useful legacy Control implementation | MT-6.2 scope clarified; no old app copies migrated now |
| 30 | Current approved logo instead of old legacy logo | Explicit MT-6.2/current-logo gate; approval inventory remains pending |
| 31 | New backend and Website paths | Existing exact paths retained in Goal/Preferences/authority docs |
| 32 | Start/Stop/Restart/Open/Status/Start All/Stop All | MT-6.2/MT-6.3 now name group actions and justified applicability |
| 33 | Detect existing servers; avoid duplicates | Existing process-ownership checks retained |
| 34 | Avoid duplicate browser tabs/windows | Existing MT-6.3 browser deduplication retained |
| 35 | Windows local; Linux/Nginx future production | Existing environment distinction retained |
| 36 | Inspect legacy file/folder role before retention | MT-1.1 and Source of Truth explicit dependency/retention assessment |
| 37 | Remove duplication without feature/asset loss | Existing parity/retirement gate clarified |
| 38 | Verify functionality/builds/data/integration/user flows | Existing per-point and MT-7 gates retained; incremental checks explicit |
| 39 | Follow global initialization rules, keep them out of Goal/Preferences | Originals preserved; control specs remain in registry/reference docs; collection gate corrected |

## Exact reconciliation scope

The canonical Preferences file was added with a Git byte-preservation attribute. README/AGENTS authority pointers were corrected. Source of Truth and roadmap became v1.1; project registry became MT-1.1. Universal baseline and VP:VERIFY semantics were unchanged in that checkpoint; only project authority/initialization-collection behavior was specialized. The Goal file and universal reference specification remained unchanged. Existing session-reload rules remained explicit; no unrelated registry refresh or global-reference-repository change was performed in this historical checkpoint.

All 32 point IDs, exact titles, dependency order and statuses were retained: only MT-0.1 was complete, 31 points remained pending, and the first pending point was MT-1.1. No point was added or removed. Roadmap and DOCX changed together for the approved requirements. Source baseline/fingerprint files remained unchanged historical evidence and were rechecked rather than overwritten.

## Verification and stop boundary

The hash/count/render evidence for that checkpoint is `docs/INITIALIZATION_VERIFICATION.json`; status/recovery position is `docs/PROJECT_IMPLEMENTATION_STATUS.md`. Verification covered all 39 Preference numbers, both supplied-file byte hashes, Markdown/Word/PDF content, roadmap IDs/dependencies/statuses and changed-file scope. Final source snapshot, clean new repository and live HEAD-to-remote-main equality were completion gates. No backend/website/brand/Control/CI implementation files were allowed to change.

The checkpoint stopped at `MT-1.1 - Source inventory and feature parity register` with Pending status and normal completion output. It remediated MT-0.1 and did not authorize MT-1.1 execution.
