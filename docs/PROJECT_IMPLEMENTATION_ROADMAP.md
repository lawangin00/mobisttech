# mobiST Tech - Project Implementation Roadmap

Version: 1.5 | Date: 2026-08-31

Canonical Goal: docs/PROJECT_GOAL.md

Binding Preferences: docs/PROJECT_PREFERENCES.md

Source of Truth: docs/PROJECT_SOURCE_OF_TRUTH.md

Status ledger: docs/PROJECT_IMPLEMENTATION_STATUS.md

Word mirror: docs/PROJECT_IMPLEMENTATION_ROADMAP.docx

## Scope and execution rules

This is the single active structural roadmap for the controlled migration. Live Completed/In Progress/Pending state, last/current/next position and progress counts are recorded only in `docs/PROJECT_IMPLEMENTATION_STATUS.md`. Each Proceed executes one point through its required gates and intended new-repository commit/push, then stops. Dependencies are ordered; point IDs and exact titles remain stable.

The original POS/Website folders, remotes and data are immutable references. All implementation, copied-source tests and migration rehearsals must run under `C:\mobisttech` or isolated approved target resources. Do not run tests/builds against the originals. Do not replace the single shared Laravel/MySQL authority with duplicate authoritative databases.

Every point includes applicable focused/full tests, parity evidence, secrets review, source-boundary checks and ledger updates. Routine execution progress updates the ledger only and must not edit this roadmap or regenerate its DOCX. Regenerate/verify the DOCX only when roadmap structure/content materially changes. MT-1.1 detailed inventory may refine this roadmap from evidence, but valid functionality must not be silently dropped and completed IDs must not be renumbered.

Both Goal and Preferences apply. For working features, assess reuse/adapt/refactor/migrate before rewrite; rewrite requires a verified reason. Verify parity, data consistency, integration and regression incrementally rather than using a big-bang rewrite. Prefer conventional solutions, justified Redis roles and a Laravel-only business backend; do not introduce a parallel Node/Express business backend. Record stack deviations only for verified unavoidable blockers and never silently resolve a Goal conflict.

Roman Urdu is limited to assistant chat/UI communication. Git-tracked project documentation and technical artifacts use standard English unless the user explicitly requests another language for a specific artifact. User-supplied Goal/Preferences remain byte-preserved in their original language/content.

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

### MT-2.5 - Sales, invoices and returns migration

Dependencies: MT-2.4

Scope: Migrate sales, invoice totals/discounts, operational customer records and verified return behavior.

Acceptance: Money precision, identifiers, historical snapshots, return adjustments and stock/accounting effects match legacy acceptance. MT-1.1 found stock-return behavior but no full routed sale-refund flow; Goal-required sale returns need an explicit verified contract and fresh tests.

### MT-2.6 - Warranty and claim migration

Dependencies: MT-2.5

Scope: Preserve warranty duration, versioned clauses, claim lifecycle and historical document relationships.

Acceptance: Sale-time snapshots, permissions, expiry/boundary conditions and historical warranty/claim output parity pass.

### MT-2.7 - Unified orders, reservations and payments

Dependencies: MT-2.6

Scope: Migrate Website order/payment/COD/project-payment services into shared transactions and prove replacement of obsolete cross-database synchronization.

Acceptance: Price/amount/ownership verification, retry/replay, expiration, confirmation-versus-release, reconciliation and payment-failure recovery tests pass. Disabled providers remain safe.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-3 - Administration, content and REST APIs


### MT-3.1 - Reports, documents and communication services

Dependencies: MT-2.7

Scope: Migrate verified reports, dashboards, A4/Thermal output, invoice/warranty documents and safe communication templates.

Acceptance: Role-scoped totals/exports, history, print/download and protected-template-placeholder parity tests pass; no real messages are sent.

### MT-3.2 - Dynamic CMS, media and presentation services

Dependencies: MT-3.1

Scope: Migrate Website-managed pages, navigation, homepage/catalogue, SEO/legal/promotion settings, media, themes and branding controls into the backend.

Acceptance: Draft/preview/publish, revisions/rollback, safe uploads/content, cache invalidation and distinct POS/Website settings are preserved.

### MT-3.3 - Audit, configuration, backups and integrations

Dependencies: MT-3.2

Scope: Migrate audit/redaction, safe configuration recovery, backup/history/restore guards and valid external-integration contracts.

Acceptance: Secret masking, key-dependent recovery, target-only backup/restore rehearsal and disabled-by-default external jobs are verified; no arbitrary commands/endpoints are introduced.

### MT-3.4 - Versioned REST API and contract acceptance

Dependencies: MT-3.3

Scope: Define and expose public catalogue/content plus authenticated account/cart/order/payment APIs for Next.js.

Acceptance: Documented payload/error/pagination contracts, authorization, rate limits, public-data allowlists and freshness tests pass; writes use shared services.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-4 - React POS and administration


### MT-4.1 - POS shell, authentication and navigation

Dependencies: MT-3.4

Scope: Build the React/TypeScript/Inertia/Tailwind POS shell, role landing pages, authentication and safe navigation.

Acceptance: Desktop/mobile role journeys and direct-route permissions pass in Playwright; menu hiding is not treated as authorization.

### MT-4.2 - POS inventory and transaction interfaces

Dependencies: MT-4.1

Scope: Migrate product/unit/IMEI, acquisition, stock, sale and return workflows onto verified backend services.

Acceptance: Keyboard/form validation, pagination, conflict handling, totals and critical transaction-completion journeys pass.

