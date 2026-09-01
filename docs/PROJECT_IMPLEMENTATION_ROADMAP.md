# mobiST Tech - Project Implementation Roadmap

Version: 1.7 | Date: 2026-09-01

Canonical Goal: docs/PROJECT_GOAL.md

Binding Preferences: docs/PROJECT_PREFERENCES.md

Approved scope expansion: docs/PROJECT_REQUIREMENTS_ADDENDUM_v1.1.md

Addendum traceability: docs/REQUIREMENTS_ADDENDUM_v1.1_RECONCILIATION.md

Approved superseding requirement: docs/PROJECT_REQUIREMENTS_UNIFIED_ADMIN_GOOGLE_v1.0.md

Superseding reconciliation: docs/remediation/RECONCILIATION.md

Source of Truth: docs/PROJECT_SOURCE_OF_TRUTH.md

Status ledger: docs/PROJECT_IMPLEMENTATION_STATUS.md

Word mirror: docs/PROJECT_IMPLEMENTATION_ROADMAP.docx

## Scope and execution rules

This is the single active structural roadmap for the controlled migration. Live Completed/In Progress/Pending state, last/current/next position and progress counts are recorded only in `docs/PROJECT_IMPLEMENTATION_STATUS.md`. Each Proceed executes one point through its required gates and intended new-repository commit/push, then stops. Dependencies are ordered; point IDs and exact titles remain stable.

The original POS/Website folders, remotes and data are immutable references. All implementation, copied-source tests and migration rehearsals must run under `C:\mobisttech` or isolated approved target resources. Do not run tests/builds against the originals. Do not replace the single shared Laravel/MySQL authority with duplicate authoritative databases.

Every point includes applicable focused/full tests, parity evidence, secrets review, source-boundary checks and ledger updates. Routine execution progress updates the ledger only and must not edit this roadmap or regenerate its DOCX. Regenerate/verify the DOCX only when roadmap structure/content materially changes. MT-1.1 detailed inventory may refine this roadmap from evidence, but valid functionality must not be silently dropped and completed IDs must not be renumbered.

Goal, Preferences, approved addendum v1.1 and the approved unified Admin/Google integrations superseding requirement apply. For working features, assess reuse/adapt/refactor/migrate before rewrite; rewrite requires a verified reason. Verify parity, data consistency, integration and regression incrementally rather than using a big-bang rewrite. Prefer conventional solutions, justified Redis roles and a Laravel-only business backend; do not introduce a parallel Node/Express business backend. Record stack deviations only for verified unavoidable blockers and never silently resolve a Goal conflict.

Roman Urdu is limited to assistant chat/UI communication. Git-tracked project documentation and technical artifacts use standard English unless the user explicitly requests another language for a specific artifact. User-supplied Goal/Preferences remain byte-preserved in their original language/content.

Addendum v1.1 adds dependency-ordered points without renumbering existing IDs. Follow document order and explicit dependencies, not numeric ID sorting. P1/P2/P3 are implementation priorities within dependency constraints, not permission to defer required work. Optional loyalty/repair/booking features must be implemented with disablement; they are not silently removed from scope. Full clause traceability is in docs/REQUIREMENTS_ADDENDUM_v1.1_RECONCILIATION.md.

## MT-0 - Project initialization


### MT-0.1 - Project initialization

Dependencies: None

Scope: Preserve/reconcile the original Goal and approved Preferences; establish the protected source baseline, monorepo layout, canonical docs/registry, matching DOCX and independent private GitHub repository.

Acceptance: Verify Goal/Preferences hashes and full coverage, documentation parity, source-unchanged checks, independent Git history and remote equality. Record MT-0.1 re-verification evidence; do not start MT-1.1/application migration within this point.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-1 - Migration inventory and design


### MT-1.1 - Source inventory and feature parity register

Dependencies: MT-0.1

Scope: Inventory pinned source code, routes, roles, models, migrations, tests, integrations, Dynamic Platform, branding and Control; assess legacy files/folders for role, dependencies and retention value.

Acceptance: Record reuse/adapt/refactor/migrate options for every valid capability and a verified reason for any required rewrite; retire only unnecessary duplication. Record target owner, parity gate and isolated characterization; originals remain unchanged.

