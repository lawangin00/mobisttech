# mobiST Tech - Project Implementation Status

Last reconciled: 2026-09-17

## Identity and authority

- Project: mobiST Tech
- Project ID: `282dba2f-a2d9-47e8-aa8d-e499fbe1706c`
- Project identity: `docs/PROJECT_IDENTITY.json`
- Repository root: `C:\mobisttech`
- Independent remote: `https://github.com/lawangin00/mobisttech.git` (private)
- Branch: `main`
- Canonical Goal: `docs/PROJECT_GOAL.md`
- Goal SHA-256: `7bce00947418d18151756ea7176b51546b0cbc8ae00c02fda3c1bf7c1344908f`
- Binding Preferences: `docs/PROJECT_PREFERENCES.md` (all 39 approved numbered Preferences)
- Preferences SHA-256: `e37c3c2fd6a30ba7211ce73854c79501181b327a98ce7acafa5938ee9941d402`
- Approved addendum: `docs/PROJECT_REQUIREMENTS_ADDENDUM_v1.1.md` v1.1
- Addendum SHA-256: `ec0947bac17002e6d003da31d53b2454d4cfb42be4c321b3b3097775613cccf5`
- Addendum traceability: `docs/REQUIREMENTS_ADDENDUM_v1.1_RECONCILIATION.md`
- Approved superseding requirement: `docs/PROJECT_REQUIREMENTS_UNIFIED_ADMIN_GOOGLE_v1.0.md`
- Superseding requirement SHA-256: `76fa2f903fa6e3e07912cfcdb76cb76e10f1ea1d8fd0331591234fd07f5bb458`
- Superseding reconciliation: `docs/remediation/RECONCILIATION.md`
- Approved Team Member/session requirement: `docs/PROJECT_REQUIREMENTS_TEAM_MEMBERS_SESSION_POLICY_v1.0.md`
- Team Member/session requirement SHA-256: `94b42b6462b8e0e70a85991a3609d3de0ef21bd8f3e87ddea600744b8803b472`
- Team Member/session reconciliation: `docs/team-members/RECONCILIATION.md`
- Approved consolidated documents/payments/legal/manual requirement: `docs/PROJECT_REQUIREMENTS_DOCUMENTS_PAYMENTS_LEGAL_MANUAL_v1.0.md`
- Consolidated requirement SHA-256: `b682fed17cf6d955c2df0cac8eb081b0b641602e2b7391f3137c2e979e69b1a1`
- Consolidated requirement reconciliation: `docs/consolidated-requirements/RECONCILIATION.md`
- Approved Software Product publishing requirement: `docs/PROJECT_REQUIREMENTS_SOFTWARE_PRODUCT_PUBLISHING_v1.0.md` v1.0
- Software Product publishing requirement SHA-256: `8ecf91807c2d0e838d1e2daf7ccfc130c522159e42ee5cef7d57d60c4276c452`
- Software Product publishing reconciliation: `docs/software-publishing/RECONCILIATION.md`
- Active Source of Truth: `docs/PROJECT_SOURCE_OF_TRUTH.md` v1.10
- Active roadmap: `docs/PROJECT_IMPLEMENTATION_ROADMAP.md` v1.10
- Roadmap Word mirror: `docs/PROJECT_IMPLEMENTATION_ROADMAP.docx`
- Project registry: `docs/AI_PROJECT_COMMAND_REGISTRY.md` MT-1.1-r15 (universal 1.20 / system 7.7)
- FINAL-AUDIT: `FINAL-AUDIT - Independent final project audit`

System 7.7 preserves the Git-backed bootstrap loading path and durable project/session binding. Bootstrap 1.3 / Registry 1.20 harden known canonical-path loading: an exact-path read failure is treated as a read-method/tool failure, gets at most one alternate exact-path read-only retry, and cannot trigger broad repository/tree search or a false absence/moved claim without authoritative exact-snapshot proof. Registry 1.20 retains storage-bound visual-QA routing, scoped literal-safe command construction and standardized Refresh output. It also hard-caps routine successful `Y` / `Proceed` completion to 3 protocol lines (4 only when a stage completes) and appends a local `h:mm AM/PM, d-MMM-yy` timestamp to every project-control response; ordinary non-project chat remains untimestamped. Refresh continues to report `Registry refreshed: Universal <version> | Project <project-version>` so a project-delta revision cannot be mistaken for the Universal Registry version. This control-plane patch does not reinitialize the project, reopen a completed checkpoint or advance application implementation.

## Verified position

Active stage: MT-2 - Shared backend and transactional migration (In Progress)

Last completed stage: MT-1 - Migration inventory and design

Current In Progress point: None

Status: MT-0 and MT-1 complete. MT-2 is in progress. 21/56 complete, 35 pending. MT-2.14 is complete; MT-2.17 is pending.

Last completed point: MT-2.14 - Promotion and coupon services

Next pending point: MT-2.17 - Validated bulk data workflows

Execution boundary: Backend/Website foundations, shared MySQL schema/infrastructure, unified Admin/customer authorization, Team Member Role/delegation authority, centralized Admin/Customer session policy, product/master-data, inventory/acquisition/stock transactions, internal POS sale/invoice/accepted-return authority, warranty/claim authority, supplier/purchase-order/partial-receipt authority, reorder recommendations, stocktake/cycle-count authority, inter-outlet transfer/custody authority, POS Payment Methods/Destinations and split-tender/refund/settlement authority, canonical business profile and secure Google integration/backup services exist. Addendum schema primitives, capability/financial-reference/retention contracts, explicit permissions and custody-aware stock exclusion are integrated. Identity/product/stock/sales/claim/procurement/stocktake mapping uses synthetic rows only. Transfer verification also uses synthetic target rows only. Real MySQL stock, return, claim, procurement, stocktake, transfer and POS payment concurrency and the existing `mobisttech-drive:` temporary read/write/delete check are verified. Canonical Invoice/Warranty delivery, optional sale-email capture, final legal/policy content, the reusable Software Product Admin/CMS/publication model, licensing/notice review and the complete product user manual are approved future work at their named points. Actual private-data migration, authentic Gmail OAuth consent/test send, production Google verification, authentic provider payment/refund execution, reset/publication workflows, transfer HTTP/POS interfaces and complete POS/Website interfaces, rendered warranty documents, brand, Control and CI remain pending. Backend tests do not constitute live Gmail/provider, payment/refund, complete UI, rendered-document or private-data import acceptance.

Approved addendum v1.1 expands required work without reinitialization. MT-2.8 implements its prerequisites only; mode publication/reset/retail/digital feature workflows remain pending at their named points.

## Documentation language policy

Roman Urdu is the default only for assistant chat/UI communication with the user. Git-tracked project documentation and technical artifacts use standard English unless the user explicitly requests another language for a specific artifact. User-supplied Goal and Preferences remain byte-preserved in their original language/content unless transformation is explicitly authorized. This boundary is recorded in Source of Truth, roadmap, AGENTS, project registry and the synchronized Universal Registry 1.20 / System 7.7 baseline loaded through the canonical control bootstrap.

## Roadmap lifecycle policy

The canonical roadmap is a structural execution plan, not the live progress tracker. This implementation ledger is authoritative for Completed/In Progress/Pending state, last/current/next point and progress counts. Routine point or stage progress must update this ledger only and must not edit the roadmap or regenerate its Word mirror.

The roadmap Markdown and same-basename DOCX are regenerated/verified only when the roadmap itself changes structurally or materially, such as approved scope/requirement changes, dependencies, acceptance intent, HOLD/Deferred constraints, remediation points or stage restructuring. This one-time v1.5 normalization removed live point/stage status markers from the roadmap while preserving all 32 point IDs, exact titles, dependencies, scopes, acceptance criteria and HOLD items.

The one-time normalized Word mirror contains 32 points, 32 dependency lines and 166 visible content blocks. Microsoft Word rendered 13 pages and all 13 were visually inspected cleanly. Global Registry 1.9 / Roadmap Specification 1.1 is committed in `lawangin00/references` at `5466f3804e05325b75f63100f4539479a6250dde`; its v7.2 reference DOCX rendered 15 pages and all 15 were visually inspected cleanly. Evidence is recorded in `docs/ROADMAP_LIFECYCLE_OPTIMIZATION_VERIFICATION.json`.

The v1.9 structural roadmap and mirror contained 56 points, 56 dependency lines and 271 visible content blocks. The generated DOCX matched every Markdown body block, rendered 27 pages, and all 27 pages were visually inspected without clipping, overlap, missing text or broken page furniture. That historical structural evidence remains recorded in `docs/consolidated-requirements/STRUCTURAL_RECONCILIATION_VERIFICATION.json`; MT-2.9 was the last completed implementation point at that checkpoint.

The current v1.10 structural roadmap and mirror retain the same 56 point IDs and 56 dependency lines with 273 visible content blocks. Markdown/DOCX body parity passes; the mirror renders as 23 pages and all 23 pages were visually inspected without clipping, overlap, missing text or broken page furniture. Current Software Product publishing structural evidence is recorded in `docs/software-publishing/STRUCTURAL_RECONCILIATION_VERIFICATION.json`. This requirement reconciliation did not itself advance application implementation; at that structural checkpoint the live position was 18/56 complete with MT-2.12 pending after the separately verified MT-2.20 closure.

## Initialization evidence