### MT-4.3 - POS customer, warranty and reporting interfaces

Dependencies: MT-4.2

Scope: Migrate required customer/history, invoice, warranty/claim, dashboard, report and export UI.

Acceptance: Role-scoped flows, historical records, Thermal 80mm/A4 preview-download-print and mobile layouts are verified.

### MT-4.4 - Website CMS and platform administration interfaces

Dependencies: MT-4.3

Scope: Migrate protected React admin screens for Website CMS and POS configuration, revisions/media/branding/payment settings.

Acceptance: Separate permissions, preview/publish/rollback, safe recovery, secret masking and Dynamic Platform parity acceptance pass.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-5 - Next.js customer Website


### MT-5.1 - Storefront, catalogue and SEO migration

Dependencies: MT-4.4

Scope: Migrate the Next.js storefront, search/filtering, categories, product pages, variants, availability and SEO onto defined APIs.

Acceptance: Public-payload privacy, metadata, responsive layouts, pagination and POS-write-to-Website freshness are verified; zero-stock visibility behavior is preserved.

### MT-5.2 - Customer account, cart, orders and reviews

Dependencies: MT-5.1

Scope: Migrate registration/login/account, multi-line cart, customer order history/access and review eligibility.

Acceptance: Session boundaries, ownership, guest-to-account behavior, cart recovery and eligible-review Playwright/API journeys pass.

### MT-5.3 - Checkout and customer payment flows

Dependencies: MT-5.2

Scope: Connect checkout, COD, pending/retry/cancel, invoice/status and provider-hosted payment boundaries.

Acceptance: Duplicate submit, price/stock changes, failed payments and verified confirmation pass end-to-end; sandbox claims are made only for authentically configured providers.

### MT-5.4 - Dynamic public content and digital solutions

Dependencies: MT-5.3

Scope: Migrate managed pages/menu/homepage/themes, promotions, legal content, digital services, service requests and approved project quote/payment flows.

Acceptance: CMS publication/revision is reflected on the Website; safe content/media, protected routes and digital quote amount/token/ownership parity pass.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-6 - Shared brand and Windows Control


### MT-6.1 - Canonical branding and runtime assets

Dependencies: MT-5.4

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

Scope: Perform an isolated MySQL migration dry-run from protected exports/sanitized fixtures; verify recovery design for ID maps, history, media and keys.

Acceptance: Record/relation/control-total reconciliation, rerun/idempotency, rollback and verified restore pass. Real export/cutover authorization remains separate; originals are never written.

### MT-7.2 - Security, performance and resilience audit

Dependencies: MT-7.1

Scope: Audit authentication/authorization, uploads/content, secrets, payments, stock concurrency, queues, API/cache performance and recovery.

Acceptance: MySQL concurrency, cache outage/stale-data behavior, worker retries, negative paths, dependency audits and focused/full regression pass; verified gaps are remediated.

### MT-7.3 - Monorepo CI and reproducible build gates

Dependencies: MT-7.2

Scope: Automate backend tests, MySQL/Redis services, TypeScript/frontend checks, builds and Playwright in GitHub Actions.

Acceptance: Clean-checkout CI passes on the new private remote; lockfiles/caches/artifacts remain secret-safe and original-repository workflows are untouched.

### MT-7.4 - Linux deployment and backup readiness

Dependencies: MT-7.3

Scope: Create Linux/Nginx/TLS configuration, queue/scheduler, S3, environment-separation, automated backup/restore and rollback runbooks.

Acceptance: Non-production configuration/recovery rehearsal and backup integrity pass. Live provisioning/domain/provider changes remain HOLD until separately authorized.

### MT-7.5 - Full functional parity and acceptance

Dependencies: MT-7.4

Scope: Close every valid requirement in the complete source-to-target register with fresh target evidence and independently run operational journeys.

Acceptance: Pest/PHPUnit, MySQL, builds, Playwright, POS/Website/Control, documentation and recovery gates pass; no silent feature loss or unreviewed duplicate master remains.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## MT-8 - Final sign-off


### FINAL-AUDIT - Independent final project audit

Dependencies: MT-7.5

Scope: Independently audit the complete Goal and Preferences, requirement map, all completed-point claims, Git/code/data, migration/recovery, security, integrations, CI, documentation, HOLD register and source immutability.

Acceptance: Reopen required gaps; the new repository must be clean and remote-aligned. Report `Project complete: 100%` only after a clean final audit; Deferred required work cannot substitute for completion.

Stage exit: Verify all acceptance gates and Git-backed evidence for every point in this stage; never mark partial work Complete.

## HOLD / Deferred register

H-01 - Live production deployment, domains, TLS activation and real customer-data cutover require separate explicit authorization and target access. MT-7.1/MT-7.4 isolated rehearsal/readiness remains in scope.

H-02 - JazzCash/Easypaisa authentic sandbox acceptance requires verified official contracts and valid sandbox credentials. Card requires an approved hosted/tokenized processor. Live activation is a separate HOLD. Existing safe disabled-provider behavior and COD migration remain required; fake acceptance is prohibited.

H-03 - Source business-data export and destructive restore require authorization for the sensitive dataset and exact target before access/use. Synthetic/sanitized isolated migration tests may continue. Source repository/database writes are prohibited in this project.

H-04 - Arbitrary new top-level category semantics and unrelated new features are outside the approved Goal unless separately scoped. Existing protected IDs/business rules must be preserved.

HOLD/dependency evidence is explicitly reviewed in FINAL-AUDIT. A required capability cannot be left unimplemented and then relabeled Deferred merely to complete the project.