Evidence: docs/migration/FEATURE_PARITY_REGISTER.md; isolated source tests passed, originals unchanged, target parity pending.

### MT-1.2 - Unified data, API and security design

Dependencies: MT-1.1

Scope: Design the single MySQL schema, identity/record collision maps, customer merge rules, history, transaction boundaries, API versioning and authorization model.

Acceptance: Document contracts for orders, payments, stock reservation, price/currency precision, idempotency, cache invalidation, rollback and encrypted-data recovery; do not use two-way database synchronization as the target. Evidence: docs/design/README.md; specification checks pass, runtime acceptance pending.

### MT-1.3 - Windows toolchain and application foundations

Dependencies: MT-1.2

Scope: Establish Laravel 13, React/Inertia/TypeScript/Tailwind and Next.js foundations on the approved pinned stack; configure isolated MySQL plus concrete justified Redis/storage roles. Reuse/adapt useful source foundations where appropriate.

Acceptance: Fresh Windows setup, secret-free examples, lockfiles and smoke builds pass. Ports do not conflict with source servers; no nested `.git`, real provider calls or source-data connections exist.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-2 - Shared backend and transactional migration


### MT-2.1 - Shared backend schema and infrastructure

Dependencies: MT-1.3

Scope: Migrate the reusable Laravel core, migration schema, jobs, MySQL ownership, Redis roles and S3-compatible storage layer.

Acceptance: Disposable MySQL apply/rollback/reapply, storage isolation and queue/cache failure behavior pass; the Website does not receive DB credentials.

### MT-2.2 - Identity, customer and authorization migration

Dependencies: MT-2.1

Scope: Map POS roles/guards and Website customer identity into the shared backend; preserve session/password/account recovery and ownership behavior.

Acceptance: Role matrix, user/customer collision cases, session isolation, CSRF, IDOR and recovery tests pass; no unauthorized account merging occurs.

### MT-2.3 - Product and master-data migration

Dependencies: MT-2.2

Scope: Migrate products, variants/units, categories, attributes and stable master data; preserve public/private field separation.

Acceptance: ID/relationship maps, historical references and validation parity pass; protected category/identifier semantics do not silently change.

### MT-2.4 - Inventory and stock integrity migration

Dependencies: MT-2.3

Scope: Migrate acquisition, stock units/IMEIs, availability, movements and transaction-derived stock rules into shared services.

Acceptance: Concurrent sale/reservation, uniqueness, rollback and negative/duplicate-stock prevention pass on MySQL; snapshots reconcile.

### MT-2.8 - Addendum foundations and capability contracts

Dependencies: MT-2.4

Scope: Define and implement only backward-compatible schema primitives and versioned contracts required by addendum v1.1 before sales migration: Website capabilities, inventory custody/procurement references, monetary adjustment and milestone identities, reset preservation classifications and permission namespaces. Reuse current services; leave feature workflows to their named later points.

Acceptance: Document the complete addendum entity/API/permission matrix; verify additive MySQL apply/rollback/reapply and existing regressions. Resolve transfer identity versus immutable product/unit history, reset preservation versus transaction references, and sale/payment extension seams without rewriting completed migrations or enabling new public flows.

### MT-2.5 - Sales, invoices and returns migration

Dependencies: MT-2.8

Scope: Migrate sales, invoice totals/discounts, operational customer records and verified return behavior. Use the MT-2.8 extension contracts for later promotion, loyalty, trade-in and cash effects; do not hard-code a second money authority.

Acceptance: Money precision, identifiers, historical snapshots, return adjustments and stock/accounting effects match legacy acceptance. MT-1.1 found stock-return behavior but no full routed sale-refund flow; Goal-required sale returns need an explicit verified contract and fresh tests. Preserve extension identifiers/snapshots and stock custody history; later feature engines remain their own points.

### MT-2.6 - Warranty and claim migration

Dependencies: MT-2.5

Scope: Preserve warranty duration, versioned clauses, claim lifecycle and historical document relationships.