The original supplied Goal was read in full and persisted in `docs/PROJECT_GOAL.md` with unchanged bytes. The source document remains preserved. Authority documents, requirement map, registry, 32-point roadmap and same-basename Word mirror were established during initialization.

Protected source refs, file/status state and live remote `main` equality are recorded in `docs/SOURCE_BASELINE.md`; tracked source fingerprints are in `docs/SOURCE_SNAPSHOT.json`. Source tests/builds were intentionally not run in the original working directories because they could mutate source caches/state.

Original Word verification recorded 32 unique point IDs, 32 status blocks and 171 visible content blocks. Preferences reconciliation retained the same 32 IDs/statuses and produced 173 visible content blocks. The later MT-1.1 roadmap v1.2 contained 174 content blocks. These are historical checkpoint records, not current hashes after the documentation-language reconciliation.

An independent private GitHub repository `lawangin00/mobisttech` was created with default branch `main`. Initial and subsequent checkpoint commits were pushed only to that new remote; no force-push or source-remote change occurred.

Final source snapshot checks at initialization and MT-1.1 confirmed original source HEADs, branches, remotes, clean status and all 824 POS + 400 Website tracked-file byte fingerprints remained unchanged. The canonical Goal is protected by Git byte-preservation attributes. Source secrets/databases/dependencies were not copied.

Application tests/builds: not applicable to documentation-only initialization/reconciliation. Fresh target backend/frontend/data/Control gates remain pending roadmap points. Fresh isolated source characterization under MT-1.1 is source evidence, not target acceptance.

## MT-0.1 Preferences re-verification

Original completion `28588c59463e8cf2f3a13be97df49ae3021ec83b` did not collect/register Preferences. The user's explicit VP:VERIFY authorized remediation of that initialization gap only. The complete approved file was persisted byte-for-byte in canonical `docs/PROJECT_PREFERENCES.md`; Goal remained unchanged. All-39 coverage and decision comparison are recorded in `docs/INITIALIZATION_PREFERENCES_RECONCILIATION.md`.

Corrected controls included joint Goal/Preferences authority, the initialization collection gate, reuse/adapt/refactor/migrate-before-rewrite, justified Redis/conventional architecture, prohibition of a parallel Node/Express business backend, incremental parity/data/integration/regression checks, inspected legacy-file retention and current-logo/Start All/Stop All Control acceptance.

No roadmap point, dependency, status or title changed in that remediation. Backend, Website, brand, Control and `.github` runtime/CI placeholders remained unchanged.

## MT-1.1 source inventory closure

On user Proceed, only MT-1.1 executed. `docs/migration/SOURCE_FILE_INVENTORY.json` records all 824 POS + 400 Website tracked paths/blob hashes/retention decisions. `SOURCE_SYMBOL_INVENTORY.json` records 316 application routes, 42 models, 79 migrations, 2,368 source method declarations, registry keys, command declarations and family traceability; unmapped files are zero. `FEATURE_PARITY_REGISTER.md` records 22 families with reuse/adapt/refactor/migrate decisions and target-pending gates.

No runtime command was executed against the originals. Fresh locked dependencies were installed only in ignored isolated pinned exports. With synthetic environment, in-memory SQLite, fake storage/mail/HTTP, disabled integrations and Vite bypass, POS 202 tests/2,695 assertions and Website 236 tests/2,806 assertions passed with zero errors/failures/skips. `CHARACTERIZATION.md` records exact boundaries, the corrected initial harness-key error, commands and limitations. `MT_1_1_VERIFICATION.json` records all 438 test cases and evidence hashes. This is source characterization, not target test/build acceptance.

Verified inventory risks include: POS outlet `User` versus Website customer/admin `User` collision; copied catalogue/order transport retirement requires transaction parity; source stock-return behavior exists but no routed full sale-refund flow was found; Control port-based taskkill needs target process ownership; duplicate brand/Control copies and unrelated unreferenced school-template partials are selective-retention candidates. No valid capability was retired in MT-1.1. Required return acceptance remains explicit under MT-2.5; detailed schema/API design was pending at that checkpoint and is now recorded in the MT-1.2 closure below.

## Documentation language reconciliation

The user clarified that Roman Urdu applies only to assistant chat/UI communication and was never intended as the language of repository documentation. This reconciliation corrected that interpretation without advancing roadmap work.

Project-generated narrative documentation was converted to standard English: `README.md`, `AGENTS.md`, `docs/PROJECT_SOURCE_OF_TRUTH.md`, `docs/PROJECT_IMPLEMENTATION_ROADMAP.md`, its synchronized DOCX mirror, `docs/PROJECT_IMPLEMENTATION_STATUS.md`, `docs/AI_PROJECT_COMMAND_REGISTRY.md`, `docs/SOURCE_BASELINE.md` and `docs/INITIALIZATION_PREFERENCES_RECONCILIATION.md`. Existing English migration evidence remained unchanged unless control metadata required reconciliation. The approved Goal and Preferences remain byte-identical and were not translated.

At that checkpoint, Source of Truth and roadmap became v1.3. The project registry is MT-1.1-r2 and deliberately synchronizes Universal Registry 1.8 / System 7.2. Roadmap task IDs, exact titles, dependencies and statuses remain unchanged: 2/32 complete, 30 pending, next `MT-1.2 - Unified data, API and security design`. No application/runtime point was executed.

The regenerated roadmap Word mirror verifies 32 stable point IDs and 175 visible content blocks. Microsoft Word rendered 13 pages; all 13 pages were visually inspected with no clipping, overlap, broken tables or missing content. Historical reconciliation hashes and language-scan results are recorded in `docs/DOCUMENTATION_LANGUAGE_VERIFICATION.json`.

## MT-1.2 unified design closure

On user Proceed, only MT-1.2 executed. `docs/design/README.md` indexes the unified schema, identity/product/order mappings, customer merge rules, immutable history, exact money, transaction/return/refund contracts, REST/auth boundaries, cache invalidation and rollback/encrypted recovery. Source mappings cover 58 source-qualified tables, 42 models, 79 migrations and 316 route dispositions. All target implementations remain Pending.

OpenAPI 3.1.1 validates 36 operations. Design verification passes 18 positive/negative schema examples and 486 specification event traces, plus inventory coverage, security metadata, unchanged target placeholders and approved-input hashes. The 34 assigned implementation acceptance cases remain pending; no MySQL concurrency, application migration, runtime build or provider acceptance is claimed. Source tests were not rerun.

Source of Truth and roadmap are v1.4; only MT-1.2 changes to Completed, with all 32 IDs/titles/dependencies retained. The same-basename DOCX is regenerated and verified in this checkpoint; detailed parity, render and boundary evidence is in `docs/design/CHECKPOINT_VERIFICATION.json`. Original repositories remain unchanged. Intended synchronization is only the new repository main branch and its private origin; closure requires clean HEAD/upstream/live-main equality.

## MT-1.3 Windows foundation closure

On user Proceed, only MT-1.3 executed. The target backend selectively reuses the standard Laravel foundation and adds React/TypeScript/Inertia/Tailwind; the separate Next.js/React/TypeScript/Tailwind Website consumes the fixed loopback Laravel API. Exact runtime/package versions, reuse decisions, ports, setup and maintenance boundaries are in `docs/foundation/WINDOWS_SETUP.md` and `MT_1_3_VERIFICATION.json`.

A checksum-verified portable MySQL 8.4.11 instance uses a new target-only data directory, loopback port 13306 and independently generated credentials. Only framework cache/jobs/sessions tables exist in the local/test schemas. Source database/URL/socket/identity overrides and external Laravel HTTP calls are rejected by local/test guards. No source environment, secret, business-data export or existing service was used. Redis has an isolated reserved derived-cache/throttling role but is not running; file cache is the explicit foundation default. The S3 adapter is configured but disabled with blank credentials and private local storage selected.

Fresh target verification passed: 15 tests/44 assertions, real MySQL identity/runtime schema and database session checks, private storage/cache probe, optimize/clear, PHP format/platform/lock checks, clean npm lockfile installs, POS TypeScript/Vite build, Website lint/typecheck/Next.js build and fixed API proxy health. No business or legacy integration route is exposed. MySQL duplicate-start, owned status, graceful stop and restart were verified. Dependency audits reported no known vulnerabilities. The compatible ESLint 9 lock has an upstream deprecation notice; ESLint 10 was rejected after confirmed plugin incompatibility, documented for pre-release re-evaluation.

Visual/browser smoke was blocked by Browser Use URL policy; no bypass or browser acceptance claim was made. Required foundation build/configuration/connectivity gates passed; later UI/browser/functional parity gates remain pending. An initial smoke-server directory error, a test helper method error and a Windows file-lock retry were corrected and the affected non-browser gates rerun successfully.

MT-1 is complete (all three points verified). Only this ledger owns live progress. Roadmap Markdown/DOCX, Source of Truth, registry, approved Goal/Preferences and historical checkpoint evidence are unchanged; no Word regeneration is required. Commit/push and final clean HEAD/upstream/live-origin equality are required only for the new monorepo. No MT-2.1 business schema work starts in this checkpoint.

## MT-2.1 shared schema and infrastructure closure

On user Y, only MT-2.1 executed. `docs/schema/README.md` records reuse decisions, source-to-target transformations, infrastructure behavior, reproduction and limitations. The 79 pinned source migrations were applied only in isolated in-memory exports, producing schema metadata for 58 source-qualified tables and 735 final columns. Every final column has an explicit destination/disposition; source migration hashes and 116 invalid row-shape rejection cases pass. No source rows, environments, secrets, databases or runtime services were accessed.

