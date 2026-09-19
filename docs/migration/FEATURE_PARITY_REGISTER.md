# MT-1.1 - Source inventory and feature parity register

Status: Completed characterization; target parity remains Pending until the linked roadmap gates pass.

## Authority and evidence boundary

This register is based on immutable tracked blobs at POS `c61e47394e7b3db8a49cfe443c85b63835febc9b` and Website `04e7c49518f9f11f60c83ad44f9f4e2fd2539066`. It inventories source behavior for migration; it does not claim that target code, MySQL migration, React/Next interfaces, provider sandboxes or production infrastructure exist. The protected source working trees were not written or executed. Select tracked blobs were exported into ignored `.local/mt11` copies; dependencies were freshly installed there with Composer scripts/plugins disabled. The characterization harness used SQLite memory, array mail/cache, synchronous queues, disabled integrations, synthetic encryption key, local fake storage, `Http::preventStrayRequests()` and Vite bypass.

Fresh isolated source characterization passed: POS 202 tests / 2695 assertions; Website 236 tests / 2806 assertions. Errors, failures and skips were zero. These are source regression baselines only.

Machine evidence: `SOURCE_FILE_INVENTORY.json` contains every tracked path/blob/hash, category and preliminary retention decision. `SOURCE_SYMBOL_INVENTORY.json` contains source-line methods, references, migration declarations, registry keys, command declarations, 316 resolved routes and family assignments.

## Inventory totals

| Source | Tracked files | Selected isolated export | Models | Migrations | Resolved routes | Declared methods |
|---|---:|---:|---:|---:|---:|---:|
| POS | 824 | 769 | 24 | 50 | 167 | 1211 |
| WEBSITE | 400 | 356 | 18 | 29 | 149 | 1157 |

All 1,224 tracked files have one or more family assignments; the unmapped list is empty. Generated dependencies/builds, source Git, secrets, live databases/uploads/backups and executable binaries were excluded. Historical documents and old CI are indexed by hash but were not treated as new authority.

## Decision vocabulary

- **Reuse** means retain a proven rule, registry, validation, calculation, state machine or assertion with minimal change.
- **Adapt** means keep behavior while changing framework boundary, path, REST contract, presentation or storage integration.
- **Refactor** means move working controller/transport/process behavior behind shared services without changing its contract.
- **Migrate** means preserve schema/data/history/identity with explicit mappings and reconciliation.
- **Rewrite** is limited to source structures that cannot satisfy the target: Blade presentation adapters become React/Next interfaces; dual-database transport becomes shared transactions; Control process ownership must be made safe. This does not justify rewriting their business contracts.

## Capability register

### P01 - POS identity and outlet access

Source coverage: 470 tracked files; 54 resolved routes. Target owner: backend shared services and protected React/Inertia POS administration. Gates: MT-2.2, MT-4.1. Target status: Pending.

**Preserve:** Preserve three guards, assigned-outlet operator context, immutable OUTLET3 identity, permission defaults, password recovery and session revocation.

**Decision:** Migrate identities with explicit collision maps; adapt guards/controllers; reuse permission checks. Do not equate POS outlet User with Website customer.

**Parity gate:** Cross-role and cross-outlet direct requests denied; credential/reset/session behavior and historical shop ownership retained.

### P02 - POS products and master data

Source coverage: 45 tracked files; 18 resolved routes. Target owner: backend shared services and protected React/Inertia POS administration. Gates: MT-2.3, MT-4.2. Target status: Pending.

**Preserve:** Preserve protected categories, variant key, brand/model/capacity/condition options, acquisition source, managed unit status and historical option usage.

**Decision:** Reuse registries and validators; migrate IDs/options/usage; adapt persistence to shared schema. No category redesign.

**Parity gate:** Category-aware validation, identifier uniqueness, disabled-option history and managed-option deletion safeguards match source assertions.

### P03 - POS acquisition and stock

Source coverage: 28 tracked files; 12 resolved routes. Target owner: backend shared services and protected React/Inertia POS administration. Gates: MT-2.4, MT-4.2. Target status: Pending.

**Preserve:** Preserve acquisition, physical units and all required IMEI slots, quantity stock, pricing and stock-movement history.