Acceptance: Sale-time snapshots, permissions, expiry/boundary conditions and historical warranty/claim output parity pass.

### MT-2.18 - Unified Admin identity and Google integrations remediation

Dependencies: MT-2.6

Scope: Prospectively supersede the completed MT-2.2 separate administrative credential model with one Admin credential shared by POS and Website administration. Require explicit verified legacy administrative mapping, reconcile POS/Website permissions, outlet access, session/reset behavior and customer isolation. Establish the canonical `mobiST Technologies` / `mobisttech@gmail.com` / `https://mobisttech.com` business profile. Implement backend-controlled Gmail OAuth/Gmail API send with only `gmail.send`, secure tokens, test/reconnect/disconnect and reset/transactional delivery. Implement dynamic Google Drive/rclone connection, private portable configuration, read/write/delete validation, Backup Now, schedule/retention and Windows/Linux provisioning without migrating old archives.

Acceptance: Removed Superadmin/Website-admin credential routes cannot authenticate; one Admin password/reset/session serves both administrative surfaces with explicit least-privilege permissions and outlet assignments. Same-email legacy records never union privileges. Current runtime consumers use the canonical business profile. Gmail remains Not Connected without authorization, uses exact approved account/scope, reports Connected only after an actual backend test send, never exposes secrets and addresses production OAuth publishing/verification. Drive detects or connects `mobisttech-drive:`, proves backend read/write/delete cleanup, keeps frontend away from rclone, executes encrypted new backups through backend jobs/scheduler, provisions portable private runtime/config, and leaves `mobist-drive:`/old archives untouched. Focused/full security, migration, integration, schema, build and DOCX parity/render/visual gates pass; historical MT-2.2 evidence remains unchanged.

### MT-2.7 - Unified orders, reservations and payments

Dependencies: MT-2.18

Scope: Migrate Website order/payment/COD/project-payment services into shared transactions and prove replacement of obsolete cross-database synchronization. Consume versioned Website capabilities for new order creation versus legitimate historical access and preserve project/milestone extension identities.

Acceptance: Price/amount/ownership verification, retry/replay, expiration, confirmation-versus-release, reconciliation and payment-failure recovery tests pass. Disabled providers remain safe. Mode-denied new commerce flows cannot bypass backend checks; callbacks and authorized historical resources remain safe across switching.

### MT-2.9 - Supplier and procurement services

Dependencies: MT-2.7

Scope: Extend acquisition-source capture with supplier/vendor profiles, contacts, purchase orders, lines, status, expected dates, partial receiving, landed/unit costs and supplier-to-acquisition history (P1). Add outlet/product reorder thresholds, low/out-of-stock alerts and actionable procurement recommendations (P2).

Acceptance: Receiving, cancellation and retry are permissioned, idempotent and concurrency-safe; quantities/costs reconcile to existing acquisitions. Threshold changes and recommendation queries are scoped, indexed and tested; no full accounting/ERP is introduced.

### MT-2.10 - Stocktake and cycle-count services

Dependencies: MT-2.9

Scope: Add physical count sessions for quantity and serialized/IMEI stock, expected/count variance, reason codes, approvals, recounts and audited reconciliation (P1).

Acceptance: Concurrent sale/reservation versus counting is reconciled against an explicit count baseline; only approved differences produce stock adjustments. Duplicate counts, stale approvals, rollback and cross-outlet access are tested; history is never fabricated.

### MT-2.11 - Inter-outlet stock transfer services

Dependencies: MT-2.10

Scope: Add authorized dispatch, in-transit custody, receive/reject/partial-receive and transfer history for quantity and serialized stock (P1).

Acceptance: MySQL races prove one owner and one active IMEI claim, no double receipt/dispatch or sale of in-transit units, balanced movements and rollback. Stable origin identifiers and historical sale/acquisition references survive destination custody changes.

### MT-2.12 - Cash sessions and operational expense services

Dependencies: MT-2.11

Scope: Add operator/outlet cash sessions, opening cash, receipts, authorized expenses/payouts, closing counts, expected/actual variance, daily closing and reporting (P1).