Two target Laravel migrations add 62 tables to the existing seven framework tables: 69 tables, 894 columns, 117 foreign keys and 317 indexes. Fresh local and disposable test MySQL 8.4.11 apply succeeds. Test rollback leaves exactly seven framework tables; reapply restores the identical normalized schema hash. Strict SQL, UTC, InnoDB, complete column types/nullability, FK destinations, indexes, exact money, active IMEI/allocation uniqueness, cross-outlet restrictions, payment identity, historical RESTRICT behavior and migration identity constraints pass. Synthetic business rows are cleaned; actual source-data import remains pending.

Database queue dispatch waits for commit; real workers demonstrate rollback discard, success, retry and terminal failed-job evidence. The durable event/version schema rolls back atomically. Versioned public-cache infrastructure consults MySQL first and falls back on Redis failure without retrying the business callback. Private object checks cover integrity, namespace/path rejection, public-route isolation and overwrite rejection; the actual S3 adapter verifies private bucket/prefix behavior and propagates failure using a network-free SDK handler. No live Redis/S3 or provider activation is claimed, and business event consumers, object authorization/content validation and recovery remain their later feature gates.

Fresh target gates pass: 33 backend tests / 3,714 assertions; POS TypeScript/Vite build; Website lint/typecheck/Next.js build; PHP syntax, Pint, Composer validation/platform requirements, optimize/clear and MySQL/cache/private-storage probe. Current Composer and both npm audits report zero known advisories. `docs/schema/MT_2_1_VERIFICATION.json` records detailed evidence and artifact hashes. A schema-inspection harness initially enumerated both allowed target databases; it was corrected to the exact disposable schema, made to exit nonzero on failure, and rollback/reapply gates were rerun successfully. No business route, credential/customer migration or MT-2.2 implementation was introduced.

Original source Git/file fingerprints remain unchanged. Goal/Preferences, Source of Truth, registry and roadmap Markdown/DOCX are unchanged; this routine progress checkpoint does not require Word regeneration. The intended checkpoint is only the new monorepo main branch/private origin, with clean local/upstream/live-main equality verified after commit/push.

## MT-2.2 identity, customer and authorization closure

On user Y, only MT-2.2 executed. `docs/identity/README.md` records source reuse, exact identity dispositions, HTTP/session/recovery contracts, import boundaries and reproduction. Four scoped credential realms isolate customer, POS admin, POS superadmin and Website administration. POS outlet users remain outlets with server-selected operator context, never customer credentials. Source permission defaults and Website role matrices are preserved; runtime null/unknown roles deny, while a proven imported legacy admin receives an explicit audited owner mapping.

Laravel session/CSRF/password-broker components provide encrypted, distinct realm cookies, explicit origin/host checks, session rotation, safe DTOs/errors and rate limits. POS device/network limits and session replacement are adapted with account-row locking. Password reset/change rotates remembered credentials and auth versions; old sessions and reset-token replays are rejected. Recovery is non-enumerating and defaults to disabled delivery; notification-fake tests send no real email. Login/reset interfaces and live transport acceptance remain later gates.

The target-only identity importer validates complete known column contracts, password hashes, roles, permissions, parent identities and timezone conversion. Source-qualified IDs prevent outlet/credential collisions; changed replay, duplicate identities, invalid data and unresolved membership quarantine without partial accounts. Customer ownership uses explicit Website account identity, never matching email/mobile or guest contact. DCASE-01 through DCASE-04 have fresh target tests; no source data or source browser/remembered credentials were imported.

The identity migration yields 73 tables, 916 columns, 117 foreign keys and 326 indexes. One-step rollback on the empty disposable test database restores the exact MT-2.1 normalized schema hash; reapply restores the identical identity hash. Local/test migrations apply successfully; zero synthetic business rows remain. Fresh target verification passes 55 backend tests / 4,080 assertions, POS TypeScript/Vite build, Website lint/typecheck/Next.js build, Pint, Composer validation/platform checks, optimize/clear, source schema lineage checks and current Composer/npm audits with zero known advisories. Detailed cases, schema hashes and checked artifacts are in `docs/identity/MT_2_2_VERIFICATION.json`.

HTTP-kernel tests enforce real CSRF and cookie isolation; browser/UI acceptance is not claimed, and the earlier Browser Use URL denial was not retried or bypassed. Original Git/file fingerprints remain unchanged. Goal/Preferences, Source of Truth, registry and roadmap Markdown/DOCX remain unchanged, with no Word regeneration required. The checkpoint synchronizes only the new monorepo main/private origin, followed by clean local/upstream/live-main verification. MT-2.3 is not started.

## MT-2.3 product and master-data closure

On user Y, only MT-2.3 executed. `docs/catalog/README.md` records source-to-target reuse, definition/attribute/permission contracts, strict source mapping, public/private field separation and remaining boundaries. Source registries, master-data lifecycle/usages, category/SIM/unit constants, stable business identifiers and variant grouping were adapted. Protected category codes and canonical status/source options remain protected; label edits preserve raw historical values and existing inactive references while blocking new inactive selections.

Authorized backend services create zero-stock product definitions and edit managed unit attributes with fresh outlet/grant checks, explicit input allowlists, exact decimal prices, option locks, usage maintenance and atomic publication events. Clients cannot supply stock counters, business identifiers or actor/outlet identity. Existing holds block definition/attribute edits; category/tracking changes cannot silently invalidate stock/unit history. No acquisition, active-IMEI, sale/reservation or stock-movement processor is introduced.

The controlled importer supports five source-qualified product/master-data tables, preserves complete mapped columns and remaps option/actor/outlet/product/historical references. Invalid shapes/types/money/timezones, unresolved parents, changed replay and identity collisions quarantine atomically. Website candidate links require matching canonical product/outlet/category/contract; cached Website stock/price is retained only as legacy listing metadata and never becomes inventory authority. Only synthetic data was used; historical dependencies without a mapped parent remain explicit reconciliation work, not silently cleared references.

Fresh target gates pass: 72 tests / 4,270 assertions, including seventeen new product/master-data/migration cases and DCASE-07 coverage; nine source constant groups and eleven isolated source formatting examples; POS TypeScript/Vite; Website lint/typecheck/Next.js; Pint; Composer validation/platform checks; optimize/clear; current Composer/npm audits with zero known advisories. MySQL remains exactly the MT-2.2 73-table schema/hash with zero synthetic business rows. Product/import/publication transaction rollback passes. Detailed source lineage, cases and artifact hashes are in `docs/catalog/MT_2_3_VERIFICATION.json`.

Original source Git/file fingerprints and approved Goal/Preferences remain unchanged. No Browser Use denial was bypassed and no UI/provider/private-data acceptance is claimed. Source of Truth, registry, roadmap Markdown/DOCX and historical evidence remain unchanged; routine progress does not require Word regeneration. Intended synchronization is only the new monorepo main/private origin, verified by clean local/upstream/live-main equality after commit/push. MT-2.4 is not started.

## MT-2.4 inventory and stock integrity closure

On user Y, only MT-2.4 executed. `docs/inventory/README.md` records source reuse, application boundaries, current locking reads, stock/IMEI/hold invariants, acquisition evidence privacy and offline migration/reconciliation. Source acquisition validation and normalization, managed buying-source usages, physical unit identifiers, variant grouping, adjustments, active allocations and stock movements are adapted into shared services. No source controller/runtime or cross-repository stock HTTP transport is activated.

Authorized inventory operations enforce fresh outlet/grant checks, strict input fields, exact cost strings, receipt/unit creation, global active IMEI claims, unit versions, hold-aware corrections/retirement/archive, actor-derived audit and durable idempotency. Stock, history, audit and publication changes roll back together. Private acquisition images use scoped authorization, content/size checks and immutable private storage outside stock transactions; source paths and public URLs are rejected. Unattached private objects require later audited retention handling.

Internal sale/reservation stock adapters require the owning business transaction and validate persisted relationships. Current product/unit/claim/allocation locks remain correct after earlier repeatable-read queries. Complete unheld units or available aggregate quantity can be allocated/consumed once; expiry and COD states differ, and release after consumption never restores stock. Full invoice creation, financial returns/refunds, payment acceptance, checkout orchestration and customer-facing endpoints remain their later points. MT-2.5 has not started.

The stock importer maps three additional source-qualified tables, rebuilds active claims without erasing historical occurrences, preserves exact costs/timestamps and quarantines unresolved references, duplicate claims, changed replay, invalid arithmetic and private source paths. Reconciliation checks physical quantity/claims/holds and movement continuity/final balance, explicitly requiring baseline approval for missing history. No source data was imported and no historical balance was fabricated.

Fresh target gates pass: 89 tests / 4,511 assertions, including seventeen new cases and three independent-process MySQL races. Sale versus reservation, duplicate physical allocation and competing cross-product IMEI claims each permit one winner and roll back the loser. Other checks cover exact money/replay, authorization/input injection, incomplete units, versions/claims, reserved-unit consumption, expiry/COD, release-after-consumption, historical duplicates, outer rollback, document privacy and import/reconciliation. POS TypeScript/Vite, Website lint/typecheck/Next.js, Pint, Composer validation/platform checks, optimize/clear, source schema contracts and current Composer/npm audits pass. Details and checked artifacts are in `docs/inventory/MT_2_4_VERIFICATION.json`.

