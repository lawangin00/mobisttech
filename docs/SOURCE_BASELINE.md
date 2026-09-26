# Source baseline and migration risks

## Local source retirement (26-Sep-2026)

The local legacy POS and Website working checkouts used for the original 31-Aug source characterization have been retired as operational dependencies. The paths listed below are historical capture provenance only and may no longer exist on disk. Mobisttech runtime, build, test, migration, acceptance and release must not depend on them. The committed `docs/SOURCE_SNAPSHOT.json`, `docs/migration/SOURCE_FILE_INVENTORY.json`, `docs/migration/SOURCE_SYMBOL_INVENTORY.json`, `docs/migration/CHARACTERIZATION.md`, feature-parity/audit evidence, and the isolated characterization exports preserve the required source evidence. The owner-approved fresh-business scope makes real legacy business-data cutover/D04 source-copy migration RETIRED / NOT APPLICABLE.

Date: 2026-08-31. Scope: read-only initialization review. The full feature-parity audit was still pending under MT-1.1 at the time this baseline was created.

## Verified references

| Source | Local path | Pinned main commit |
|---|---|---|
| lawangin00/mobiST-POS | C:\mobiST\mobiST-POS | c61e47394e7b3db8a49cfe443c85b63835febc9b |
| lawangin00/mobiST-Website | C:\mobiST\mobiST-Website | 04e7c49518f9f11f60c83ad44f9f4e2fd2539066 |

Read-only live `ls-remote` checks matched both pinned commits to remote `main`. Both working trees were clean. No fetch/pull, source test/build, migration, provider call, source commit/push or source runtime mutation was performed. Full tracked-file fingerprints are stored in `docs/SOURCE_SNAPSHOT.json`.

SHA-256 values of identical control files in both source repositories:

- `docs/PROJECT_IMPLEMENTATION_STATUS.md`: `d40f3b94647b41050b9eb5483f5459425fe3859a808faba0810608f8f0cd19ae`
- `docs/DYNAMIC_PLATFORM_SOURCE_OF_TRUTH.md`: `cc3cb3f31f7206ee8d9359b7455dee4fe2c3531b34aa14b5e9ff41bb24dbd8f4`
- `docs/DYNAMIC_PLATFORM_GAP_ANALYSIS_AND_ROADMAP.md`: `3a3003c057e49b4d2b5f47da111d481524b372a8eeddabc737be3cfd28b0fac4`

The source ledger and reviewed roadmap record Dynamic Platform as 62/62 complete, with final point `DP-X.7 - Dynamic Platform final sign-off and documentation`, plus the 31 August FULL-AUDIT reconciliation. Historical ledger totals report POS 202 tests / 2,695 assertions and Website 236 tests / 2,806 assertions. These were not rerun as part of this new-project baseline.

## Actual code observations

- Both Composer manifests use Laravel `^13.17` and PHPUnit `^12.5.12`; POS also includes Sanctum. Source PHP constraints are `^8.3`. The fresh migration toolchain must be verified against actual locked-package requirements rather than README version claims.
- Both applications are currently Laravel/Blade/Vite. There is no existing React/Inertia POS or Next.js Website implementation to claim as complete. Frontend conversion is required.
- POS models/services include StockUnit, StockMovement, StockAcquisition, ProductImei, Sale, Invoice, Claim, WebsiteOrderAllocation, reservation/confirmation/release, settings, branding, backup and restore capabilities.
- Website includes Product, User, Order/OrderItem, Payment, ProductReview, ServiceRequest, ProjectQuote, managed pages/navigation/media, configuration revisions, verified payments, COD collection and POS sync/outbox services.
- The existing architecture uses separate Laravel databases and narrow HTTP synchronization. The new Goal replaces that boundary with one master MySQL architecture. This does not authorize restructuring the original source repositories.
- Both `mobiST Control Center/MobiSTControlCenter.cs` copies have identical SHA-256: `bbf54a8db8ef3e871894f9f543555b61007b7dca4fbbb86cd2ea7f19b6a50fcd`. Source Control uses hard-coded old paths and Laravel ports 8000/8001. The new project must migrate Next.js lifecycle handling, safe process ownership and the new paths.
- Both roots contain `Brandkit - mobiST`. Brand approval, exact duplicates and runtime dependencies must be verified asset-by-asset under MT-1.1/MT-6.1; folder naming alone does not establish approval.

## Evidence discrepancies and risks

The Website README implementation overview and the original gap-analysis paragraphs in the old roadmap are initial-stage snapshots. Later reviewed points and the ledger are newer. Do not treat those stale explanatory sections as the current missing-functionality list; leave source files unchanged and reconcile actual code in the new parity register.

The separate-application requirement in the old Dynamic Platform Source belongs to the old-stage context. In this new project, the approved Goal's single-backend/single-master architecture is authoritative. A completed marker in source history does not replace fresh target testing.

Key migration risks include identity collisions, duplicate product/order records, private inventory fields, historical snapshots, encrypted-credential key dependencies, reservation races, provider idempotency, public cache invalidation and rollback. Full schema/content/route inventory, exports and tests were not yet complete when this baseline was created; MT-1.1/MT-1.2 resolve those areas.

## Protected operational state

Source `.env`, private database contents, API/payment secrets and customer records were not read or exported in this review. Source tests/builds can write caches, so future characterization must run only in isolated copies. Copied jobs/integrations must initially remain disabled or sandboxed. Production-hosting and provider HOLD boundaries remain unchanged.