Acceptance: Exact cash reconciliation includes sale/return/payment effects with scoped approval, audit, retry and concurrent-close guards. Closed snapshots remain historical; operational cash control is not a general ledger.

### MT-2.13 - Trade-in and buyback services

Dependencies: MT-2.12

Scope: Formalize individual-seller intake with device/IMEI identity, condition/diagnostics, valuation, ownership/source details and purchase or sale-credit treatment (P2).

Acceptance: Authorized valuations and exact purchase/credit amounts link to acquisition and sale snapshots without double credit/intake. Active identifier uniqueness, source privacy, cancellation and transactional rollback pass.

### MT-2.14 - Promotion and coupon services

Dependencies: MT-2.13

Scope: Implement shared fixed/percentage promotion and coupon calculations with validity, minimum/maximum conditions, product/category scope, usage limits, customer/order restrictions and explicit stacking rules (P2).

Acceptance: POS and Website use identical server calculations and immutable discount snapshots. Concurrent usage-limit claims, tampering, retries, expiry, cancellation and return effects pass with audit.

### MT-2.17 - Validated bulk data workflows

Dependencies: MT-2.14

Scope: Provide CSV/XLSX-style catalogue, price, master-data and inventory import/export with scoped fields, preview, row-level errors and explicit operational versus historical-import boundaries (P2).

Acceptance: Permissions, malicious file/formula handling, exact values, row/whole-batch recovery, idempotency and stock/hold invariants pass. Exports exclude unnecessary sensitive fields; imports never write raw stock counters around shared services.

### MT-2.15 - Customer loyalty services

Dependencies: MT-2.17

Scope: Add an optional disableable rewards framework with earning, redemption, expiry, cancellation/return reversal, fraud controls, audit and configuration (P3).

Acceptance: Concurrent earn/redeem/replay and reversal tests prove consistent reward balances. Rewards remain separate from authoritative money; disabled operation preserves history and existing obligations without silently changing sale totals.

### MT-2.16 - Paid repair job services

Dependencies: MT-2.15

Scope: Add optional disableable out-of-warranty repair intake, identifiers, diagnosis, estimate/approval, parts/labor, status, collection, payment linkage and history (P3).

Acceptance: Permission, estimate/amount ownership, stock parts, replay, payment and lifecycle tests pass. Warranty claims remain distinct, disabled new intake does not strand existing jobs, and historical estimates are preserved.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-3 - Administration, content and REST APIs


### MT-3.1 - Reports, documents and communication services

Dependencies: MT-2.16

Scope: Migrate verified reports, dashboards, A4/Thermal output, invoice/warranty documents and safe communication templates. Include new procurement, stocktake/transfer, cash/expense, trade-in, promotion/loyalty and optional repair histories in scoped reporting and retail labels.

Acceptance: Role-scoped totals/exports, history, print/download and protected-template-placeholder parity tests pass; no real messages are sent. Reconciled reports never conflate rewards with money or private supplier/client data with public exports.

### MT-3.2 - Dynamic CMS, media and presentation services

Dependencies: MT-3.1

Scope: Migrate Website-managed pages, navigation, homepage/catalogue, SEO/legal/promotion settings, media, themes and branding controls into the backend. Extend modular content with service landing schemas, managed case studies, client/industry disclosure, digital testimonials separate from retail reviews, reusable FAQs, insights/blog/guides and service associations.

Acceptance: Draft/preview/publish, revisions/rollback, safe uploads/content, cache invalidation and distinct POS/Website settings are preserved. Draft/preview/publish/revisions, consent/moderation, disclosure, metadata and safe media handling cover each added content family; common plus capability/mode variants avoid three page-tree copies.

### MT-3.7 - Website operating mode publication

Dependencies: MT-3.2

Scope: Publish one Laravel-owned Website profile: digital_only, hybrid or commerce_only. Evolve common/digital/commerce modular content with explicit mode copy variants; define route/API/CTA/SEO/sitemap and historical-access capability rules.

Acceptance: Preview/diff validates affected sections/routes; separate publish permission, audit/revisions and one-click rollback pass. Mode switches delete no data; authorized orders/invoices/project-payment history remain accessible. Versioned cache-safe reads and affected-cache revalidation prevent stale capability exposure.