MySQL remains exactly the MT-2.2 schema/hash with 73 tables and zero synthetic business rows. The initial Windows sandbox worker-output wait was replaced with bounded polling and rerun in approved process context; only owned test workers/fixtures were cleaned. Original source Git/file fingerprints and approved inputs remain unchanged. No browser denial was bypassed; no UI/provider/private-data acceptance is claimed. Source of Truth, registry, structural roadmap/DOCX and historical evidence remain unchanged; no Word regeneration is required. Intended synchronization is only the new monorepo main/private origin, followed by clean HEAD/upstream/live-main equality and artifact checks.

## Approved addendum v1.1 reconciliation - 2026-09-01

Applied the approved supplemental source without changing Goal/Preferences, rerunning initialization, implementing MT-2.8/MT-2.5 or reopening completed checkpoints. Source of Truth and roadmap are v1.6; registry MT-1.1-r4 adds authority/traceability pointers only, with unchanged universal alias semantics. The full 41-leaf-clause map, current schema/code dependency evidence, reuse decisions, optional-feature boundaries and exclusions are in `docs/REQUIREMENTS_ADDENDUM_v1.1_RECONCILIATION.md`.

All 32 original roadmap IDs/titles survive and the first eight completed point blocks remain unchanged. Twenty new points create 52 total / 8 completed / 44 pending. New MT-2.8 precedes MT-2.5 to resolve additive capability/custody/procurement/money/milestone/reset-preservation contracts against current code. Later retail, digital, mode publication, safe reset and interface work stays in dependency-appropriate stages. Explicit roadmap order, not numeric sorting, governs execution. P1/P2/P3 and optional disablement never imply unapproved deferral.

This is one structural roadmap update and one same-basename DOCX generation. Markdown/Word parity passes for 52 points and 249 content blocks; Microsoft Word rendered 21 pages, all visually inspected without clipping or overlap. Dependency/coverage checks, rendered-page QA, original-input hashes, unchanged application/source fingerprints and committed control-artifact hashes are recorded in `docs/REQUIREMENTS_ADDENDUM_v1.1_VERIFICATION.json`. No application tests/builds or database/services were run; prior target acceptance remains historical evidence. Commit/push synchronization is only the new monorepo main/private origin; no source runtime or remote mutation is permitted.

## MT-2.8 addendum foundation closure - 2026-09-01

On user Proceed, only MT-2.8 executed. `docs/addendum/README.md`, `CONTRACTS.json` and the complete 41-clause `ENTITY_API_PERMISSION_MATRIX.json` define additive schema, reserved APIs, exact permissions, ownership, stock custody, money/milestone identity, reset preservation and future consuming-point contracts. Planned API names are not routed endpoints. No transfer/procurement/reset/promotion/payment/publication or Website interface workflow was enabled.

One additive migration creates seven companion tables while preserving all 73 existing table definitions, original migrations and schema/source mappings. Both isolated local and test schemas have 80 tables, 962 columns, 129 foreign keys and 349 indexes, identical normalized schema hash and zero business rows. The hard-guarded test-schema lifecycle rehearsal passed two rollback/reapply cycles, unchanged original definitions and a synthetic original row, plus refusal of populated rollback before any table drop.

Current stock availability combines reservation and custody holds; future transfers cannot expose held stock to sales, adjustments, IMEI edits or definition changes. Active IMEI claims stay at the held origin during transit; a forward-only successor relation preserves historic identity. Exact-money reference primitives are append-only/idempotent, keep trade-in tender distinct from discounts, reject changed replay and prevent milestone over-allocation or new allocation on paid/expired quotes. They do not implement financial engines. Website capability reads validate the published revision/version and default to unpublished/off; no public mode route is created. Explicit new permissions preserve old POS defaults and Website roles; mode publishing is separate from editor preview and reset is a global superadmin capability with later execution safeguards still required.

Fresh full backend verification passed 105 tests / 4,733 assertions, including all 16 addendum tests and existing independent-process stock races. Focused addendum verification passed 16 tests / 154 assertions. MySQL FK/unique/check constraints and rollback, original schema/source lineage, Pint, Composer/platform, optimize/clear, POS TypeScript/build and Website lint/typecheck/production build passed. All-table retention classification rejects unknown domains and identifies preserved inventory-to-financial FK barriers without deleting anything. `docs/addendum/MT_2_8_VERIFICATION.json` records exact test cases, command/log hashes, schema/contract evidence and checkpoint artifacts. No future public-page performance or browser acceptance is claimed.

Source fingerprints, approved Goal/Preferences/addendum, Source of Truth, registry, roadmap Markdown/DOCX and historical evidence remain unchanged. No Word generation is needed for routine progress. No source runtime/data/remote, secret, provider, production action or browser-policy bypass occurred. The owned target MySQL is restored to its initial stopped state after verification. Intended synchronization is only the new monorepo main/private origin, followed by clean HEAD/upstream/live-main and artifact checks. MT-2.5 remains not started.

## MT-2.5 sales, invoices and returns closure - 2026-09-01

On user Y, only MT-2.5 executed. `docs/sales/README.md` records pinned source traceability, reuse/refactor decisions, server-owned customer/sale/invoice behavior, exact-money allocation, immutable snapshots, the explicit accepted-return contract, stock/accounting effects, offline import and remaining boundaries. The source stock-only return operation was not misrepresented as refund parity. No route/UI, payment collection, refund execution, warranty/claim workflow or private source-data import was enabled.

`SalesOperations` now owns atomic outlet-authorized sales and accepted returns. It uses server product prices/costs, locked document sequences, explicit operational-customer identity, immutable invoice/business/salesperson/warranty/product snapshots, exact deterministic discount allocation and the MT-2.8 monetary-adjustment reference. The existing stock transaction authority consumes inventory in the same transaction. Idempotency, audit, invoice/sale rows, unit/IMEI retirement, stock movement, counters and publication effects commit or roll back together.

Returns preserve original invoice and sale values. Cumulative returned quantity prevents excess acceptance; immutable return lines store exact gross/discount/net/cost evidence and a snapshot digest. Sellable returns restore stock, while damaged/quarantined returns remain unavailable. Serialized returns create a forward successor occurrence, preserve the sold source and IMEI history, and restore the active IMEI claim only on the sellable successor. A real two-connection race accepts exactly one request for the last returnable quantity. The response records refund due but writes no refund/payment/cash/provider record; MT-2.7 remains the actual refund authority.

The additive migration keeps 80 tables and yields 975 columns, 132 foreign keys and 355 indexes with normalized schema hash `1abc1aa3023796d80dc82ecd477c7b3e2a0891f3b4b22a7ce2bbc6ffcd31f80c`. Empty-schema rollback/reapply succeeds after an initial rehearsal exposed and corrected check-constraint drop ordering. `SalesImporter` strictly migrates complete synthetic POS invoice/sale rows with source-qualified parents, UTC conversion, signed historical profit, exact arithmetic, stable identities, replay and quarantine; contact snapshots never infer a customer account.

Fresh full backend verification passes 111 tests / 4,840 assertions. Focused sales/migration/concurrency verification passes 6 tests / 80 assertions. MySQL lifecycle/schema, source-schema/fingerprint checks, Pint, Composer validation/platform requirements, optimize/clear, POS TypeScript/Vite build and Website lint/typecheck/Next.js build pass. Online Composer/npm advisory refresh was attempted but blocked by the execution security policy because it would disclose private dependency metadata to public registries; dependency locks did not change and no fresh audit result is claimed. `docs/sales/MT_2_5_VERIFICATION.json` records exact evidence and artifact hashes.

Approved Goal/Preferences/addendum, Source of Truth, registry and structural roadmap Markdown/DOCX remain unchanged, so Word regeneration is neither required nor performed. Protected originals remain unchanged. The owned target MySQL must return to its initial stopped state after final Git checks. Intended synchronization is only the new monorepo main/private origin. MT-2.6 remains not started.

## MT-2.6 warranty and claim closure - 2026-09-01

On user Y, only MT-2.6 executed. `docs/warranty/README.md` records pinned P05 traceability, reuse/adaptation decisions, versioned warranty clauses, immutable sale-time eligibility, lifecycle rules, strict migration and remaining document/UI/payment boundaries. No route, interface, document renderer, payment/refund operation, paid-repair workflow or private source-data import was enabled.

`WarrantyClauses` validates and publishes canonical ordered clause revisions under `config.documents.manage`, with version, digest, audit and idempotency evidence. `SalesOperations` now writes that immutable clause snapshot to invoices. `ClaimOperations` authorizes fresh outlet access, calculates exact inclusive UTC expiry from the sale-time warranty line, bounds quantity by accepted returns and active claims, requires the exact sold/unreturned occurrence for serialized units and records a complete historical output payload. Current product or clause changes cannot rewrite existing coverage evidence.

The explicit claim lifecycle is enforced in an atomic transaction. Every accepted transition appends an actor-attributed, sequenced, hash-verified event snapshot; terminal and invalid transitions fail. A generated active-unit key and MySQL unique constraint prevent simultaneous active jobs for one physical unit. A real two-connection race accepts exactly one claim for the last eligible quantity. Historical null-sale claims remain readable/transitionable with an explicit unresolved marker but cannot establish new coverage.

The strict `ClaimImporter` validates the complete pinned source row shape and source-qualified invoice/sale/product/unit/actor maps. It preserves stable claim identity, lifecycle timestamps, operator text and history; identical replay is stable, while changed, unresolved and invalid rows quarantine without partial claims. Actual private-data migration remains MT-7.1.