**Decision:** Reuse stock rules; refactor controller transactions into shared services; migrate units/movements. Preserve app-owned availability states.

**Parity gate:** MySQL concurrent sale/reserve/return proves no oversell, duplicate IMEI or movement; rollback and inventory totals reconcile.

### P04 - POS sales invoices and returns

Source coverage: 28 tracked files; 8 resolved routes. Target owner: backend shared services and protected React/Inertia POS administration. Gates: MT-2.5, MT-4.2, MT-4.3. Target status: Pending.

**Preserve:** Preserve POS checkout, sale/invoice numbers, totals/discounts, customer snapshots, return/cancellation effects and outlet business identity.

**Decision:** Reuse arithmetic and snapshots; refactor transactional controller work; migrate history; adapt React forms and print bindings.

**Parity gate:** Legacy sale and negative flows, precise totals, returns, stock/accounting changes, historical invoices and permissions match.

### P05 - POS warranty and claims

Source coverage: 15 tracked files; 6 resolved routes. Target owner: backend shared services and protected React/Inertia POS administration. Gates: MT-2.6, MT-4.3. Target status: Pending.

**Preserve:** Preserve warranty jobs, durations, versioned clauses, claims and sale-time warranty snapshots.

**Decision:** Reuse clause validation/snapshot services; migrate claim/history relations; adapt interface.

**Parity gate:** Expiry boundaries, claim lifecycle and role denial, old/new clause snapshots and document output retained.

### P06 - POS reports customer history and documents

Source coverage: 83 tracked files; 36 resolved routes. Target owner: backend shared services and protected React/Inertia POS administration. Gates: MT-3.1, MT-4.3. Target status: Pending.

**Preserve:** Preserve outlet/customer history, dashboards, category reports, profit summaries, exports, invoice/warranty A4 and Thermal output and safe messaging.

**Decision:** Reuse query/formatting/template contracts; adapt shared services and React screens. Preserve historical snapshot output.

**Parity gate:** Role-scoped totals/CSV, filters and date boundaries; A4/80mm print/download; protected placeholders and safe communication links.

### P07 - POS Dynamic Platform presentation

Source coverage: 148 tracked files; 32 resolved routes. Target owner: backend shared services and protected React/Inertia POS administration. Gates: MT-3.2, MT-4.4. Target status: Pending.

**Preserve:** Preserve distinct POS settings, branding/theme, safe media, navigation/form/table/dashboard/report presentation, preview/publish/revisions/rollback.

**Decision:** Reuse settings registries and sanitizers; adapt runtime presentation from Blade to Inertia; migrate revisions/media usage.

**Parity gate:** Per-section and publish permission checks, draft isolation, safe upload/path rules, cache invalidation, audit and rollback parity.

### P08 - POS backup audit and operations

Source coverage: 33 tracked files; 19 resolved routes. Target owner: backend shared services and protected React/Inertia POS administration. Gates: MT-3.3, MT-7.1, MT-7.4. Target status: Pending.

**Preserve:** Preserve backup manifests/history, download/delete rights, verify-only restore, code/key/target guards, secret-safe audit and operational health.

**Decision:** Reuse manifest/redaction guards; adapt storage/process paths and shared-schema backup. Keep destructive reset separately guarded, never auto-run.

**Parity gate:** Isolated backup/restore and failure tests; no secrets in audit/errors; ownership guards; recovery controls and scheduled job disablement.

### X01 - Reservation confirmation release and reconciliation

Source coverage: 40 tracked files; 11 resolved routes. Target owner: backend shared transactional services and REST contracts. Gates: MT-2.7, MT-3.3, MT-3.4. Target status: Pending.

**Preserve:** Preserve reservation expiry, allocations, idempotency, replay checks, confirmation-versus-release exclusion, retries and reconciliation.

**Decision:** Reuse state machine/contract validations; refactor transport into shared Laravel transactions. Retire dual-database transport only after replacement acceptance, never its invariants.

**Parity gate:** Same request replay, conflicting request hashes, expiry/confirm/release races, partial failure/retry and order-stock-payment reconciliation on MySQL.

### W01 - Website customer and admin identity

