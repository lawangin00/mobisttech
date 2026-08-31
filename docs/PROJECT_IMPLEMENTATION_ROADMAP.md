# mobiST Tech - Project Implementation Roadmap

Version: 1.2 | Date: 2026-08-31

Canonical Goal: docs/PROJECT_GOAL.md

Binding Preferences: docs/PROJECT_PREFERENCES.md

Source of Truth: docs/PROJECT_SOURCE_OF_TRUTH.md

Status ledger: docs/PROJECT_IMPLEMENTATION_STATUS.md

Word mirror: docs/PROJECT_IMPLEMENTATION_ROADMAP.docx

## Scope aur execution rules

Yeh controlled migration ka single active roadmap hai. MT-0.1 initialization aur MT-1.1 source inventory complete hain (2/32); application migration pending hai. Har Proceed aik point ko required gates aur intended new-repository commit/push tak complete karega, phir rukega. Dependencies ordered hain; IDs aur exact titles stable rahenge.

Purane POS/Website folders, remotes aur data immutable references hain. Sab implementation, copied-source tests aur migration rehearsals C:\mobisttech ya isolated approved target resources mein honge. Originals par tests/builds bhi nahi. Single shared Laravel/MySQL authority ko duplicate authoritative databases se replace nahi karna.

Har point mein applicable focused/full tests, parity evidence, secrets review, source-boundary check, ledger update aur roadmap/DOCX parity completion gates hain. MT-1.1 detailed inventory is roadmap ko evidence ke mutabiq refine kar sakti hai; silently functionality drop ya completed IDs renumber nahi karna.

Goal aur Preferences dono apply hon. Working features ke liye reuse/adapt/refactor/migrate pehle assess hon; rewrite ko verified reason chahiye. Incremental batches mein parity, data consistency, integration aur regression verify hon; big-bang rewrite nahi. Conventional solutions, justified Redis roles aur Laravel-only business backend use hon; Node/Express parallel backend nahi. Stack deviation sirf verified unavoidable blocker par documented ho, Goal conflict silently resolve na ho.

## MT-0 - Project initialization

Stage status: Complete

### MT-0.1 - Project initialization

Status: Completed | Dependencies: None

Scope: Original Goal aur approved Preferences preserve/reconcile karna; protected source baseline, monorepo layout, canonical docs/registry, matching DOCX aur independent private GitHub repository establish karna.

Acceptance: Goal/Preferences hashes aur full coverage, docs parity, source unchanged checks, independent Git history aur remote equality verify hon. MT-0.1 re-verification evidence record ho; MT-1.1/application migration start na ho.

Stage exit: Is stage ke tamam points ke acceptance gates aur Git-backed evidence verified hon; partial work Complete mark na ho.

## MT-1 - Migration inventory and design

Stage status: In Progress

### MT-1.1 - Source inventory and feature parity register

Status: Completed | Dependencies: MT-0.1

Scope: Pinned source code, routes, roles, models, migrations, tests, integrations, Dynamic Platform, branding aur Control inventory; legacy files/folders ke roles/dependencies aur retention value assess karein.

Acceptance: Har valid capability ke reuse/adapt/refactor/migrate options aur required rewrite ka verified reason record ho; retirement sirf unnecessary duplication ke liye. Target owner, parity gate aur isolated characterization hon; originals unchanged hon.

Evidence: docs/migration/FEATURE_PARITY_REGISTER.md; isolated source tests passed, originals unchanged, target parity pending.

### MT-1.2 - Unified data, API and security design

Status: Pending | Dependencies: MT-1.1

Scope: Single MySQL schema, identity/record collision maps, customer merge rules, history, transaction boundaries, API versioning aur authorization design karein.

Acceptance: Orders, payments, stock reservation, price/currency precision, idempotency, cache invalidation, rollback aur encrypted-data recovery contracts documented hon; two-way database sync target na ho.

### MT-1.3 - Windows toolchain and application foundations

Status: Pending | Dependencies: MT-1.2

Scope: Approved pinned stack par Laravel 13, React/Inertia/TypeScript/Tailwind aur Next.js foundations; isolated MySQL aur concrete justified Redis/storage roles configure karein. Useful source foundations reuse/adapt hon.

Acceptance: Fresh Windows setup, secret-free examples, lockfiles aur smoke builds pass hon. Ports source servers se conflict na karein; nested .git, real provider calls ya source data connection na ho.

Stage exit: Is stage ke tamam points ke acceptance gates aur Git-backed evidence verified hon; partial work Complete mark na ho.