The additive migration yields 81 tables, 990 columns, 134 foreign keys and 361 indexes with normalized schema hash `10bc28f7fee21948a7099fa1d3e0d9d67a5550ab1742d87150512e8934e1511e`. Empty-schema rollback/reapply succeeds. Fresh verification passes 120 backend tests / 4,960 assertions; focused warranty/claim/migration/concurrency verification passes 8 tests / 65 assertions. MySQL schema/lifecycle, source-schema/fingerprint checks, Pint, Composer validation/platform requirements, optimize/clear, POS TypeScript/Vite build and Website lint/typecheck/Next.js build pass. Dependency locks are unchanged; no fresh online advisory result is claimed. `docs/warranty/MT_2_6_VERIFICATION.json` records exact evidence and artifact hashes.

Approved Goal/Preferences/addendum, Source of Truth, registry and structural roadmap Markdown/DOCX remain unchanged, so Word regeneration is neither required nor performed. Protected originals remain unchanged. The owned target MySQL must return to its initial stopped state after final Git checks. Intended synchronization is only the new monorepo main/private origin. MT-2.7 remains not started.

## MT-2.18 unified Admin identity and Google integrations remediation closure - 2026-09-01

The approved superseding requirement is preserved byte-for-byte in `docs/PROJECT_REQUIREMENTS_UNIFIED_ADMIN_GOOGLE_v1.0.md` and reconciled in `docs/remediation/RECONCILIATION.md`. It prospectively replaces the target credential and primary-email assumptions established at MT-2.2 without rewriting that historical checkpoint. Runtime authentication now has exactly two credential realms: Customer and Admin. POS operations and Website administration share one Admin credential, reset/session path, explicit permissions and outlet assignments. Legacy administrative rows require explicit verified mapping; matching email never unions privileges.

The singleton canonical business profile supplies `mobiST Technologies`, `mobisttech@gmail.com` and `https://mobisttech.com` to the public API, Website, sale-time invoice snapshots, Gmail sender and recovery links. Gmail uses backend OAuth 2.0 and Gmail API with exact `gmail.send`, state plus PKCE, encrypted server-side tokens, approved-account checks, test-before-connected and safe reconnect/disconnect. Recovery sends only an expiring HTTPS link. Authentic consent/test-send and Google production verification remain operator/production gates and are not claimed.

Google Drive integration is backend-only and dynamically manages or detects `mobisttech-drive:` through a private configurable rclone path/config. Test, connect/reconnect/disconnect, Backup Now, daily/weekly/disabled schedules, retention and encrypted logical business backups are implemented. Windows and Linux provisioning scripts avoid user-specific paths. The existing Windows remote passed a live temporary write/read/delete cleanup check; `mobist-drive:` and all old archives remained untouched.

The additive migration yields 86 tables. Explicit identity mapping, canonical profile, integration state/events and backup linkage are covered by focused and full security, migration, schema and integration tests. Final exact counts, normalized schema hash, build gates, roadmap Markdown/DOCX parity, rendered-page inspection, protected-source checks and artifact hashes are recorded in `docs/remediation/MT_2_18_VERIFICATION.json`.

The Source of Truth and structural roadmap advance to v1.7, adding only `MT-2.18 - Unified Admin identity and Google integrations remediation` before MT-2.7. The same-basename roadmap DOCX is regenerated and verified once for this material change. The owned target MySQL must return to its initial stopped state after final Git checks. Intended synchronization remains only this monorepo's private `origin/main`; MT-2.7 remains not started.

## MT-2.7 unified orders, reservations and payments closure - 2026-09-01

The shared Laravel backend now owns Website physical checkout, canonical repricing, customer/guest owner scope, one-outlet stock reservation, cancellation/expiry, COD collection, provider payment intent/callback handling, project milestone payments and bounded verified manual refunds. Physical settlement reuses the existing SalesOperations invoice authority and TransactionalStock held-unit consumption in one MySQL transaction; no Website-to-POS synchronization or second stock/payment database is introduced. New commerce creation obeys the locked Website operating profile, while verified callbacks and authorized historical milestone access remain safe after a mode switch.

Provider activation remains fail-closed. JazzCash, Easypaisa and Card are disabled without authentic H-02 contracts/credentials; a signed isolated fake proves the narrow adapter contract without network or fabricated sandbox claims. Receipt replay, amount/currency/reference tampering, failure release, unknown results, late paid reconciliation, COD authorization, milestone ownership/exact amount, refund balance reservation and shared stock/sale effects pass. Paid-after-expiry evidence is retained without a sale; automatic provider refund is not claimed.

The additive migration preserves the 86-table set and yields 1,052 columns, 143 foreign keys and 381 indexes with normalized schema hash `00f9478e641f4d73b5b61e9bb736cf8cffad5f766df7630d80961886c10f7115`. Empty disposable rollback returns the verified MT-2.18 schema hash `6f0b46482efd7a3a93e0986b3decf02d10ffaf62c4037d9f6ec204ddc877f488`, and reapply restores the MT-2.7 hash. Fresh verification passes 123 backend tests / 4,719 assertions; focused commerce verification passes 7 tests / 49 assertions. Strict MySQL/UTC checks, Pint, Composer validation/platform requirements, optimize/clear, POS TypeScript/Vite build and Website lint/typecheck/Next.js build pass. `docs/orders/MT_2_7_VERIFICATION.json` records exact evidence and artifact hashes.

Approved Goal/Preferences/addendum, Source of Truth, registry and structural roadmap Markdown/DOCX remain unchanged, so Word regeneration is neither required nor performed. No public business route, private source-data import, source runtime/database action, provider activation, external message or production change occurs. The owned target MySQL must return to its initial stopped state after final Git checks. Intended synchronization remains only this monorepo's private `origin/main`; MT-2.9 remains not started.

## MT-2.19 Team member roles, delegated access and session security remediation closure - 2026-09-01

The approved requirement is preserved byte-for-byte in `docs/PROJECT_REQUIREMENTS_TEAM_MEMBERS_SESSION_POLICY_v1.0.md` and reconciled in `docs/team-members/RECONCILIATION.md`. It extends the unified Admin architecture without reopening MT-2.18 or MT-2.7. Runtime human credentials remain exactly Customer and Admin; Team Member Roles are authorization bundles, not new guards/providers. Existing direct permissions remain an explicit compatibility input to effective permissions while relational named and Custom Roles provide protected snapshots that never auto-acquire future permission definitions.

The backend now owns Team Member creation, Role assignment, outlet assignment and safe delegation. An actor cannot grant permissions or outlets outside the actor's own effective ceiling, change the actor's own access, create a Custom Role containing the protected Full Access assignment permission or assign Full Access without that explicit protected permission. Job Title is descriptive only. Historical Team Member and operational audit records retain actor name, Role snapshots and outlet context independently of later Role changes.

Laravel centrally enforces 30 minutes of true human inactivity for Admin and 120 minutes for Customer. Background requests cannot refresh either clock. Admin uses browser-close cookies and rejects remembered login; Customer uses browser-close cookies by default and may use a remembered login capped at 30 days. A five-minute Admin warning/continuation component consumes the same backend human-activity contract. Sensitive Team Member, Role, profile, credential and integration changes require a login/password-confirmed timestamp no older than ten minutes. Password change preserves existing account-wide session and remember-token revocation.

The additive migration yields 91 tables, 1,089 columns, 153 foreign keys and 398 indexes with normalized schema hash `3906dc0f88ab3180e9887c2b7a06dea8366d419a580ee5ac22c49b36ccbf10ed`. Empty disposable rollback returns the verified MT-2.7 hash `00f9478e641f4d73b5b61e9bb736cf8cffad5f766df7630d80961886c10f7115`, and reapply restores the identical MT-2.19 hash. Fresh focused verification passes 8 tests / 54 assertions; identity regression passes 18 tests / 130 assertions; the full backend suite passes 131 tests / 4,773 assertions. Pint, Composer validation/platform requirements, optimize/clear, POS TypeScript/Vite build and Website lint/typecheck/Next.js build pass.

The Source of Truth and structural roadmap advance once to v1.8. The roadmap contains 54 points and 261 visible content blocks; its same-basename DOCX has matching content, renders as 22 pages and all 22 pages were visually inspected without clipping or overlap. `docs/team-members/MT_2_19_VERIFICATION.json` records the detailed implementation, schema, regression, document, source-protection and artifact evidence. No source runtime/database/remote, private business data, provider, external message or production system is changed. The owned target MySQL must return to its initial stopped state after final Git checks. Intended synchronization remains only this monorepo's private `origin/main`; MT-2.9 remains not started.

## MT-2.9 supplier and procurement services closure - 2026-09-02

The shared Laravel backend now owns outlet-scoped supplier profiles and contacts, immutable purchase-order supplier snapshots, exact ordered and landed unit costs, expected dates, partial/full receiving, cancellation, purchase-order events and supplier-to-acquisition history. Procurement receipts reuse the existing acquisition and stock authority through `StockReceiptWriter`; receipt lines retain stable acquisition references without changing historical supplier or stock records. All writes require `shop.procurement`, durable idempotency and server-owned outlet context. Receiving and cancellation lock the order and lines, prevent over-receipt or post-cancellation receipt and roll back multi-line failures atomically.