### MT-3.5 - Digital service catalogue and lead services

Dependencies: MT-3.7

Scope: Extend service landing content and low-friction progressive enquiries (P1); add quote/fixed/starting-from/package pricing, add-ons, timezone-safe optional consultation/callback windows and lightweight assigned lead pipeline, notes/follow-ups/history (P2).

Acceptance: Service identity, optional fields, safe reference uploads, spam/rate controls, permissions and authoritative pricing are tested. Mode gates block new inactive leads without removing history. External calendars remain optional/disabled unless configured; no general CRM is built.

### MT-3.6 - Client projects, proposals and milestone services

Dependencies: MT-3.5

Scope: Evolve requests/quotes into owned project lifecycle and approved scope/deliverable proposals with deposit/milestone/final schedules (P1), private client reference/delivery files and privacy-conscious aggregate conversion reporting (P2).

Acceptance: Exact approved amounts, validity/expiry, immutable paid milestones, ownership and payment replay/reconciliation pass. Private files require content/type/size checks, audited immutable references and retention. Mode changes retain authorized portal/payment access; analytics minimize sensitive data.

### MT-3.9 - Customer engagement and notification services

Dependencies: MT-3.6

Scope: Add opt-in back-in-stock/price-drop notifications (P2) and wishlist/save-for-later persistence (P3), using verified product/customer references and Website capabilities.

Acceptance: Consent, preferences, unsubscribe, rate limits, deduplication, unavailable products and guest/account ownership pass. No inactive-mode discovery/delivery leakage; external delivery is disabled by default and tested using safe fakes.

### MT-3.3 - Audit, configuration, backups and integrations

Dependencies: MT-3.9

Scope: Migrate audit/redaction, safe configuration recovery, backup/history/restore guards and valid external-integration contracts.

Acceptance: Secret masking, key-dependent recovery, target-only backup/restore rehearsal and disabled-by-default external jobs are verified; no arbitrary commands/endpoints are introduced.

### MT-3.8 - Guarded data reset services

Dependencies: MT-3.3

Scope: Implement Transactional Data Reset, Business Data Reset and Factory Reset with explicit domain selection and documented preservation/bootstrap matrices across all approved business domains and private objects.

Acceptance: Permission plus recent re-authentication, record/file-count dry-run, typed reset-level confirmation, mandatory automatic verified backup, dependency-safe deletion and integrity checks pass. Stop if backup is impossible. Actor/scope/time/backup/result audit survives reset; private object cleanup matches DB semantics. Never use migrate:fresh, indiscriminate truncation or schema destruction; production execution remains separately authorized.

### MT-3.4 - Versioned REST API and contract acceptance

Dependencies: MT-3.8

Scope: Define and expose public catalogue/content plus authenticated account/cart/order/payment APIs for Next.js. Add documented approved-domain and operating-profile APIs, mode-aware queries/cache keys and indexed pagination; no public page may fetch the full inventory.

Acceptance: Documented payload/error/pagination contracts, authorization, rate limits, public-data allowlists and freshness tests pass; writes use shared services. Direct route/API capability denial, authenticated historical exceptions, selective cache invalidation and all-mode payload/request budgets pass before Website integration.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-4 - React POS and administration


### MT-4.1 - POS shell, authentication and navigation

Dependencies: MT-3.4

Scope: Build the React/TypeScript/Inertia/Tailwind POS shell, role landing pages, authentication and safe navigation.

Acceptance: Desktop/mobile role journeys and direct-route permissions pass in Playwright; menu hiding is not treated as authorization.

### MT-4.2 - POS inventory and transaction interfaces

Dependencies: MT-4.1

Scope: Migrate product/unit/IMEI, acquisition, stock, sale and return workflows onto verified backend services. Add barcode/QR/IMEI scanner-friendly lookup/entry and controlled product/unit retail label generation/printing.

Acceptance: Keyboard/form validation, pagination, conflict handling, totals and critical transaction-completion journeys pass. Scanning and label requests retain validation and permissions; shared promotion/coupon and optional loyalty adjustments are server-calculated and visible in sale/return journeys.

