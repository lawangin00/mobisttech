# MT-0.1 - Approved Preferences reconciliation

Date: 2026-08-31 | Scope: VP:VERIFY, MT-0.1 only

## Verified defect aur correction

Original initialization closure `28588c59463e8cf2f3a13be97df49ae3021ec83b` ne approved Preferences collect/register kiye baghair MT-0.1 complete mark kiya tha. Is verification mein user ki complete approved Preferences poori read karke `docs/PROJECT_PREFERENCES.md` mein byte-for-byte preserve ki gayi hain. Original SHA-256: `e37c3c2fd6a30ba7211ce73854c79501181b327a98ce7acafa5938ee9941d402`.

Already approved Goal unchanged hai: `7bce00947418d18151756ea7176b51546b0cbc8ae00c02fda3c1bf7c1344908f`. Goal aur Preferences mein architecture conflict nahi mila. Missing authority pointers, reuse-before-rewrite decision, justified infrastructure, incremental verification aur Control group actions ko initialization/control documentation mein reconcile kiya gaya. Koi source/application migration ya detailed MT-1.1 inventory execute nahi hui.

## Initialization decisions comparison

`C:\mobisttech` single Git root, backend/website/tools/mobist-control/brand/docs/.github layout, independent private `lawangin00/mobisttech`, Laravel-only shared backend, one master MySQL, internal Inertia POS aur separate API-consuming Next.js Website approved documents se compatible hain. Private visibility reasonable existing initialization choice hai; Preferences ne isay change nahi kiya. No repointing/recreation required.

Read-only preflight ne new local HEAD aur live remote main ko `28588c59463e8cf2f3a13be97df49ae3021ec83b` par equal paya; working tree clean thi. GitHub ne private visibility aur default main confirm kiya. Protected source snapshot comparison passed. Component paths mein sirf original .gitkeep placeholders hain; application runtime abhi nahi hai.

Current source-logo approval inventory, exact runtime versions, Redis role decisions aur data/API design pending implementation/design tasks hi hain. Preferences mein legacy Control logo older hone ka approved direction record hai; is verification ne logo asset migration ya new approval claim nahi kiya.

## All 39 approved Preferences coverage

Neeche original numbered Preferences ka complete coverage map hai. Represented ka matlab required control/acceptance documented hai, target functionality implemented nahi.

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

Canonical Preferences file added with Git byte-preservation attribute. README/AGENTS authority pointers corrected. Source of Truth and roadmap are v1.1; project registry is MT-1.1. Registry universal baseline and VP:VERIFY semantics unchanged; only project authority/initialization collection gate specialized. Goal file and universal reference specification unchanged. Existing session reload rules remain explicit; no unrelated registry refresh or global-repository change performed.

All 32 point IDs, exact titles, dependency order and statuses retained: only MT-0.1 complete, 31 pending, first pending MT-1.1. No point added or removed. Roadmap/DOCX changed together for these approved requirements. Source baseline/fingerprint files remain unchanged historical evidence and are rechecked, not overwritten.

## Verification and stop boundary

Current hash/count/render evidence is `docs/INITIALIZATION_VERIFICATION.json`; status/recovery position is `docs/PROJECT_IMPLEMENTATION_STATUS.md`. Compare all 39 preference numbers, both supplied-file byte hashes, Markdown/Word/PDF content, roadmap IDs/dependencies/statuses and changed-file scope. Final source snapshot, clean new repository and live HEAD-to-remote-main equality are completion gates. No backend/website/brand/Control/CI implementation files may change.

Stop at `MT-1.1 - Source inventory and feature parity register` with Pending status and normal completion output. This verification is remediation of MT-0.1, not authorization to begin MT-1.1.