Outlet/product reorder policies use version preconditions and indexed, bounded recommendations over current stock plus outstanding open-order quantities. Low/out-of-stock results recommend the quantity required to reach the configured target after incoming stock. No automatic ordering, accounts payable, general ledger or full ERP is introduced. Reserved HTTP and interface contracts remain pending MT-3.4 and MT-4.5.

The additive migration yields 99 tables, 1,191 columns, 172 foreign keys and 448 indexes with normalized schema hash `966fd7d798d15661ca070833126abc81ad20ece7c7b58e9d2b383fab03f1de27`. Empty disposable rollback returns the verified MT-2.19 hash `3906dc0f88ab3180e9887c2b7a06dea8366d419a580ee5ac22c49b36ccbf10ed`, and reapply restores the identical MT-2.9 hash. Focused procurement and real two-connection duplicate-receipt verification passes 8 tests / 107 assertions; the broader inventory/procurement regression passes 30 tests / 313 assertions; the full backend suite passes 139 tests / 4,935 assertions. Pint, Composer validation/platform requirements, optimize/clear, POS TypeScript/Vite build and Website lint/typecheck/Next.js build pass.

`docs/procurement/README.md` records reuse, transaction, recommendation and exclusion contracts; `docs/procurement/MT_2_9_VERIFICATION.json` records exact schema, regression, source-protection and artifact evidence. Approved authority documents, Source of Truth and structural roadmap Markdown/DOCX remain unchanged, so Word regeneration is neither required nor performed. No protected source runtime/database/remote, private business data, provider, external message or production system is changed. The owned target MySQL must return to its initial stopped state after final Git checks. Intended synchronization remains only this monorepo's private `origin/main`; MT-2.10 remains not started.

## Consolidated documents, payments, legal and manual requirement reconciliation - 2026-09-02

The approved requirement is preserved byte-for-byte in `docs/PROJECT_REQUIREMENTS_DOCUMENTS_PAYMENTS_LEGAL_MANUAL_v1.0.md` (65,000 bytes; SHA-256 `b682fed17cf6d955c2df0cac8eb081b0b641602e2b7391f3137c2e979e69b1a1`). Its authoritative interpretation and clause-to-point traceability are recorded in `docs/consolidated-requirements/RECONCILIATION.md`. This is a structural documentation checkpoint only: no completed point is reopened, no application/runtime file is changed and MT-2.10 is not started.

The Source of Truth and roadmap advance to v1.9. The roadmap adds `MT-2.20 - POS payment channels, split tenders and settlement reconciliation` after MT-2.11 and makes MT-2.12 depend on MT-2.20. It adds `MT-7.6 - Product user manual and administrator operations guide` after MT-7.5 and makes FINAL-AUDIT depend on MT-7.6. MT-2.12, MT-3.1, MT-3.2, MT-4.2, MT-4.3, MT-4.4, MT-4.6, MT-5.3, MT-5.4, MT-7.2, MT-7.5 and FINAL-AUDIT receive the approved delivery, payment, legal, licensing and manual acceptance scope without changing their state.

The roadmap now contains 56 unique points, 56 dependency lines and 271 visible content blocks. Its same-basename DOCX was regenerated once; automated Markdown/body parity passed. The mirror rendered as 27 pages, and every page was visually inspected with no clipping, overlap, missing text or broken page furniture. Exact preservation, traceability, dependency, document and boundary evidence is recorded in `docs/consolidated-requirements/STRUCTURAL_RECONCILIATION_VERIFICATION.json`.

The canonical Goal and Preferences remain byte-identical. The pinned POS and Website application-source baselines remain unchanged; both protected repositories are clean at later System 7.7 control-only commits whose differences from the pinned baselines are limited to repository control documentation. No Gmail/provider call, settlement action, source-data access, production action or destructive operation occurred. Live state remains 15 completed / 56 total / 41 pending; `MT-2.10 - Stocktake and cycle-count services` remains the first pending point.

## MT-2.10 stocktake and cycle-count services closure - 2026-09-02

The shared Laravel backend now owns outlet-scoped full stocktake and selected-product cycle-count sessions for quantity and serialized/IMEI inventory. Session creation captures an explicit immutable movement-ID baseline plus product/held/available state; serialized lines also retain unit identity/version snapshots. Counting is append-only, never mutates stock, requires reason codes for non-zero variance and preserves each recount iteration. `shop.stocktake` and `shop.stocktake.approve` remain separate server-side permissions.

Approval is versioned, idempotent and delta-based rather than a silent quantity overwrite. Legitimate post-count sale, restock and customer-return movements are reconciled against the explicit baseline, while later manual corrections or stocktake adjustments invalidate stale approval and require recount. Negative quantity variance cannot consume held stock. Missing serialized units are retired as `adjusted_out`, active IMEI claims are removed without falsely marking the unit sold, and positive serialized variance requires normal inventory evidence followed by recount. Multi-line approval is one MySQL transaction and rolls back completely on any stale or unsafe line.

The additive migration yields 105 tables, 1,279 columns, 186 foreign keys and 482 indexes with normalized schema hash `6ff5e8e2a05a57d3e255b1c9fd4a4cd5bcf4494e4db87da20306a061f3298f74`. Empty disposable rollback returns the verified MT-2.9 hash `966fd7d798d15661ca070833126abc81ad20ece7c7b58e9d2b383fab03f1de27`, and reapply restores the identical MT-2.10 hash. Focused stocktake verification passes 9 tests / 53 assertions; real independent-connection concurrency verification passes 9 tests / 537 assertions including approval-versus-sale and approval-versus-reservation; the full backend suite passes 150 tests / 5,150 assertions. Pint, Composer validation/platform requirements, optimize/clear, POS TypeScript/Vite production build and Website lint/typecheck/Next.js production build pass.

`docs/stocktake/README.md` records baseline, count, recount, approval and exclusion contracts; `docs/stocktake/MT_2_10_VERIFICATION.json` records exact schema, regression, source-boundary and artifact evidence. The addendum entity/API/permission matrix and target schema manifest are synchronized to the implemented backend domain and explicit `adjusted_out` IMEI history state. Approved Goal/Preferences/addendum, Source of Truth and structural roadmap Markdown/DOCX remain unchanged, so Word regeneration is neither required nor performed. No protected source runtime/database/remote, private business data, provider, external message or production system is changed. Intended synchronization remains only this monorepo's private `origin/main`; MT-2.11 remains not started.

## Software Product publishing requirement reconciliation - 2026-09-06

The approved requirement `docs/PROJECT_REQUIREMENTS_SOFTWARE_PRODUCT_PUBLISHING_v1.0.md` is registered with SHA-256 `8ecf91807c2d0e838d1e2daf7ccfc130c522159e42ee5cef7d57d60c4276c452`. It adds one reusable first-party Software Product publishing model to the existing CMS/Admin/Website architecture without adding, renumbering or reopening a roadmap point.

The planned public route family is `/software/{software-slug}` with product-specific `/privacy`, `/terms`, `/faq`, `/releases` and optional `/releases/{version}` routes. The protected Admin workflow includes a Software list, `New Software` from one reusable template, Overview/features/media/platform/documentation fields, product-specific policies/FAQ, release/version history, preview, publish, archive and rollback. Release publication preserves prior versions and surfaces documentation-impact review for material changes.

The first planned entry is `mobiST POS` with slug `mobist-pos`. The four files under `docs/reference/mobiST POS-IMS/` are preserved as reviewed factual seed/reference material for later MT-3.2/MT-4.4 reconciliation; they are not silently treated as already-published CMS records.

Roadmap and Source of Truth advance structurally to v1.10. The roadmap retains 56 points and 56 dependency lines, has 273 visible content blocks, passes exact Markdown/DOCX paragraph parity, renders as 23 pages and passed every-page visual inspection with no clipping, overlap, missing text or broken page furniture. Detailed evidence is in `docs/software-publishing/STRUCTURAL_RECONCILIATION_VERIFICATION.json`.

No runtime application feature, live domain/DNS/TLS, provider configuration, Google verification, private-data migration or production publication is performed by this checkpoint. H-01 remains the live-domain boundary. Implementation position remains 16/56 complete; `MT-2.11 - Inter-outlet stock transfer services` remains next.

## MT-2.11 inter-outlet stock transfer services closure - 2026-09-17

The shared Laravel backend now owns outlet-scoped inter-outlet transfer planning, dispatch, in-transit custody, partial receive/reject and final receipt history for quantity and serialized/IMEI stock. `shop.transfers.dispatch` is source-outlet scoped and `shop.transfers.receive` is destination-outlet scoped. Dispatch uses existing `inventory_custody_holds`, so in-transit stock remains physically at source while unavailable to sale, reservation, adjustment, IMEI editing, archive or competing transfer consumption.

Accepted quantity creates balanced `transfer_out`/`transfer_in` movements; rejected quantity releases custody without fabricated movement. Serialized receipt preserves identity by retiring the source occurrence as `transferred_out`, moving its historical IMEI occurrence to that state, creating a destination `in_stock` successor with the same IMEI identity, and recording forward-only `stock_unit_lineage`. Serialized rejection leaves source stock and active IMEI ownership unchanged. Immutable product/unit/receipt snapshots, SHA-256 digests, version preconditions, idempotency and row locks reject stale or duplicate work atomically.