## MT-2 - Shared backend and transactional migration

Stage status: Pending

### MT-2.1 - Shared backend schema and infrastructure

Status: Pending | Dependencies: MT-1.3

Scope: Reusable Laravel core, migration schema, jobs, MySQL ownership, Redis roles aur S3-compatible storage layer migrate karein.

Acceptance: Disposable MySQL apply/rollback/reapply, storage isolation aur queue/cache failure behavior pass ho; Website ko DB credentials na milein.

### MT-2.2 - Identity, customer and authorization migration

Status: Pending | Dependencies: MT-2.1

Scope: POS roles/guards aur Website customer identity ko shared backend mein map karein; session/password/account recovery aur ownership preserve karein.

Acceptance: Role matrix, user/customer collision cases, session isolation, CSRF, IDOR aur recovery tests pass hon; unauthorized account merging na ho.

### MT-2.3 - Product and master-data migration

Status: Pending | Dependencies: MT-2.2

Scope: Products, variants/units, categories, attributes aur stable master data migrate karein; public/private field separation rakhein.

Acceptance: ID/relationship maps, historical references aur validation parity pass ho; protected category/identifier semantics silently change na hon.

### MT-2.4 - Inventory and stock integrity migration

Status: Pending | Dependencies: MT-2.3

Scope: Acquisition, stock units/IMEIs, availability, movements aur transaction-derived stock rules shared services mein migrate karein.

Acceptance: Concurrent sale/reservation, uniqueness, rollback aur negative/duplicate stock prevention MySQL par pass hon; snapshots reconcile hon.

### MT-2.5 - Sales, invoices and returns migration

Status: Pending | Dependencies: MT-2.4

Scope: Sales, invoice totals/discounts, operational customer records aur returns ke verified behavior migrate karein.

Acceptance: Money precision, identifiers, historical snapshots, return adjustments aur stock/accounting effects legacy acceptance se match hon. MT-1.1 mein stock return mila, full sale-refund route nahi; Goal-required sale returns ka explicit verified contract aur fresh tests required hain.

### MT-2.6 - Warranty and claim migration

Status: Pending | Dependencies: MT-2.5

Scope: Warranty duration, versioned clauses, claim lifecycle aur historical document relationships preserve karein.

Acceptance: Sale-time snapshots, permissions, expiry/boundary conditions aur historical warranty/claim output parity pass ho.

### MT-2.7 - Unified orders, reservations and payments

Status: Pending | Dependencies: MT-2.6

Scope: Website order/payment/COD/project-payment services ko shared transactions mein migrate karein; obsolete cross-DB sync ki replacement prove karein.

Acceptance: Price/amount/ownership verification, retry/replay, expiration, confirmation-versus-release, reconciliation aur payment failure recovery tests pass hon. Disabled providers safe rahen.

Stage exit: Is stage ke tamam points ke acceptance gates aur Git-backed evidence verified hon; partial work Complete mark na ho.

## MT-3 - Administration, content and REST APIs

Stage status: Pending

### MT-3.1 - Reports, documents and communication services

Status: Pending | Dependencies: MT-2.7

Scope: Verified reports, dashboards, A4/Thermal output, invoice/warranty documents aur safe communication templates migrate karein.

Acceptance: Role-scoped totals/exports, history, print/download aur protected template placeholders parity tests pass hon; real messages send na hon.

### MT-3.2 - Dynamic CMS, media and presentation services

Status: Pending | Dependencies: MT-3.1

Scope: Website managed pages, navigation, homepage/catalogue, SEO/legal/promotion settings, media, themes aur branding controls backend mein migrate karein.

Acceptance: Draft/preview/publish, revisions/rollback, safe uploads/content, cache invalidation aur distinct POS/Website settings preserve hon.

### MT-3.3 - Audit, configuration, backups and integrations

Status: Pending | Dependencies: MT-3.2

Scope: Audit/redaction, safe configuration recovery, backup/history/restore guards aur valid external integration contracts migrate karein.

Acceptance: Secret masking, key-dependent recovery, target-only backup/restore rehearsal aur disabled-by-default external jobs verify hon; no arbitrary commands/endpoints.

### MT-3.4 - Versioned REST API and contract acceptance

Status: Pending | Dependencies: MT-3.3

Scope: Next.js ke liye public catalogue/content aur authenticated account/cart/order/payment APIs define aur expose karein.

Acceptance: Documented payload/error/pagination contracts, authorization, rate limits, public data allowlists aur freshness tests pass hon; writes shared services use karein.