### MT-4.5 - Procurement and stock control interfaces

Dependencies: MT-4.2

Scope: Build supplier/PO/partial receiving, stocktake/variance/recount/approval, transfer dispatch/in-transit/receive/reject, low-stock reorder and validated bulk preview/error/export interfaces over verified services.

Acceptance: Role/outlet, scanner-friendly entry, concurrent conflicts, partial operations, count approvals, recovery and privacy journeys pass in Playwright on supported layouts; the UI cannot bypass stock/procurement validation.

### MT-4.6 - Cash, trade-in and repair interfaces

Dependencies: MT-4.5

Scope: Provide opening/expenses/payout/closing cash journeys, individual-seller valuation/intake/sale-credit, optional paid-repair estimate/approval/collection and linked histories.

Acceptance: Exact money, variance/approval, double-submit, privacy and role controls pass. Disabled repair behavior retains legitimate historical access; warranties remain a separate workflow.

### MT-4.3 - POS customer, warranty and reporting interfaces

Dependencies: MT-4.6

Scope: Migrate required customer/history, invoice, warranty/claim, dashboard, report and export UI.

Acceptance: Role-scoped flows, historical records, Thermal 80mm/A4 preview-download-print and mobile layouts are verified.

### MT-4.4 - Website CMS and platform administration interfaces

Dependencies: MT-4.3

Scope: Migrate protected React admin screens for Website CMS and POS configuration, revisions/media/branding/payment settings. Include three-way Website mode selector, per-mode preview and publish impact, revision rollback, case studies/digital testimonials/knowledge content, promotion/coupon and optional loyalty settings.

Acceptance: Separate permissions, preview/publish/rollback, safe recovery, secret masking and Dynamic Platform parity acceptance pass. Publish permissions remain distinct from editing; publication triggers verified cache/sitemap/SEO revalidation and does not delete historical data.

### MT-4.8 - Digital operations administration interfaces

Dependencies: MT-4.4

Scope: Provide service/package/add-on management, enquiry/lead ownership and follow-ups, booking administration, project lifecycle, proposal/milestone approvals, private file exchange and aggregate conversion reporting.

Acceptance: Role/ownership, safe upload/download, approved monetary snapshots, paid milestone immutability and mode preview/history behavior pass in Playwright; client data is not leaked in internal notes or analytics.

### MT-4.7 - Data reset administration interface

Dependencies: MT-4.8

Scope: Provide three clearly labeled reset levels, domain selection, preservation display, preview counts, recent re-authentication, verified-backup evidence, typed confirmation and outcome/audit reporting.

Acceptance: End-to-end dry-run, denied access, stale preview, failed backup, cancellation, partial failure/recovery and minimum-bootstrap access are verified only on disposable target fixtures; production reset remains HOLD.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-5 - Next.js customer Website


### MT-5.1 - Storefront, catalogue and SEO migration

Dependencies: MT-4.7

Scope: Migrate the Next.js storefront, search/filtering, categories, product pages, variants, availability and SEO onto defined APIs. Apply central mode to home/navigation/footer/CTAs, catalogue/category/compare/cart/checkout discovery, sitemap/indexability, canonical/structured/social metadata and appropriate copy variants. Prefer Server Components, freshness-appropriate SSR/SSG/ISR, route splitting and optimized responsive media.

Acceptance: Public-payload privacy, metadata, responsive layouts, pagination and POS-write-to-Website freshness are verified; zero-stock visibility behavior is preserved. Production-build audits in digital_only, hybrid and commerce_only verify inactive capabilities add no unnecessary JS, API fetches, hydration, rendering, media, preloads or background requests. Target LCP <= 2.5 s, INP <= 200 ms, CLS <= 0.1 and representative mobile Lighthouse 90+; record environment, measurements and justified exceptions.

### MT-5.2 - Customer account, cart, orders and reviews

Dependencies: MT-5.1

Scope: Migrate registration/login/account, multi-line cart, customer order history/access and review eligibility. Add mode-aware wishlist/save-for-later, availability/price opt-in preferences and unsubscribe controls; expose optional loyalty state through owned contracts.