The additive migration yields 110 tables, 1,341 columns, 201 foreign keys and 513 indexes with normalized schema hash `c26ea97aeeaf01b928040d06b0ad05fd2df72b6906dba358ccf305a90121bfee`. Empty disposable rollback restores the exact MT-2.10 hash `6ff5e8e2a05a57d3e255b1c9fd4a4cd5bcf4494e4db87da20306a061f3298f74`, and reapply restores the identical MT-2.11 hash. Focused transfer verification passes 4 tests / 38 assertions; final transfer plus inventory-concurrency verification passes 14 tests / 697 assertions including independent-connection duplicate receipt arbitration; the full backend suite passes 155 tests / 5,311 assertions. Pint, strict Composer/platform checks, optimize/clear, POS TypeScript/Vite production build and Website ESLint/typecheck/Next.js 16.3.3 production build pass.

`docs/transfers/README.md` records the transfer/custody contracts and exclusions; `docs/transfers/MT_2_11_VERIFICATION.json` records schema, regression, source-boundary and artifact evidence. The addendum entity/API/permission matrix, target schema manifest and reset-retention classification are synchronized to the implemented backend domain and explicit `transferred_out` IMEI history state. Approved Goal/Preferences/addendum, Source of Truth and structural roadmap Markdown/DOCX remain unchanged, so Word regeneration is neither required nor performed. Protected source repositories were inspected read-only and remain clean; no private source data, provider, external message, deployment or production system was changed. HTTP publication remains MT-3.4 and POS transfer interfaces remain MT-4.5.

## MT-2.20 POS payment channels, split tenders and settlement reconciliation closure - 2026-09-17

The shared Laravel backend now owns configurable outlet-scoped POS Payment Destinations for Cash, Card, Mobile Wallet and Bank Transfer, plus exact-money multiple-tender allocations for walk-in POS invoices. Destination management is independently permissioned from sale use, active/effective/outlet state is rechecked server-side, and unmasked financial identifiers plus PAN/CVV/PIN-like ordinary references are rejected. Cash tendered and returned change are derived separately from the allocation amount.

Sale, customer tender and settlement remain distinct. Non-cash tender allocations retain immutable destination evidence and append settlement events for gross customer payment, merchant fee, signed adjustment, expected net receipt, confirmed received amount and variance without rewriting the Invoice or customer-paid amount. POS returns continue to use the existing SalesOperations authority; POS refund allocations retain the original tender and refund-destination snapshots. Different-method/destination refunds require explicit override, reason and permission, with configured approval where required. Website checkout remains fixed to COD, JazzCash, Easypaisa and Card; no Website Bank Transfer, split tender or merchant-destination selection was introduced.

The additive migration yields 114 tables, 1,416 columns, 215 foreign keys and 540 indexes with normalized schema hash `e4909c96ddc9ff12bf637adb4118f9ac803de19202fba3fc6cbc8dfb3198063f`. One-step disposable rollback restores the exact MT-2.11 hash `c26ea97aeeaf01b928040d06b0ad05fd2df72b6906dba358ccf305a90121bfee`, and reapply restores the identical MT-2.20 hash. Final focused POS payment verification passes 4 tests / 41 assertions; focused payment plus independent-connection inventory concurrency passes 16 tests / 883 assertions; the full backend suite passes 161 tests / 5,535 assertions. Pint 145-file verification, strict Composer/platform checks, optimize/clear, POS TypeScript/Vite build and Website ESLint/typecheck/Next.js 16.3.3 production build pass.

`docs/payments/README.md` records the payment/destination/split-tender/settlement/refund contracts and exclusions; `docs/payments/MT_2_20_VERIFICATION.json` records exact schema, concurrency, regression, source-boundary and artifact evidence. Approved Goal/Preferences/addendum/payment requirement, Source of Truth and structural roadmap Markdown/DOCX remain unchanged, so Word regeneration is neither required nor performed. Protected source repositories remain clean under read-only inspection. No authentic provider activation, automatic external refund, private-data migration, external message, deployment or production mutation occurred. HTTP/UI/reporting publication and cash-session/day-closing work remain at their named later points.

## MT-2.12 cash sessions and operational expense services closure - 2026-09-17

The shared Laravel backend now owns outlet-scoped cash sessions, opening cash, approved Cash In, operational expenses/payouts, Cash refund attribution, closing counts and immutable Day Closing snapshots. Exact reconciliation is `Opening Cash + Cash Sales + approved Cash In - Cash Refunds - Expenses/Payouts = Expected Cash`; Actual Cash records variance, and non-zero variance requires reason plus approval authority. Non-cash Payment Destination gross receipts, settlement fees/adjustments, expected net, confirmed received net and settlement variance remain separate from drawer cash and from the customer sale amount.

Cash POS tenders and Cash refunds bind to the active outlet session while non-cash activity can remain independent. Outlet/session locks, version preconditions and durable idempotency reject duplicate close and ensure a concurrent close-versus-sale cannot omit committed cash. Closed snapshots retain destination display evidence and SHA-256 integrity independently of later configuration edits. Populated cash/tender/refund history blocks unsafe migration rollback, and reset retention classifies cash sessions/entries as transactional history.

The additive migration yields 116 tables, 1,454 columns, 224 foreign keys and 559 indexes with normalized schema hash `47692f4c7a369c729c24da8fddffdc28d696592122a76fc8019fe6e15a125cee`. One-step rollback restores the exact MT-2.20 schema hash `e4909c96ddc9ff12bf637adb4118f9ac803de19202fba3fc6cbc8dfb3198063f`, and reapply restores the identical MT-2.12 hash. Focused cash/payment/concurrency verification passes 21 tests / 1,080 assertions; the full backend suite passes 167 tests / 5,734 assertions. Pint, strict Composer/platform checks, optimize/clear, POS TypeScript/Vite production build and Website lint/typecheck/Next.js 16.3.3 production build pass. Detailed evidence is in `docs/cash/MT_2_12_VERIFICATION.json`.

Approved Goal/Preferences/addendum/consolidated requirement, Source of Truth and structural roadmap Markdown/DOCX remain unchanged, so Word regeneration is neither required nor performed. No protected source runtime/database/remote, private business data, provider, external message, deployment or production system is changed. Broader reports remain MT-3.1 and cash/day-closing UI remains MT-4.6. Intended synchronization remains only this monorepo's private `origin/main`; At the MT-2.12 checkpoint, MT-2.13 had not started.

## MT-2.13 trade-in and buyback services closure - 2026-09-17

The shared Laravel backend now owns individual-seller trade-in/buyback valuation, approval, cancellation and atomic stock intake. Device serial/IMEI identity, condition/diagnostics, seller ownership/source details and exact PKR valuation are retained under `shop.trade-in`; pending/approved identifier reservations prevent duplicate active intake before canonical inventory assignment.

Purchase settlement records the exact acquisition value without inventing a payment processor. Sale-credit settlement locks the target invoice, rejects over-allocation, and appends one immutable `trade_in_credit` tender snapshot through the existing monetary-adjustment authority. Receipt revalidates approval, creates the acquisition and stock occurrence, assigns canonical active IMEIs, attaches the typed trade-in acquisition reference and applies any sale credit in one transaction; duplicate identifiers or later failures roll back every stock/money effect. Cancellation before receipt releases reserved identifiers with no inventory or credit effect. Private source values stay scoped; acquisition evidence is masked and approval snapshots use a CNIC digest.

The additive migration yields 119 tables, 1,498 columns, 235 foreign keys and 576 indexes with normalized schema hash `2053c9a0e7ddaaf26f0dd8b91bb70e03b25deda8b6d3e30e09226e566369c0b0`. One-step rollback restores the exact MT-2.12 schema hash `47692f4c7a369c729c24da8fddffdc28d696592122a76fc8019fe6e15a125cee`, and reapply restores the identical MT-2.13 hash. Final focused verification passes 4 tests / 31 assertions; the final-code full backend suite passes 171 tests / 5,765 assertions. Pint 151-file verification, strict Composer/platform checks, optimize/clear, POS TypeScript/Vite production build and Website ESLint/typecheck/Next.js 16.3.3 production build pass.

`docs/trade-in/README.md` records workflow/privacy/money boundaries and `docs/trade-in/MT_2_13_VERIFICATION.json` records schema, rollback, regression, build and protected-source evidence. Reset-retention classification and the disposable schema verifier include the new domain. Approved Goal/Preferences/addendum, Source of Truth and structural roadmap Markdown/DOCX remain unchanged, so Word regeneration is neither required nor performed. Protected source repositories remain clean under read-only inspection. HTTP publication remains MT-3.4 and operator interfaces remain MT-4.6; no provider, private source data, external message, deployment or production system was changed.

## Roadmap point state - v1.10

This is the live status list. Completed counts are preserved from verified Git checkpoints; newly inserted points start Pending and advance only through their own verified checkpoint. The structural roadmap defines scopes and acceptance.