Stage exit: Is stage ke tamam points ke acceptance gates aur Git-backed evidence verified hon; partial work Complete mark na ho.

## MT-4 - React POS and administration

Stage status: Pending

### MT-4.1 - POS shell, authentication and navigation

Status: Pending | Dependencies: MT-3.4

Scope: React/TypeScript/Inertia/Tailwind POS shell, role landing pages, authentication aur safe navigation build karein.

Acceptance: Desktop/mobile role journeys aur direct-route permissions Playwright mein pass hon; menu hiding ko authorization na samjhein.

### MT-4.2 - POS inventory and transaction interfaces

Status: Pending | Dependencies: MT-4.1

Scope: Products/units/IMEIs, acquisition, stock, sale aur return workflows verified backend services par migrate karein.

Acceptance: Keyboard/form validation, pagination, conflict handling, totals aur transaction completion critical journeys pass hon.

### MT-4.3 - POS customer, warranty and reporting interfaces

Status: Pending | Dependencies: MT-4.2

Scope: Customer/history, invoices, warranties/claims, dashboards, reports aur exports ki existing required UI migrate karein.

Acceptance: Role-scoped flows, historical records, Thermal 80mm/A4 preview-download-print aur mobile layouts verify hon.

### MT-4.4 - Website CMS and platform administration interfaces

Status: Pending | Dependencies: MT-4.3

Scope: Website CMS aur POS configuration ke protected React admin screens, revisions/media/branding/payment settings migrate karein.

Acceptance: Separate permissions, preview/publish/rollback, safe recovery, secret masking aur Dynamic Platform parity acceptance pass ho.

Stage exit: Is stage ke tamam points ke acceptance gates aur Git-backed evidence verified hon; partial work Complete mark na ho.

## MT-5 - Next.js customer Website

Stage status: Pending

### MT-5.1 - Storefront, catalogue and SEO migration

Status: Pending | Dependencies: MT-4.4

Scope: Next.js storefront, search/filtering, categories, product pages, variants, availability aur SEO defined APIs par migrate karein.

Acceptance: Public payload privacy, metadata, responsive layouts, pagination aur POS-write-to-Website freshness verify ho; zero-stock visibility behavior preserve ho.

### MT-5.2 - Customer account, cart, orders and reviews

Status: Pending | Dependencies: MT-5.1

Scope: Registration/login/account, multi-line cart, customer order history/access aur review eligibility migrate karein.

Acceptance: Session boundaries, ownership, guest-to-account behavior, cart recovery aur eligible-review Playwright/API journeys pass hon.

### MT-5.3 - Checkout and customer payment flows

Status: Pending | Dependencies: MT-5.2

Scope: Checkout, COD, pending/retry/cancel, invoices/status aur provider-hosted payment boundaries connect karein.

Acceptance: Duplicate submit, price/stock changes, failed payments aur verified confirmation end-to-end pass hon; sandbox claims sirf authentic configured providers ke liye hon.

### MT-5.4 - Dynamic public content and digital solutions

Status: Pending | Dependencies: MT-5.3

Scope: Managed pages/menu/homepage/themes, promotions, legal content, digital services, service requests aur approved project quote/payment flows migrate karein.

Acceptance: CMS publication/revision website par reflect ho; safe content/media, protected routes aur digital quote amount/token/ownership parity pass ho.

Stage exit: Is stage ke tamam points ke acceptance gates aur Git-backed evidence verified hon; partial work Complete mark na ho.

## MT-6 - Shared brand and Windows Control

Stage status: Pending

### MT-6.1 - Canonical branding and runtime assets

Status: Pending | Dependencies: MT-5.4

Scope: Approved assets deduplicate karke root brand ko single master banayein; runtime copies/derivatives ke manifests banayein.

Acceptance: Logo/icon/favicon/watermark/font references, build output aur fallbacks verify hon; duplicate Brand Kit masters na hon; source artwork unchanged rahe.

### MT-6.2 - Canonical mobiST Control migration

Status: Pending | Dependencies: MT-6.1

Scope: Useful legacy Control implementation aik canonical app mein adapt karein; current approved logo lagayein, old logo carry forward na ho. New backend/website paths aur development processes manage hon.

Acceptance: Start, Stop, Restart, Open, Status, Start All aur Stop All jahan applicable hon verify hon; exclusions justified hon. PID ownership, already-running/occupied-port handling aur duplicate/orphan safety pass ho; unrelated/source processes terminate na hon.

### MT-6.3 - Windows operator and local integration acceptance