Source coverage: 40 tracked files; 46 resolved routes. Target owner: backend services/protected CMS plus website Next.js REST client. Gates: MT-2.2, MT-4.1, MT-4.4, MT-5.2. Target status: Pending.

**Preserve:** Preserve customer auth/account, owner/manager/content_editor/operations roles, permission matrix, admin avatar/profile/password, audit and signed order access.

**Decision:** Reuse policy/validation and session boundaries; migrate identity mapping; adapt CMS administration into backend and public account into Next.js.

**Parity gate:** Guest/customer/admin cross-role denial, account ownership, signed-link expiry, profile upload limits, last-owner safety and password reset parity.

### W02 - Public catalogue and comparison

Source coverage: 17 tracked files; 11 resolved routes. Target owner: backend services/protected CMS plus website Next.js REST client. Gates: MT-2.3, MT-3.4, MT-5.1. Target status: Pending.

**Preserve:** Preserve category catalogue, search/filter/sort/pagination, product/variant details, comparison, availability, zero-stock visibility and legacy redirects.

**Decision:** Reuse query/presentation/public allowlist contracts; replace copied POS catalogue with shared-authority reads only after freshness parity; adapt Next pages.

**Parity gate:** Same category/filter/variant/price results, private fields never public, zero stock handled correctly, POS writes reflected through APIs and redirects retained.

### W03 - Cart orders and reviews

Source coverage: 29 tracked files; 16 resolved routes. Target owner: backend services/protected CMS plus website Next.js REST client. Gates: MT-2.7, MT-3.4, MT-5.2. Target status: Pending.

**Preserve:** Preserve multi-line cart, quantity update/removal, order history/status/invoice access, review eligibility/moderation and admin order CSV/status operations.

**Decision:** Reuse validation/ownership/review rules; migrate records and snapshots; adapt session cart and REST/Next interfaces.

**Parity gate:** Guest-to-account/cart recovery, quantity/price changes, duplicate submit, ownership, signed access, eligible purchase review and moderation.

### W04 - Payments COD hosted card and wallets

Source coverage: 52 tracked files; 16 resolved routes. Target owner: backend services/protected CMS plus website Next.js REST client. Gates: MT-2.7, MT-3.3, MT-5.3. Target status: Pending.

**Preserve:** Preserve payment manager/provider contracts, verified amount/currency/order/reference, COD collection, callback/webhook replay safety, retry/cancel and encrypted credentials.

**Decision:** Reuse provider interfaces, verification and disabled-provider failures; adapt persistence/URLs; no invented gateway implementation or raw PAN/CVV collection.

**Parity gate:** Amount/owner/reference tampering and replay denied; COD works; hosted gateway fakes prove contract only; authentic provider sandbox remains H-02.

### W05 - Digital services requests and project quotes

Source coverage: 24 tracked files; 9 resolved routes. Target owner: backend services/protected CMS plus website Next.js REST client. Gates: MT-3.2, MT-3.4, MT-5.4. Target status: Pending.

**Preserve:** Preserve digital services/content, request references/status, approved quote amounts/currency, secure payment tokens and paid quote linkage.

**Decision:** Reuse quote/request validation and signed access; migrate service/quote/order relationships; adapt CMS and Next public flows.

**Parity gate:** Approved quote amount cannot be changed by client, ownership/token expiry and request privacy hold, status/history and paid linkage preserved.

### W06 - Website managed content navigation and SEO

Source coverage: 72 tracked files; 45 resolved routes. Target owner: backend services/protected CMS plus website Next.js REST client. Gates: MT-3.2, MT-4.4, MT-5.4. Target status: Pending.

**Preserve:** Preserve managed page templates/content/SEO, nested menus/destinations, homepage section registry, header/footer, promotions, legal pages and protected routes.

**Decision:** Reuse registries/sanitization/revision services; migrate content; adapt admin and Next rendering. Keep publication and rollback semantics.

**Parity gate:** Draft isolation, protected route collisions, safe markup/URLs/media, ordering/visibility, publication/rollback and cache invalidation match.

### W07 - Website themes media settings and recovery

Source coverage: 119 tracked files; 15 resolved routes. Target owner: backend services/protected CMS plus website Next.js REST client. Gates: MT-3.2, MT-3.3, MT-4.4. Target status: Pending.

