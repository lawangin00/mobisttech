# mobiST Tech - Project Implementation Status

Last reconciled: 2026-09-01

## Identity and authority

- Project: mobiST Tech
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
- Active Source of Truth: `docs/PROJECT_SOURCE_OF_TRUTH.md` v1.7
- Active roadmap: `docs/PROJECT_IMPLEMENTATION_ROADMAP.md` v1.7
- Roadmap Word mirror: `docs/PROJECT_IMPLEMENTATION_ROADMAP.docx`
- Project registry: `docs/AI_PROJECT_COMMAND_REGISTRY.md` MT-1.1-r5 (universal 1.10 / system 7.3)
- FINAL-AUDIT: `FINAL-AUDIT - Independent final project audit`

## Verified position

Active stage: MT-2 - Shared backend and transactional migration (In Progress)

Last completed stage: MT-1 - Migration inventory and design

Current In Progress point: None

Status: MT-0 and MT-1 complete. MT-2 is in progress. 13/53 complete, 40 pending. MT-2.7 is complete; MT-2.9 is not started.

Last completed point: MT-2.7 - Unified orders, reservations and payments

Next pending point: MT-2.9 - Supplier and procurement services

Execution boundary: Backend/Website foundations, shared MySQL schema/infrastructure, unified Admin/customer authorization, product/master-data, inventory/acquisition/stock transactions, internal POS sale/invoice/accepted-return authority, warranty/claim authority, canonical business profile and secure Google integration/backup services exist. Addendum schema primitives, capability/financial-reference/retention contracts, explicit permissions and custody-aware stock exclusion are integrated. Identity/product/stock/sales/claim mapping uses synthetic rows only. Real MySQL stock, return and claim concurrency and the existing `mobisttech-drive:` temporary read/write/delete check are verified. Actual private-data migration, authentic Gmail OAuth consent/test send, production Google verification, payment collection/refund execution, transfer/procurement/reset/publication workflows, complete POS/Website interfaces, rendered warranty documents, brand, Control and CI remain pending. Backend tests do not constitute live Gmail/provider, payment/refund, complete UI, rendered-document or private-data import acceptance.

Approved addendum v1.1 expands required work without reinitialization. MT-2.8 implements its prerequisites only; mode publication/reset/retail/digital feature workflows remain pending at their named points.

## Documentation language policy

Roman Urdu is the default only for assistant chat/UI communication with the user. Git-tracked project documentation and technical artifacts use standard English unless the user explicitly requests another language for a specific artifact. User-supplied Goal and Preferences remain byte-preserved in their original language/content unless transformation is explicitly authorized. This boundary is recorded in Source of Truth, roadmap, AGENTS, project registry and the synchronized Universal Registry 1.10 baseline.

## Roadmap lifecycle policy

The canonical roadmap is a structural execution plan, not the live progress tracker. This implementation ledger is authoritative for Completed/In Progress/Pending state, last/current/next point and progress counts. Routine point or stage progress must update this ledger only and must not edit the roadmap or regenerate its Word mirror.

The roadmap Markdown and same-basename DOCX are regenerated/verified only when the roadmap itself changes structurally or materially, such as approved scope/requirement changes, dependencies, acceptance intent, HOLD/Deferred constraints, remediation points or stage restructuring. This one-time v1.5 normalization removed live point/stage status markers from the roadmap while preserving all 32 point IDs, exact titles, dependencies, scopes, acceptance criteria and HOLD items.

The one-time normalized Word mirror contains 32 points, 32 dependency lines and 166 visible content blocks. Microsoft Word rendered 13 pages and all 13 were visually inspected cleanly. Global Registry 1.9 / Roadmap Specification 1.1 is committed in `lawangin00/references` at `5466f3804e05325b75f63100f4539479a6250dde`; its v7.2 reference DOCX rendered 15 pages and all 15 were visually inspected cleanly. Evidence is recorded in `docs/ROADMAP_LIFECYCLE_OPTIMIZATION_VERIFICATION.json`.

The current v1.7 structural roadmap and mirror contain 53 points, 53 dependency lines and 255 visible content blocks. The generated DOCX matches every Markdown body block, rendered 22 pages through LibreOffice, and all 22 pages were visually inspected without clipping, overlap, missing text or broken page furniture. Current evidence is recorded in `docs/remediation/MT_2_18_VERIFICATION.json`.

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

## Roadmap point state - v1.7

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
| MT-2.9 | Supplier and procurement services | Pending |
| MT-2.10 | Stocktake and cycle-count services | Pending |
| MT-2.11 | Inter-outlet stock transfer services | Pending |
| MT-2.12 | Cash sessions and operational expense services | Pending |
| MT-2.13 | Trade-in and buyback services | Pending |
| MT-2.14 | Promotion and coupon services | Pending |
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
| FINAL-AUDIT | Independent final project audit | Pending |

## Recovery and next action

Do not re-execute the thirteen completed points, addendum reconciliation or unified Admin/Google remediation. After the MT-2.7 checkpoint is committed/pushed and clean synchronization is verified, stop. The next applicable Y/Proceed executes only `MT-2.9 - Supplier and procurement services`. Follow document order and explicit dependencies, not numeric ID sorting. Registry MT-1.1-r5 is current; routine progress requires no roadmap Word regeneration. Already-open chat/Work sessions that previously loaded r4 must run one `Refresh` / `VP:REFRESH-REGISTRY` before using aliases again; new chats load r5 on their first project alias. Broader addendum feature workflows remain assigned to their respective later points.

## HOLD / decisions

Roadmap H-01 through H-04 remain the authoritative HOLD register: live production/cutover, authentic provider contracts/credentials, sensitive-data export/destructive restore and unrelated category/feature expansion boundaries. Shared schema/infrastructure, unified Admin/customer identity, product/master-data, stock/acquisition, internal and Website sale/invoice/accepted-return, warranty/claim, order/reservation/COD/manual-refund transactions, milestone payment identity, canonical business profile and secure Google integration/backup foundations now exist. Private business-data migration, authentic external provider activation/automatic refunds, supplier and broader financial processing, authentic Gmail consent/test send, Google production verification, later interfaces/rendered documents and approved branding inventory remain future verified work. Target-only runtime ports and locks remain unchanged, without real source data/provider/production actions. The local environment uses file cache, database sessions/queue and private local storage. These HOLD items do not block verified MT-2.7 scope. Addendum features are approved planned work, not H-04 exclusions; production destructive resets remain separately authorized under H-01/H-03.

No source-repository write, source-data migration, source runtime action, real payment-provider activation, external message or production change is authorized or performed by this reconciliation.