Status: Pending | Dependencies: MT-6.2

Scope: Clean Windows setup se both apps, queues/cache/storage aur Control ke real lifecycle journeys rehearse karein.

Acceptance: Online/Offline accuracy, repeated Start All/Stop All, partial-start failure, stale PID, browser deduplication, runtime errors aur reboot recovery verify hon; source environments unaffected hon.

Stage exit: Is stage ke tamam points ke acceptance gates aur Git-backed evidence verified hon; partial work Complete mark na ho.

## MT-7 - Migration rehearsal and release readiness

Stage status: Pending

### MT-7.1 - Data migration and rollback rehearsal

Status: Pending | Dependencies: MT-6.3

Scope: Protected exports/sanitized fixtures se isolated MySQL migration dry-run karein; ID maps, history, media aur keys ka recovery design verify karein.

Acceptance: Record/relation/control-total reconciliation, rerun/idempotency, rollback aur verified restore pass hon. Real export/cutover authorization alag rahe; originals par writes na hon.

### MT-7.2 - Security, performance and resilience audit

Status: Pending | Dependencies: MT-7.1

Scope: Authentication/authorization, upload/content, secrets, payments, stock concurrency, queues, API/cache performance aur recovery audit karein.

Acceptance: MySQL concurrency, cache outages/stale data, worker retries, negative paths, dependency audits aur focused/full regression pass hon; verified gaps remediate hon.

### MT-7.3 - Monorepo CI and reproducible build gates

Status: Pending | Dependencies: MT-7.2

Scope: GitHub Actions mein backend tests, MySQL/Redis services, TypeScript/frontend checks, builds aur Playwright automate karein.

Acceptance: New private remote par clean checkout CI pass ho; lockfiles/caches/artifacts secret-safe hon aur original repos workflows untouched hon.

### MT-7.4 - Linux deployment and backup readiness

Status: Pending | Dependencies: MT-7.3

Scope: Linux/Nginx/TLS configuration, queues/scheduler, S3, environment separation, automated backup/restore aur rollback runbooks banayein.

Acceptance: Non-production configuration/recovery rehearsal aur backup integrity pass ho. Live provisioning/domain/provider changes tab tak HOLD rahen jab tak separately authorized na hon.

### MT-7.5 - Full functional parity and acceptance

Status: Pending | Dependencies: MT-7.4

Scope: Complete source-to-target register ka har valid requirement fresh target evidence se close karein; operational journeys independently run karein.

Acceptance: Pest/PHPUnit, MySQL, builds, Playwright, POS/Website/Control, docs and recovery gates pass hon; no silent feature loss or unreviewed duplicate master ho.

Stage exit: Is stage ke tamam points ke acceptance gates aur Git-backed evidence verified hon; partial work Complete mark na ho.

## MT-8 - Final sign-off

Stage status: Pending

### FINAL-AUDIT - Independent final project audit

Status: Pending | Dependencies: MT-7.5

Scope: Complete Goal aur Preferences, requirements map, all completed-point claims, Git/code/data, migration/recovery, security, integrations, CI, docs, HOLD register aur source immutability independently audit karein.

Acceptance: Required gaps reopen hon; new repo clean aur remote-aligned ho. Sirf clean final audit par Project complete: 100% report ho; Deferred required work ko completion ka substitute na banayein.

Stage exit: Is stage ke tamam points ke acceptance gates aur Git-backed evidence verified hon; partial work Complete mark na ho.

## HOLD / Deferred register

H-01 - Live production deployment, domains, TLS activation aur real customer-data cutover: separate explicit authorization aur target access required. MT-7.1/MT-7.4 ki isolated rehearsal/readiness in scope rahegi.

H-02 - JazzCash/Easypaisa authentic sandbox acceptance: verified official contracts aur valid sandbox credentials required. Card: approved hosted/tokenized processor required. Live activation alag HOLD hai. Existing safe disabled-provider behavior aur COD migration required hain; fake acceptance mana hai.

H-03 - Source business-data export aur destructive restore: sensitive datasets aur exact target ka authorization before access/use. Synthetic/sanitized isolated migration tests continue ho sakte hain. Source repository/database writes is project mein prohibited hain.

H-04 - Arbitrary new top-level category semantics aur unrelated new features: approved Goal se bahar jab tak separately scoped na hon. Existing protected IDs/business rules preserve honge.

HOLD/dependency evidence final audit mein explicitly review hogi. Koi required capability implement na hone par use sirf Deferred label de kar project complete nahi kehna.