Acceptance: Session boundaries, ownership, guest-to-account behavior, cart recovery and eligible-review Playwright/API journeys pass. Unavailable products, guest-to-account ownership and consent/deduplication pass; inactive marketing does not remove required order/invoice access or add unused client bundles.

### MT-5.3 - Checkout and customer payment flows

Dependencies: MT-5.2

Scope: Connect checkout, COD, pending/retry/cancel, invoice/status and provider-hosted payment boundaries. Use shared promotion/coupon/loyalty calculations and mode-aware creation rules while retaining approved historical payment/status links.

Acceptance: Duplicate submit, price/stock changes, failed payments and verified confirmation pass end-to-end; sandbox claims are made only for authentically configured providers. Measure mode-specific checkout loading with production builds; price/discount/reward tampering and usage-limit races fail safely.

### MT-5.4 - Dynamic public content and digital solutions

Dependencies: MT-5.3

Scope: Migrate managed pages/menu/homepage/themes, promotions, legal content, digital services, service requests and approved project quote/payment flows. Add rich service landing pages, case studies, digital testimonials, FAQs/insights/guides, package/add-on presentation and progressive optional-field enquiry/booking flows. Apply common/digital/commerce content and digital_only/hybrid/commerce_only hero/About/Contact/CTA variants.

Acceptance: CMS publication/revision is reflected on the Website; safe content/media, protected routes and digital quote amount/token/ownership parity pass. Mode-aware discovery/enquiry, SEO/social metadata, safe attachments and low-friction journeys pass. Production-build measurements meet the MT-5.1 targets or record cause, measured impact and explicit remediation/acceptance; minimize global JS, scripts, libraries and fonts.

### MT-5.5 - Client project portal and digital conversion journeys

Dependencies: MT-5.4

Scope: Provide private client request/discussion/proposal/approved/in-progress/review/delivered/completed journeys, secure milestone payment links and reference/delivery exchange. Integrate optional consultation and privacy-conscious conversion events.

Acceptance: Ownership, safe file access, proposal expiry/tampering, replay, paid milestone history and secure historical access in all three Website modes pass. Production-build loading/performance and low-friction enquiries are verified without activating external providers.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-6 - Shared brand and Windows Control


### MT-6.1 - Canonical branding and runtime assets

Dependencies: MT-5.5

Scope: Deduplicate approved assets so the root `brand` directory is the single master; create manifests for runtime copies/derivatives.

Acceptance: Logo/icon/favicon/watermark/font references, build output and fallbacks are verified; no duplicate master Brand Kits remain; source artwork stays unchanged.

### MT-6.2 - Canonical mobiST Control migration

Dependencies: MT-6.1

Scope: Adapt useful legacy Control implementation into one canonical app; apply the current approved logo and do not carry the old logo forward. Manage the new backend/website paths and development processes.

Acceptance: Start, Stop, Restart, Open, Status, Start All and Stop All are verified where applicable; exclusions are justified. PID ownership, already-running/occupied-port handling and duplicate/orphan safety pass; unrelated/source processes are not terminated.

### MT-6.3 - Windows operator and local integration acceptance

Dependencies: MT-6.2

Scope: Rehearse real lifecycle journeys for both apps, queues/cache/storage and Control from a clean Windows setup.

Acceptance: Online/Offline accuracy, repeated Start All/Stop All, partial-start failure, stale PID, browser deduplication, runtime errors and reboot recovery are verified; source environments remain unaffected.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-7 - Migration rehearsal and release readiness


### MT-7.1 - Data migration and rollback rehearsal

Dependencies: MT-6.3

Scope: Perform an isolated MySQL migration dry-run from protected exports/sanitized fixtures; verify recovery design for ID maps, history, media and keys. Rehearse addendum domain mappings and all reset-level backup/preservation/object semantics against approved disposable fixtures.

Acceptance: Record/relation/control-total reconciliation, rerun/idempotency, rollback and verified restore pass. Real export/cutover authorization remains separate; originals are never written. Existing data/history, minimum bootstrap, reset audit and dependent references survive the selected preservation rules; no real reset/export is authorized here.

### MT-7.2 - Security, performance and resilience audit

