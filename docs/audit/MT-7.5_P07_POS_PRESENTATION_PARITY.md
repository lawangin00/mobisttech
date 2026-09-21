# MT-7.5 P07 — POS Dynamic Platform presentation (PARTIAL)

Status: **OPEN**. This file records bounded target acceptance, not P07 family closure. The authoritative source feature register's **148 tracked files and 32 resolved routes** now have explicit dispositions in `MT-7.5_P07_SOURCE_CROSSWALK.md`; remaining rendered column/dashboard/report and theme/branding runtime acceptance is still open.

## 21-Sep-2026: Per-section POS configuration controls and branding revisions

Confirmed target UI gap: `backend/resources/js/pages/platform-admin.tsx` already disabled generic document/theme draft and revision actions by the appropriate permission, but the branded-media revision Publish/Rollback buttons did not check `config.branding.manage`; the document, theme and branding edit controls themselves remained editable to a role without that section's permission. Backend `App\Pos\PosConfiguration` already authorizes each domain before preview, draft, publish, rollback and branding upload. Do not mistake the former permissive UI for server authorization.

Scoped correction: POS configuration inputs/selects and branding alt text are disabled for users without the matching `config.documents.manage`, `config.theme.manage` or `config.branding.manage` permission and while an operation is busy. Branding revision Publish/Rollback now mirror existing per-section busy/permission gating; already accepted backend authorization, safe media storage, draft isolation and published state semantics are unchanged.

Focused backend role-scope proof: `PlatformAdministrationInterfaceTest::test_platform_data_is_permission_scoped_and_never_projects_provider_secrets` on isolated `mobisttech_test` **1/1 PASS (27 assertions)**. Documents-only Admin receives 403 on direct theme preview, branding draft and branding media upload; branding data is absent from its domain projection and existing provider-secret suppression remains enforced. React/TypeScript production Vite build **PASS (588 modules)**, scoped PHP syntax/Pint and TypeScript checks **PASS**.

Real Edge acceptance: guarded `PosShellE2eSeeder` synthetic fixture, owned backend server and isolated MySQL; the existing full `platform-administration.spec.ts` with added document-only and branding-only sections verified correct disabled/enabled input and revision actions, including actual branded revision Publish/Rollback request routing: **1/1 PASS (48.6 s)**. Scoped synthetic cleanup, isolated cache clear, backend and MySQL shutdown **PASS**. The first conventional Playwright launch stalled before test setup and was terminated without acceptance; it also shut down its owned isolated MySQL. The successful run used a temporary focused Playwright configuration and externally owned server/fixture; no unrelated test, provider or production operation was counted.

Remaining P07: read-only source 32-route/148-file disposition; rendered POS settings/theme/branding/presentation and navigation/table/form/dashboard across roles and outlets; revision and rollback runtime propagation; safe media/path and draft/public isolation negatives with exact-scoped fixture evidence. **P07 OPEN; MT-7.5 remains 12/27 DONE, 15 OPEN; P06 remains complete.**

## 21-Sep-2026: Separate POS publication authority (bounded PARTIAL)

Read-only original source `C:\mobiST\mobiST-POS\routes\admin.php` lines 30-42 and `superadmin.php` lines 33-45: branding, theme and document preview/edit require their own section permission, while publish/rollback additionally require independent `config.publish`. Target retained the `config.publish` permission in the unified Admin catalogue but `App\Pos\PosConfiguration::publish/rollback` previously required only the section permission; UI likewise allowed section editors to publish. This is a confirmed source-contract and protected-boundary gap, not an old-account migration requirement.

Scoped target correction: keep preview/draft/media upload section-scoped, require both the section permission and `config.publish` server-side for publish and rollback, including a denial before rollback creates a derivative draft; rendered document/theme/branding Publish and Rollback buttons require both permissions. Independent published revision validation, cache invalidation, audit and media/path checks remain unchanged. Never infer a publish permission from `website.publish` or a section-editor role.

Acceptance: isolated `mobisttech_test` focused `PlatformAdministrationInterfaceTest` **2/2 PASS (58 assertions)**, including documents-only author denied direct publish of its own draft, denied rollback of published revision, unchanged published document value and existing cross-domain 403 checks. Real Edge protected `platform-administration.spec.ts` **1/1 PASS (49.4 s)**: section editor can edit/draft but cannot publish/rollback, branding editor without publish authority likewise cannot, and separately authorized branding publisher can publish/rollback; fresh guarded `PosShellE2eSeeder` fixture exact cleanup, isolated cache clear and owned backend/MySQL shutdown PASS. React/TS/Vite production build PASS (588 modules); scoped PHP syntax, Pint and diff check PASS. No live/provider/source data touched.