**Preserve:** Preserve Website-specific setting keys, brand/theme tokens, safe upload/usage and replacements, secret envelopes, revisions and recovery.

**Decision:** Reuse registries and recovery guards; migrate logical namespace and usage; adapt admin UI and public rendering.

**Parity gate:** Secret masking/key mismatch, unsafe SVG/path rejection, usage-aware deletion/replacement, theme contrast/responsive fallbacks and revision recovery.

### W08 - Website integration health and jobs

Source coverage: 13 tracked files; 2 resolved routes. Target owner: backend integration jobs, audit and operational health. Gates: MT-3.3, MT-7.4. Target status: Pending.

**Preserve:** Preserve safe integration status, outbox/reconciliation job semantics, rate/retry limits and redacted audit; distinguish local test helpers from operational commands.

**Decision:** Adapt health checks to one backend; retain recovery jobs where external I/O remains; retire obsolete polling only with recorded replacement evidence.

**Parity gate:** No arbitrary endpoint/command execution, disabled integration makes no call, fake retry/failure tests and scheduler/queue recovery; local mark-paid never production API.

### B01 - Canonical brand and runtime assets

Source coverage: 459 tracked files; 0 resolved routes. Target owner: brand canonical masters; generated backend/website runtime derivatives. Gates: MT-6.1. Target status: Complete.

**Preserve:** Preserve approved editable logo/wordmark, fonts/icons/favicon/watermark/print/runtime references and license requirements.

**Decision:** Deduplicate byte-identical masters into root brand later; retain runtime derivatives only when referenced. Do not migrate old Control logo as approved artwork.

**Parity gate:** Master hashes and asset manifest, view/style/build references, fallback dimensions and print contrast verified in both target applications.

### C01 - Windows Control and developer launch tooling

Source coverage: 28 tracked files; 0 resolved routes. Target owner: tools/mobist-control single Windows application. Gates: MT-6.2, MT-6.3. Target status: In Progress (MT-6.2 complete; MT-6.3 pending).

**Preserve:** Preserve useful Start/Stop/Restart/Open/Status, LAN/QR, browser reuse and operator diagnostics; add required Start All/Stop All with current approved logo.

**Decision:** Adapt useful C# UI; refactor process ownership and hard-coded paths. Port-only taskkill is unsafe; executable mirrors/launcher backup are not authoritative target source.

**Parity gate:** Exact target PID/path ownership, occupied ports/stale PIDs, repeated all-actions, partial failure, no orphan/duplicate tabs, no source/unrelated process termination.

### Q01 - Regression security performance and migration gates

Source coverage: 37 tracked files; 0 resolved routes. Target owner: backend tests, website Playwright and .github monorepo CI. Gates: MT-7.1, MT-7.2, MT-7.3, MT-7.5. Target status: Pending.

**Preserve:** Retain source regression assertions and negative security/performance/rollback contracts as migration evidence, not proof of target completion.

**Decision:** Adapt fixtures/tests for shared schema and new UI incrementally; reuse assertions. Replace source-text Blade checks with behavior plus target structure checks where appropriate.

**Parity gate:** Fresh target PHPUnit/Pest, MySQL concurrency, builds and Playwright; no source tests quoted as target results; all registered capabilities independently close.

### F01 - Framework schema and build foundation

Source coverage: 178 tracked files; 4 resolved routes. Target owner: backend foundation, website build contracts and .github. Gates: MT-1.2, MT-1.3, MT-2.1, MT-7.3. Target status: Pending.

**Preserve:** Preserve Laravel configuration, middleware ordering, migration history/constraints, reproducible locks and application boot contracts.

**Decision:** Reuse Laravel13 foundation, adapt shared migrations/config/CI, install dependencies fresh. React/Next presentation adapters are new; business logic rewrite is not justified.

**Parity gate:** Shared schema collision/foreign key map, fresh setup and migration apply/rollback/reapply, exact toolchain locks, monorepo CI and environment isolation.

### H01 - Historical documentation and metadata

Source coverage: 45 tracked files; 0 resolved routes. Target owner: docs migration evidence and operational runbooks. Gates: MT-7.5. Target status: Pending.

**Preserve:** Keep provenance of prior source acceptance and operational instructions without adopting old dual-database authority.