| Point | Title | State |
|---|---|---|
| MT-0.1 | Project initialization | Completed |
| MT-1.1 | Source inventory and feature parity register | Completed |
| MT-1.2 | Unified data, API and security design | Completed |
| MT-1.3 | Windows toolchain and application foundations | Completed |
| MT-2.1 | Shared backend schema and infrastructure | Completed |
| MT-2.2 | Identity, customer and authorization migration | Completed |
| MT-2.3 | Product and master-data migration | Completed |
| MT-2.4 | Inventory and stock integrity migration | Completed |
| MT-2.8 | Addendum foundations and capability contracts | Completed |
| MT-2.5 | Sales, invoices and returns migration | Completed |
| MT-2.6 | Warranty and claim migration | Completed |
| MT-2.18 | Unified Admin identity and Google integrations remediation | Completed |
| MT-2.7 | Unified orders, reservations and payments | Completed |
| MT-2.19 | Team member roles, delegated access and session security remediation | Completed |
| MT-2.9 | Supplier and procurement services | Completed |
| MT-2.10 | Stocktake and cycle-count services | Completed |
| MT-2.11 | Inter-outlet stock transfer services | Completed |
| MT-2.20 | POS payment channels, split tenders and settlement reconciliation | Completed |
| MT-2.12 | Cash sessions and operational expense services | Completed |
| MT-2.13 | Trade-in and buyback services | Completed |
| MT-2.14 | Promotion and coupon services | Completed |
| MT-2.17 | Validated bulk data workflows | Pending |
| MT-2.15 | Customer loyalty services | Pending |
| MT-2.16 | Paid repair job services | Pending |
| MT-3.1 | Reports, documents and communication services | Pending |
| MT-3.2 | Dynamic CMS, media and presentation services | Pending |
| MT-3.7 | Website operating mode publication | Pending |
| MT-3.5 | Digital service catalogue and lead services | Pending |
| MT-3.6 | Client projects, proposals and milestone services | Pending |
| MT-3.9 | Customer engagement and notification services | Pending |
| MT-3.3 | Audit, configuration, backups and integrations | Pending |
| MT-3.8 | Guarded data reset services | Pending |
| MT-3.4 | Versioned REST API and contract acceptance | Pending |
| MT-4.1 | POS shell, authentication and navigation | Pending |
| MT-4.2 | POS inventory and transaction interfaces | Pending |
| MT-4.5 | Procurement and stock control interfaces | Pending |
| MT-4.6 | Cash, trade-in and repair interfaces | Pending |
| MT-4.3 | POS customer, warranty and reporting interfaces | Pending |
| MT-4.4 | Website CMS and platform administration interfaces | Pending |
| MT-4.8 | Digital operations administration interfaces | Pending |
| MT-4.7 | Data reset administration interface | Pending |
| MT-5.1 | Storefront, catalogue and SEO migration | Pending |
| MT-5.2 | Customer account, cart, orders and reviews | Pending |
| MT-5.3 | Checkout and customer payment flows | Pending |
| MT-5.4 | Dynamic public content and digital solutions | Pending |
| MT-5.5 | Client project portal and digital conversion journeys | Pending |
| MT-6.1 | Canonical branding and runtime assets | Pending |
| MT-6.2 | Canonical mobiST Control migration | Pending |
| MT-6.3 | Windows operator and local integration acceptance | Pending |
| MT-7.1 | Data migration and rollback rehearsal | Pending |
| MT-7.2 | Security, performance and resilience audit | Pending |
| MT-7.3 | Monorepo CI and reproducible build gates | Pending |
| MT-7.4 | Linux deployment and backup readiness | Pending |
| MT-7.5 | Full functional parity and acceptance | Pending |
| MT-7.6 | Product user manual and administrator operations guide | Pending |
| FINAL-AUDIT | Independent final project audit | Pending |

## Recovery and next action

Do not re-execute the nineteen completed points, addendum reconciliation, unified Admin/Google remediation, Team Member/session remediation, consolidated-requirement structural reconciliation, Software Product publishing structural reconciliation, the MT-2.10 stocktake closure, the MT-2.11 transfer closure, the MT-2.20 payment closure, the MT-2.12 cash closure the MT-2.13 trade-in closure or the MT-2.14 promotion/coupon closure. The project is 21/56 complete with 35 pending. The next applicable Y/Proceed executes only `MT-2.17 - Validated bulk data workflows`. Follow document order and explicit dependencies, not numeric ID sorting. Registry MT-1.1-r15 is current under immutable Project ID `282dba2f-a2d9-47e8-aa8d-e499fbe1706c` and adopts Universal Registry 1.20 / System 7.7 without changing application position. Roadmap v1.10 and its same-basename DOCX are the verified structural plan. Sessions using an older loaded registry must refresh before project-control aliases; new/unbound sessions resolve the Project ID and load the canonical current registry through the bootstrap. Approved addendum, consolidated-requirement and Software Product publishing workflows remain assigned to their named later points.

## HOLD / decisions

Roadmap H-01 through H-04 remain the authoritative HOLD register: live production/cutover, authentic provider contracts/credentials, sensitive-data export/destructive restore and unrelated category/feature expansion boundaries. Shared schema/infrastructure, unified Admin/customer identity, Team Member Role/delegation and session-policy authority, product/master-data, stock/acquisition, internal and Website sale/invoice/accepted-return, warranty/claim, order/reservation/COD/manual-refund transactions, milestone payment identity, supplier/purchase-order/partial-receipt authority, reorder recommendations, stocktake/cycle-count authority, inter-outlet transfer/custody authority, POS Payment Methods/Destinations and split-tender/refund/settlement authority, canonical business profile and secure Google integration/backup foundations now exist. Canonical Invoice/Warranty delivery, Gmail attachment sending, assisted WhatsApp states, fixed Website-channel regression, typed legal/policy publication, legal-owner approval, application ownership/license choice, dependency/asset notices and the final user manual remain future verified work. No legal text, license ownership or provider settlement state is invented by this reconciliation. Private business-data migration, authentic external provider activation/automatic refunds, authentic Gmail consent/test send, Google production verification, later interfaces/rendered documents and approved branding inventory remain future verified work. Target-only runtime ports and locks remain unchanged, without real source data/provider/production actions. The local environment uses file cache, database sessions/queue and private local storage. These HOLD items do not block verified MT-2.14 scope. Addendum and consolidated-requirement features are approved planned work, not H-04 exclusions; production destructive resets remain separately authorized under H-01/H-03.

No source-repository write, source-data migration, source runtime action, real payment-provider activation, external message or production change is authorized or performed by this reconciliation.

## MT-2.13 attempt tracking

- Attempt 1: Incomplete orchestration edit. Signature: temporary lock on ackend/app/Addendum/ResetRetention.php plus malformed verifier insertion causing PHP parse error at 	ools/migration/verify_mysql_schema.php:221. No product test/migration failure occurred.
- Material change before retry: split the edits, inspect the malformed verifier region, apply a targeted syntax correction, and use a separate bounded write path for the locked retention file.
- LOOP_GUARD: inactive (first unsuccessful/incomplete attempt).

- Attempt 2: Incomplete orchestration edit. Signature: PowerShell parsed inline Python patch text as shell syntax before execution; repository files were not modified by this attempt.
- Material change before attempt 3: stop inline quoting entirely; use a standalone temporary Python patch file with exact assertions and post-write PHP syntax checks. LOOP_GUARD remains inactive unless attempt 3 is unsuccessful.

- MT-2.13 schema verification attempt 1: FAIL. Signature: `trade-in` verifier reported missing `trade_ins.device_serial`; migration text did not contain the intended optional serial column although the service/result contract references it.
- Material change before retry: add the missing nullable `device_serial` column to the additive MT-2.13 migration, rebuild only the disposable test schema, then rerun the focused verifier. LOOP_GUARD inactive (first schema failure).

- MT-2.13 focused verification attempt 1: FAIL (2 failed, 2 passed). Signature: raw query-builder `DB::table(...)->whereKey(...)` generated an invalid `key` column lookup in the new trade-in test/service paths. The two unaffected cancellation/rollback-permission cases passed.
- Material change required before retry: replace only raw query-builder `whereKey` calls with explicit `where('id', ...)`, syntax-check, then rerun the two affected focused cases before any broader verification. LOOP_GUARD inactive (first focused-test failure).

- MT-2.13 resume focused-fix attempt 1: orchestration FAIL after intended raw query-builder replacements; PowerShell Set-Content wrote UTF-8 BOM, so PHP rejected namespace position. Product logic change was limited to explicit id predicates. Retry strategy: preserve exact content and rewrite only affected PHP files as UTF-8 without BOM before syntax/focused tests. LOOP_GUARD inactive (first resume failure).

- MT-2.13 final gate attempt 1: Pint --test FAIL on the two new PHP files only (line endings/formatter rules). Functional focused suite and full backend suite had already passed. Material retry: run Pint formatter only on those two files, syntax-check, then rerun final-code focused/full regression before broader gates. LOOP_GUARD inactive (first style-gate failure).

## MT-2.14 attempt tracking

- Resume implementation attempt 1: file write failed before content creation because the new `backend/app/Promotions` directory did not yet exist. Material change: create the scoped directory explicitly before writing. No product file was partially written.
- Resume patch-runner attempts 2 and 3: equivalent Python parse failures caused by missing newlines at chunk append boundaries. LOOP_GUARD activated after the second equivalent signature; no target Sales/Commerce patch executed during either failed attempt.
- LOOP_GUARD diagnosis/material change: normalize all `)replace(` append boundaries in the temporary patcher, require `python -m py_compile` PASS before execution, then execute once. The patcher passed compile, SalesOperations and OrderTransactions patches applied, and both PHP syntax checks passed. The temporary patcher was removed.
- Completion verification: shared promotion schema/service and POS/Website integration passed focused tests, full backend regression, exact rollback/reapply schema proof, formatter/dependency gates and backend/Website production builds. Evidence is in `docs/promotions/`.
- MT-2.14 closure: implementation and verification complete. Next roadmap point is `MT-2.17 - Validated bulk data workflows`. Do not rerun the retired temporary patcher path.