**P07 remains OPEN**: original 32-route/148-file crosswalk, rendered runtime theme/branding/nav/table/dashboard/report and media/rollback propagation remain independently unaccepted. Finite count **12/27 DONE, 15 OPEN**; P06 remains complete.

## 21-Sep-2026: Safe unused-media deletion (bounded PARTIAL)

Read-only source review confirmed that the original `SafePosMedia` service permits deletion only for unreferenced assets. The target already validated branding uploads, randomized storage paths, role constraints and published usage records, but it exposed no controlled deletion path.

The protected POS configuration service and React Admin now expose **Delete unused** for branding media. Deletion requires `config.branding.manage`, locks the active media record, rejects non-public or non-branding paths, rejects every published usage, and additionally rejects assets retained by draft, published or superseded branding revision snapshots so rollback history cannot be broken. Only an unreferenced asset row is removed; its exact safe storage object is deleted after database commit and the action is audited. No replacement, bulk deletion or path supplied by the client is accepted.

Focused HTTP verification passed **1/1 (19 assertions)**; the complete current `PlatformAdministrationInterfaceTest` passed **4/4 (134 assertions)**. It proves a revision-retained asset returns HTTP 409 and remains stored, then an unreferenced asset is removed from both database and controlled storage. Production TypeScript/Vite build passed (588 modules); scoped PHP syntax and Pint passed after normalizing one changed controller line ending. The first concurrently launched focused test had no retrievable terminal result and remains recorded as `ORCHESTRATION_FAIL`; the separately rerun focused and full-class gates are the accepted results.

**P07 remains OPEN** for the 32-route/148-file disposition and runtime navigation/table/dashboard/report presentation plus theme/branding propagation across the rendered POS surfaces. Finite count remains **12/27 DONE, 15 OPEN**.

## 21-Sep-2026: Safe POS navigation presentation (bounded PARTIAL)

Read-only source review of `PosPortalNavigation` and its acceptance tests confirmed the intended boundary: selected optional modules may change label, visibility and presentation order, while route destinations, permissions and protected core modules remain application-owned. Hiding navigation never revokes or grants backend authorization.

The target business-wide Portal Preferences screen now manages the equivalent React POS navigation contract. Invoices, Warranty, Claims, Master data, Outlet profile, Reports and Operations accept a plain 1–40 character label, boolean visibility and bounded order 100–999. Sales and Inventory remain visible with fixed labels; unknown modules, route/permission metadata, markup/control characters and invalid order/visibility fail closed. `PosShell` applies the stored presentation only after effective permission filtering and continues to authorize direct workspace routes independently.

Focused HTTP acceptance passed **1/1 (74 assertions)**, including protected-core rejection, optional-item persistence, hidden-navigation rendering and direct authorized route access. The complete current `PosShellTest` passed **22/22 (1,050 assertions)**. Production TypeScript/Vite build passed (588 modules); PHP syntax and scoped Pint passed after the recorded formatting-only correction.

**P07 remains OPEN** for table/dashboard/report presentation, complete runtime theme/branding propagation and final 32-route/148-file disposition. Finite count remains **12/27 DONE, 15 OPEN**.

## 21-Sep-2026: Responsive history-density presentation (bounded PARTIAL)

Read-only source review confirmed that `PosPortalTablePresentation` treats table density as a safe presentation preference. The target uses responsive card/history surfaces rather than copying the legacy table implementation, so the equivalent business-wide preference is now registered as `comfortable` or `compact` for invoice history and warranty/claim history. The authoritative history projection includes the resolved density, and the React reporting workspace applies it to its root spacing while exposing a stable `data-density` value for rendered verification. Authorization, outlet scoping, search, pagination and record contents remain application-owned and unchanged.

The inventory density preference is also registered and manageable, but runtime stock-control consumption remains pending. Safe sort, filter and column controls plus inventory, dashboard and report presentation remain separate acceptance work.

After the recorded formatting-only correction, joined isolated MySQL HTTP verification passed **2/2 (134 assertions)** and scoped Pint passed. Production TypeScript/Vite build passed **588 modules**. **P07 remains OPEN; 12/27 DONE, 15 OPEN.**

## 21-Sep-2026: Responsive inventory-density propagation (bounded PARTIAL)

The already registered business-wide `inventory_density` preference now propagates through the outlet-scoped inventory listing projection and is consumed by the real React Inventory workspace as `comfortable` or `compact` responsive spacing. Sales catalogue requests continue to omit inventory pagination/presentation state. Search, pagination, outlet authorization, stock values, product fields and direct actions remain server-owned and unchanged.