**Decision:** Retain inventory/hash references; migrate only useful current runbooks. Source roadmap/old launch paths and duplicate documents are historical.

**Parity gate:** Every required legacy behavior traced to target evidence; stale instructions cannot override Goal, Preferences or new registry.

## Verified conflicts, gaps and retirement candidates

1. **Identity collision:** POS `User` is the outlet/shop record with immutable three-digit outlet code; Website `User` is customer identity and can also carry Website admin role. POS also has distinct `Admin`, `SuperAdmin` and `shop_admins`. MT-1.2 must define separate concepts and collision maps; table-name or numeric-ID equality cannot authorize merging.

2. **Duplicated products and orders:** Website stores a synced catalogue plus its own orders/payments/outbox while POS stores authoritative inventory plus reservation/allocation/request records. These copies and HTTP synchronization are retirement candidates only after one-schema transactional replacement passes X01/W02/W03/W04. Their idempotency, reservation, pricing and release rules remain required.

3. **Return scope gap:** source inspection found an inventory stock-unit return-to-stock operation, but no routed completed-sale refund/return workflow. The Goal still requires returns. MT-2.5 must define and test the missing sale-return contract from verified business requirements rather than pretend source parity already exists.

4. **Payment boundary:** COD and provider abstractions/configuration are reusable. JazzCash/Easypaisa authentic sandbox claims and an approved hosted/tokenized card gateway remain H-02. Disabled behavior, callback verification, retries and secret recovery are required; raw PAN/CVV handling is prohibited.

5. **Control safety:** both source repositories contain the same old Control source/mirror. It hard-codes old paths/ports and can `taskkill` any listener PID found on a port. Useful UI/browser/LAN/status behavior may be adapted once, but stop/restart must verify exact target process ownership. Old executable/logo/launcher backups are not approved masters.

6. **Unreferenced legacy presentation:** old POS course/badge/card/search Ajax partials are not referenced by current routes/controllers and contain unrelated school-template concepts. They are indexed historical/retirement candidates, not valid capabilities. Removal becomes final only when target full register and source route/view-reference acceptance confirm no dependency.

7. **Brand duplication:** source master artwork is byte-identical across repositories; runtime assets have application-specific references. MT-6.1 will retain one approved root master plus an explicit derivative/runtime manifest, after current-logo approval mapping.

8. **Local operational helpers:** interactive mail/account/test-payment/quote creation and go-live/reset commands are not public target APIs. Useful guarded operator behavior must be reviewed under F01/P08/W08; source-mutating launch/reset scripts and stale paths are not auto-migrated or executed.

## Retention and traceability rules

Every source file is traceable by source commit, Git blob and SHA-256. A file may serve more than one family; every assigned family gate must pass before retirement. File classification is a migration decision aid, not permission to bulk-copy. Dependencies and generated assets must be reinstalled/regenerated, licenses retained as applicable, and sensitive/runtime material must come from target-only secret/data procedures.

Target parity remains Pending for every family. During later points, evidence must update the register by stable family ID and source path, linking target services/routes/tests and recording migrated, retired-with-proof or blocked status. MT-7.5 and FINAL-AUDIT must show no valid capability silently dropped.

## Approved scope expansion overlay - 2026-09-01

`../PROJECT_REQUIREMENTS_ADDENDUM_v1.1.md` adds approved requirements beyond source parity. `../REQUIREMENTS_ADDENDUM_v1.1_RECONCILIATION.md` maps every leaf clause to the v1.6 roadmap and fresh acceptance gates. P02/P03 acquisition/inventory, P04 sales, P05 claims, P06 reporting, P08 operations, W02/W03/W04 commerce, W05 digital and W06/W07 CMS families are reuse anchors, not proof that added stocktake/transfer/procurement/reset/mode/digital functionality exists. Completed backend component evidence remains in the ledger; full family parity still includes pending interfaces and integration.

New features are approved extensions, not retroactive source capabilities or source completion claims. Original source family IDs, source inventories/counts, characterization and retirement decisions remain unchanged. MT-7.5 and FINAL-AUDIT must close both this source register and the addendum clause register; no new priority/optional feature may be silently relabeled Deferred.