Dependencies: MT-7.1

Scope: Audit authentication/authorization, uploads/content, secrets, payments, stock concurrency, queues, API/cache performance and recovery. Audit every added domain, capability switching/history exceptions and continuous per-mode performance budgets under production-like builds.

Acceptance: MySQL concurrency, cache outage/stale-data behavior, worker retries, negative paths, dependency audits and focused/full regression pass; verified gaps are remediated. Record representative LCP <= 2.5 s, INP <= 200 ms, CLS <= 0.1 and stable mobile Lighthouse 90+ targets across all modes; do not substitute Lighthouse for interaction evidence. Document cause, measured impact and remediation/acceptance for each justified exception.

### MT-7.3 - Monorepo CI and reproducible build gates

Dependencies: MT-7.2

Scope: Automate backend tests, MySQL/Redis services, TypeScript/frontend checks, builds and Playwright in GitHub Actions. Include new MySQL stock/payment/procurement/reset races, mode matrices and production-build performance regression evidence in CI.

Acceptance: Clean-checkout CI passes on the new private remote; lockfiles/caches/artifacts remain secret-safe and original-repository workflows are untouched. Disabled integrations and public bundle/request budgets remain reproducible and secret-safe.

### MT-7.4 - Linux deployment and backup readiness

Dependencies: MT-7.3

Scope: Create Linux/Nginx/TLS configuration, queue/scheduler, S3, environment-separation, automated backup/restore and rollback runbooks.

Acceptance: Non-production configuration/recovery rehearsal and backup integrity pass. Live provisioning/domain/provider changes remain HOLD until separately authorized.

### MT-7.5 - Full functional parity and acceptance

Dependencies: MT-7.4

Scope: Close every valid requirement in the complete source-to-target register with fresh target evidence and independently run operational journeys. Close the addendum clause traceability as well as original source parity; priorities and optional enablement do not waive implementation gates.

Acceptance: Pest/PHPUnit, MySQL, builds, Playwright, POS/Website/Control, documentation and recovery gates pass; no silent feature loss or unreviewed duplicate master remains. Every approved addendum clause has fresh backend/UI/API/performance evidence or an explicit unresolved gap; expansion is not treated as legacy completed behavior.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-8 - Final sign-off


### FINAL-AUDIT - Independent final project audit

Dependencies: MT-7.5

Scope: Independently audit the complete Goal and Preferences, requirement map, all completed-point claims, Git/code/data, migration/recovery, security, integrations, CI, documentation, HOLD register and source immutability. Include approved requirements addendum v1.1, safe reset, retail/digital expansion, all three Website modes and measured performance exceptions.

Acceptance: Reopen required gaps; the new repository must be clean and remote-aligned. Report `Project complete: 100%` only after a clean final audit; Deferred required work cannot substitute for completion. Verify original eight completed points were preserved and every subsequently required extension was independently accepted.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## HOLD / Deferred register

H-01 - Live production deployment, domains, TLS activation and real customer-data cutover require separate explicit authorization and target access. MT-7.1/MT-7.4 isolated rehearsal/readiness remains in scope.

H-02 - JazzCash/Easypaisa authentic sandbox acceptance requires verified official contracts and valid sandbox credentials. Card requires an approved hosted/tokenized processor. Live activation is a separate HOLD. Existing safe disabled-provider behavior and COD migration remain required; fake acceptance is prohibited.

H-03 - Source business-data export, destructive restore and production reset require authorization for the sensitive dataset and exact target before access/use. Synthetic/sanitized isolated migration tests may continue. Source repository/database writes are prohibited in this project.

H-04 - Addendum v1.1 is approved scope, not a HOLD. Arbitrary new top-level category semantics and unrelated features beyond Goal/Preferences/addendum remain outside scope. Marketplace, consumer lending, general subscription billing, arbitrary multi-currency accounting, full ERP/general ledger and unnecessary microservices remain excluded unless separately approved. Existing protected IDs/business rules must be preserved.

HOLD/dependency evidence is explicitly reviewed in FINAL-AUDIT. A required capability cannot be left unimplemented and then relabeled Deferred merely to complete the project.