Focused isolated MySQL verification passed **1/1 (32 assertions)** after the recorded line-ending-only formatting correction. Scoped Pint and diff checks passed; production TypeScript/Vite build passed **588 modules**. Safe sort, filter and column presentation plus dashboard/report presentation remain pending. **P07 remains OPEN; 12/27 DONE, 15 OPEN.**

## 21-Sep-2026: Safe inventory sort and filter presentation (bounded PARTIAL)

Read-only source review confirmed allowlisted Inventory presentation modes for operational/product order, stock and sale-price order, and all/in-stock/out-of-stock/IMEI-tracked filters. The target now validates those exact business-wide choices through Portal Preferences and applies them server-side to the already outlet-scoped Inventory query. Clients receive only the resolved safe mode labels; they cannot submit SQL columns, directions or patterns. Stable ID ordering remains the final tie-break, and Sales catalogue behavior remains independent.

Joined isolated MySQL verification passed **2/2 (115 assertions)**, including high-stock-first ordering, in-stock filtering, registered-preference validation and persistence. Scoped Pint and diff checks passed; production TypeScript/Vite build passed **588 modules**. Column visibility/order, invoice/claim sort/filter, dashboard/report presentation and final route/file disposition remain pending. **P07 remains OPEN; 12/27 DONE, 15 OPEN.**

## 21-Sep-2026: Safe invoice and claim sort/filter presentation (bounded PARTIAL)

The source allowlists operational/newest/oldest/total invoice order with an optional discounted-only view, and operational/updated/received/claim order with active/completed warranty-job views. Target Portal Preferences now validates those business-wide choices and applies them only as server-owned query branches. Clients receive resolved mode labels but cannot submit columns, directions, SQL patterns or status lists. Outlet and role scope, search, page bounds and record fields remain unchanged.

After two recorded patch-context orchestration failures, the reconciled exact-literal strategy succeeded. Joined isolated MySQL verification passed **2/2 (148 assertions)**, including one discounted invoice and one completed claim filter result plus preference registration/persistence. Scoped Pint and diff checks passed; production TypeScript/Vite build passed **588 modules**. Column visibility/order, dashboard/report presentation and final route/file disposition remain pending. **P07 remains OPEN; 12/27 DONE, 15 OPEN.**

## 21-Sep-2026: Complete source file and route disposition crosswalk

`MT-7.5_P07_SOURCE_CROSSWALK.md` now disposes every P07 item from the pinned MT-1.1 inventory: **148/148 files and 32/32 resolved routes**. It records unified-Admin adaptations, shared-schema reuse, React replacements, overlap ownership under P01-P06/P08 and retired legacy Blade/Ajax/desktop transport without treating transport duplication as required behavior. The reproducible `tools/docs/build_p07_crosswalk.ps1` generator pins the inventory SHA-256 and hard-fails unless both written counts match the source counts.

Generation attempts and the corrected row-count method are recorded in the ledger. Final generator verification passed with inventory SHA-256 `c5368e4121eaaac727efe72b87064ae8367ed1231419b6159642115d2bcbb17e`; diff check passed. This closes the source inventory/route-disposition gate only. Column visibility/order, dashboard/report presentation, full published theme/branding propagation and final rendered role/outlet browser acceptance remain pending. **P07 remains OPEN; 12/27 DONE, 15 OPEN.**

## 21-Sep-2026: Permission-scoped dashboard/report section presentation (bounded PARTIAL)

The source dashboard contract permits safe widget visibility/order without changing calculations, data or permissions. The target adapts that boundary to its combined **Outlet dashboard & reports** React workspace: Sales metrics, Payment mix, Category performance/inventory and Operational activity each have validated business-wide visibility and bounded order. The protected editor and mutation contract require the independent `config.dashboard-reports.manage` permission plus outlet-management authority; portal-navigation permission alone is insufficient. Read-only labels and unknown sections are rejected, the stored JSON shape is fixed, updates are reauthorized inside the transaction and audited.

The report endpoint projects only resolved safe presentation metadata after existing `reports.view` and outlet authorization. React applies visibility/order to rendered sections while date filters, CSV, totals, payment drill-down, report data and direct authorization remain server-owned and unchanged. Hidden presentation does not remove authorized data from the API and is not represented as a security boundary.

After correcting the test-only read-label payload and scoped formatting drift, focused isolated MySQL HTTP verification passed **1/1 (40 assertions)**. The complete current `PosShellTest` passed **23/23 (1,093 assertions)**. It covers guest/insufficient-authority denial, strict unknown-section rejection, persistence, audit, POS-shell discovery and runtime report projection alongside the existing shell regressions. Scoped Pint/diff check passed; production TypeScript/Vite build passed **589 modules**. Column visibility/order, full published theme/branding propagation and final rendered role/outlet browser acceptance remain pending. **P07 remains OPEN; 12/27 DONE, 15 OPEN.**

## 21-Sep-2026: Safe responsive column/field presentation (bounded PARTIAL)

The source table contract distinguishes protected identifiers/actions from optional visible/reorderable columns. The target adapts that behavior to its responsive React surfaces instead of copying legacy DataTables indexes. Portal Preferences now manages an exact allowlist for Invoice, Warranty/Claim and Inventory field presentation. Invoice number, Claim, Product and Actions remain visible at fixed protected order; optional totals/customer/contact/date/salesperson, invoice/device/received/status/updated and variant/cost/price/stock/IMEI/unit/history fields accept boolean visibility and bounded order below protected Actions. Unknown metadata, incomplete areas, invalid types/ranges and attempts to hide/reorder protected fields fail closed.

The outlet-scoped listing services project only the resolved safe field presentation. React applies visibility/order to invoice details, warranty/claim details and inventory product cards while selectors, identifiers, actions, backend queries, search fields, direct routes, financial/stock values and permissions remain unchanged. Hidden fields remain authorized API data and are explicitly presentation state rather than a security boundary.

After two recorded validation/test-orchestration corrections, focused joined HTTP verification passed **2/2 (130 assertions)**. Full current `PosShellTest` plus `PosTransactionInterfaceTest` passed **29/29 (1,261 assertions)**. Scoped Pint/diff check passed; production TypeScript/Vite build passed **589 modules**. Full published theme/branding propagation and final rendered role/outlet browser acceptance remain pending. **P07 remains OPEN; 12/27 DONE, 15 OPEN.**

## 21-Sep-2026: Published POS shell theme and branding runtime (bounded PARTIAL)

`PosConfiguration` now projects a public runtime presentation contract from published `pos_settings` only. Draft and preview revisions remain isolated. The contract exposes validated theme tokens plus role-specific branding assets with canonical wordmark/mark fallbacks. Runtime media resolution requires an active public-disk PNG/WebP under the controlled branding path and an existing storage object; a missing or unsafe object falls back without exposing storage metadata.

The POS login and authenticated desktop/mobile shell consume the published theme, login/header logo and favicon. The shell applies background, surface text foundation, active-navigation and focus tokens while preserving route authorization, outlet scope and operational data. The same runtime contract resolves invoice, warranty, app and desktop roles for their later rendering consumers without allowing drafts to affect live output.

Focused isolated MySQL verification passed **1/1 (64 assertions)** for draft isolation, publish propagation, authenticated and guest Inertia projections, missing-object fallback and rollback restoration. Joined `PlatformAdministrationInterfaceTest` plus `PosShellTest` passed **28/28 (1,303 assertions)**; production TypeScript/Vite build passed **589 modules**; PHP syntax, scoped Pint and diff checks passed after the recorded formatting-only correction. The conventional focused Playwright run produced no terminal result and was stopped under the Finalization Fence, so no browser PASS is claimed. Final rendered role/outlet browser acceptance and document-surface logo consumption remain open. **P07 remains OPEN; 12/27 DONE, 15 OPEN.**

## 21-Sep-2026: Published document presentation and partial direct-Edge acceptance (bounded PARTIAL)

Canonical invoice and warranty rendering now consumes published document settings and role-specific branding. Invoice output honors the configured logo, business/contact fields, customer CNIC, salesperson, warranty terms, thank-you text, footer alignment and default A4/Thermal selection. Warranty output honors its business/contact, customer CNIC, assignment, expected-completion and status controls and its independent warranty logo. Drafts remain isolated; unsafe/missing media uses the canonical fallback. HTML preview/print renders the resolved logo, while PDF text content honors the same visibility/footer settings; raster logo embedding in the generated PDF remains open.

Focused document propagation passed **1/1 (18 assertions)**. Joined document/platform/shell regression passed **34/34 (1,373 assertions)** before scoped formatting normalization; the focused test, scoped Pint and production TypeScript/Vite build (589 modules) then passed again. Direct computer-use Edge verified the desktop Sales role shell, default published theme/wordmark, permission-filtered navigation, and the 390x844 Inventory-user outlet-required shell with no horizontal overflow. The remaining mobile outlet-selection continuation was not started after the 12-minute Finalization Fence. Conventional Playwright orchestration repeated its no-terminal-result signature and activated `LOOP_GUARD`; it must not be rerun unchanged. **P07 remains OPEN; 12/27 DONE, 15 OPEN.**
