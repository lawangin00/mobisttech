# Mobisttech - Approved Requirements Addendum

**Version:** 1.1  
**Date:** 2026-09-01  
**Project:** Mobisttech  
**Repository:** `C:\mobisttech`  

## 1. Authority and purpose

This document is an approved scope expansion for the existing Mobisttech project. It supplements the canonical `docs/PROJECT_GOAL.md` and `docs/PROJECT_PREFERENCES.md`; it does not replace, rewrite, translate, or invalidate either original file.

The project is already initialized. This addendum must therefore be reconciled into the existing Source of Truth, structural roadmap, implementation ledger, project registry pointers, and requirement traceability without running project initialization again.

All already verified completed work remains completed unless a newly approved requirement exposes a real dependency gap that must be remediated. Existing completed roadmap IDs/titles must not be renumbered or silently reopened. New work must be inserted at the correct dependency point using stable new IDs or clearly justified additions to still-pending points.

The roadmap/DOCX lifecycle policy remains binding: perform one structural roadmap update for this approved expansion, regenerate and QA the Word mirror once for that structural change, and thereafter update the roadmap/DOCX only when the plan itself changes materially. Routine execution progress remains ledger-only.

## 2. Execution strategy

Adopt these requirements now so data models, APIs, permissions, CMS, POS interfaces, Website behavior, tests, and release gates are designed correctly the first time. Do not implement all additions immediately as an out-of-sequence feature batch.

During the addendum-reconciliation checkpoint:

- update project-control documentation only;
- preserve the current verified implementation state and completed checkpoints;
- determine the correct dependency order from actual code/schema state;
- place each requirement into the most appropriate existing stage/point or create a new stable point where bundling would be unsafe;
- regenerate/verify the roadmap DOCX once because the roadmap is materially changing;
- commit and push the documentation-only reconciliation;
- stop before starting the newly resolved next implementation point.

After reconciliation, continue normal one-point-at-a-time execution using the project command system. Do not assume that the next point remains the same if a newly approved foundational dependency must be inserted before it.

## 3. Safe data reset and cleanup

Provide an explicitly guarded data-management facility with three reset levels.

### 3.1 Transactional Data Reset

Clear transactional/user-entered operational history such as sales, invoices, returns/refunds, warranties/claims, Website orders, carts, payment transaction records where legally/operationally safe, service requests, quotes/project transactions where selected, related transactional documents, and other dependent transactional records.

Preserve products/inventory, identities, permissions, system configuration, branding, master data, and infrastructure settings unless a dependency-safe rule requires otherwise.

### 3.2 Business Data Reset

Clear Transactional Data plus operational business records such as products, product units/variants, inventory, stock/acquisition records, customers where selected, supplier/procurement records, and related business content that is explicitly part of the reset scope.

Preserve the minimum system bootstrap, authorized admin/superadmin/outlet identities, permissions, configuration, branding, and required master/system data.

### 3.3 Factory Reset

Return the application to a documented clean operational baseline while preserving only the minimum bootstrap required to access and configure the system again.

### 3.4 Reset safety requirements

Every destructive reset must include:

- explicit permission and recent re-authentication;
- a preview/dry-run showing affected record/file counts by domain;
- dependency-safe deletion order and integrity checks;
- automatic verified backup before destructive execution unless explicitly impossible, in which case execution must stop;
- typed destructive confirmation and an unambiguous reset-level label;
- audit trail identifying actor, scope, time, backup reference, and result;
- private-file/object cleanup rules that match database deletion semantics;
- preservation rules clearly shown before execution;
- production/cutover execution remaining separately authorized under existing HOLD controls.

Do not use `migrate:fresh`, raw indiscriminate truncation, or schema destruction as the normal business-data reset mechanism.

## 4. Retail/POS operational additions

### 4.1 Physical stocktake and cycle counting - P1

Add stocktake/cycle-count workflows for physical verification of quantity and serialized/IMEI inventory. Support count sessions, expected-versus-counted variance, reason codes, approvals where required, auditable adjustments, recounts, and reconciliation without silently fabricating stock history.

### 4.2 Inter-outlet stock transfer - P1

Support transfer of serialized devices and quantity stock between authorized outlets with dispatch, in-transit, receive/reject/partial-receive handling, stock ownership integrity, IMEI uniqueness, role controls, audit history, and concurrency-safe rollback.

### 4.3 Supplier/vendor and purchase-order management - P1

Extend existing acquisition-source capture into formal supplier/vendor profiles and procurement workflows: supplier contacts, purchase orders, line items, status, expected dates, receiving, partial receiving, landed/unit cost where applicable, supplier history, and traceability from procurement to stock acquisition.

### 4.4 Cash drawer, daily closing, and expenses - P1

Add POS cash-session controls including opening cash, cash receipts, authorized expenses/payouts, closing count, expected-versus-actual variance, reconciliation, operator/outlet ownership, daily closing, audit history, and reporting. This is operational cash control, not a full accounting/ERP replacement.

### 4.5 Barcode/QR/IMEI scanning and label printing - P1

Support scanner-friendly product/unit lookup and transaction entry, including IMEI/serial scanning where applicable. Provide controlled label generation/printing for product/unit identifiers and relevant retail labels. Scanning must accelerate workflows without bypassing validation or authorization.

### 4.6 Low-stock and reorder management - P2

Add configurable reorder thresholds at an appropriate product/outlet level, low/out-of-stock alerts, reorder recommendations, and links into supplier/procurement workflows. Preserve the existing reporting concept while adding actionable threshold management.

### 4.7 Trade-in and buyback - P2

Formalize customer/individual-seller acquisition into a trade-in/buyback workflow with device identity, IMEI, condition/diagnostics, valuation, ownership/source details, purchase or sale-credit handling where applicable, stock intake, audit trail, and protection against duplicate active identifiers.

### 4.8 Promotion and coupon engine - P2

Provide controlled fixed/percentage promotions and coupon codes with validity windows, minimum/maximum conditions, product/category applicability, usage limits, customer/order restrictions where needed, stacking rules, auditability, and consistent POS/Website calculation semantics.

### 4.9 Bulk product/inventory import and export - P2

Support validated CSV/XLSX-style bulk workflows for appropriate catalogue, price, master-data, and inventory operations. Require preview, row-level validation/errors, permission checks, idempotency/replay safety where applicable, and rollback/recovery for failed imports. Do not expose sensitive fields unnecessarily in exports.

### 4.10 Back-in-stock and price-drop notifications - P2

Allow customers to opt in to product availability and/or price-change notifications. Use verified product/customer references, consent/preferences, rate limiting, deduplication, unsubscribe controls, and disabled-by-default external delivery until configured.

### 4.11 Wishlist / Save for Later - P3

Add customer wishlist/save-for-later capability with ownership isolation, guest-to-account behavior where appropriate, unavailable-product handling, and Website-mode awareness.

### 4.12 Customer loyalty / rewards - P3

Add an optional loyalty/reward framework with explicit earning, redemption, expiry, cancellation/return reversal, fraud/abuse controls, audit history, and configuration. Keep it disableable and separate from authoritative money/accounting records.

### 4.13 Out-of-warranty paid repair jobs - P3

Provide an optional, disableable paid-repair workflow distinct from warranty claims if mobiST chooses to offer paid repair services. It should support intake, device identifiers, diagnosis, estimate/approval, parts/labor lines where applicable, status lifecycle, collection, payment linkage, and history without weakening warranty rules.

## 5. Website Operating Mode

Implement one Website codebase with a centrally governed, published operating profile. Do not create three separate Websites, repositories, databases, or duplicated page trees.

### 5.1 Supported modes

1. **Digital Solutions Only** - Digital Solutions are publicly active; retail/e-commerce discovery and new commerce flows are disabled.
2. **Digital Solutions + E-commerce** - Complete mobiST Technologies Website with both Digital Solutions and retail/e-commerce capabilities.
3. **E-commerce Only** - Retail/e-commerce is publicly active; Digital Solutions discovery and new digital-service enquiry flows are disabled.

Recommended internal identifiers: `digital_only`, `hybrid`, and `commerce_only`.

### 5.2 Central capability model

The Laravel backend must own the authoritative published Website mode/capability configuration. Next.js must consume that configuration through a defined, cache-safe contract. The mode must not be implemented as a CSS-only hide/show switch.

The mode must govern, as applicable:

- homepage section visibility and content variants;
- header/navigation/footer links and CTAs;
- Products, category, compare, cart, checkout, and new commerce discovery/creation flows;
- Digital Solutions pages, service enquiry creation, project-oriented CTAs, and new digital lead flows;
- About and Contact copy variants;
- promotions and mode-specific landing content;
- sitemap and indexability;
- SEO metadata, canonical behavior, structured data, and social metadata;
- public API exposure/allowlists where capability-specific;
- Next.js cache invalidation/revalidation after publication.

### 5.3 Mode-sensitive content architecture

Avoid maintaining three full copies of every page. Use common content plus capability/mode variants.

- `common` content is shared by all modes.
- `digital` content is available in Digital Solutions modes.
- `commerce` content is available in e-commerce modes.
- Mode-sensitive hero/About/Contact/CTA copy may have `digital_only`, `hybrid`, and `commerce_only` variants where a single shared sentence would be misleading.

The existing modular homepage model should be evolved rather than discarded.

### 5.4 Safe switching behavior

Changing the Website mode must never delete products, services, orders, quotes, customers, content, or historical transactions.

Mode switching governs new public discovery and creation behavior, not legitimate historical access. Existing authorized customers/clients must retain safe access to required order status, invoices, approved project-payment links, completed transaction records, or other secure historical resources even if that capability is no longer publicly marketed. Exact exceptions must be defined and tested.

### 5.5 Admin publication workflow

Provide:

- clear three-way mode selector;
- preview for each mode before publication;
- validation showing which sections/routes/CTAs will become public or unavailable;
- publish permission separate from ordinary content editing where appropriate;
- audit/revision history;
- one-click rollback to a prior valid published mode/configuration;
- cache/sitemap/SEO revalidation after publish.

### 5.6 Mode-aware performance and loading

The three operating modes must reduce unnecessary public work rather than creating a single global bundle that loads all capabilities. Inactive capabilities must not impose unnecessary public JavaScript, API fetching, rendering, media, navigation, sitemap, preloading, or background-request overhead.

Implementation requirements:

- prefer Next.js Server Components and SSR/SSG/ISR according to freshness, security, and interaction needs; use client components only where client-side interaction requires them;
- use route/feature-level code splitting and lazy loading so commerce-only functionality is not unnecessarily shipped on Digital Solutions-only public journeys, and digital-only functionality is not unnecessarily shipped on e-commerce-only journeys;
- make public API queries, cache keys, and server-side data fetching mode-aware so inactive capabilities are not queried or hydrated without need;
- keep catalogue/search/list responses paginated and backed by appropriate MySQL indexes; never load the full inventory dataset into public pages;
- use responsive image sizing, modern optimized formats where supported, lazy loading, and appropriate caching/CDN behavior for public media;
- minimize global JavaScript, third-party scripts, duplicate libraries, and font overhead; defer non-critical assets and scripts;
- invalidate/revalidate only the affected public caches after product, CMS, service, or Website-mode publication changes;
- test representative public pages in all three published modes using production builds and record meaningful performance regressions before release.

Performance targets for representative public pages under controlled production-like audits:

- Largest Contentful Paint (LCP): target `<= 2.5 s`;
- Interaction to Next Paint (INP): target `<= 200 ms`;
- Cumulative Layout Shift (CLS): target `<= 0.1`;
- Mobile Lighthouse Performance: target `90+` where the page and test environment permit a stable representative audit.

These are release targets, not permission to hide unstable measurements. Any justified exception must be documented with its cause, measured impact, and remediation/acceptance decision. Performance is an implementation-time acceptance concern, not an optimization task deferred entirely until project completion.

## 6. Digital Solutions expansion

The existing service catalogue, service-request, project-quote, and secure project-payment concepts must be preserved and expanded rather than replaced without cause.

### 6.1 Dedicated Digital Service landing pages - P1

Each Digital Service should support a rich public landing page with service-specific hero, summary, problem/outcome framing, features/capabilities, deliverables, process, technologies where useful, FAQs, related case studies/testimonials, CTA, SEO/social metadata, and publication controls.

### 6.2 Portfolio and case studies - P1

Add managed case studies with project title, service/category, client/industry disclosure controls, problem, solution, technologies, media/screenshots, measurable outcomes where available, testimonial linkage, sort/visibility, SEO metadata, and draft/preview/publish/revision behavior.

### 6.3 Enhanced project enquiry - P1

Evolve the simple service-request form into a progressive enquiry flow while keeping unnecessary fields optional. Support selected service, project type, existing Website/system URL where relevant, budget range, preferred timeline, requirements, preferred contact, and safe reference-file attachments. Preserve a low-friction enquiry path.

### 6.4 Client project portal - P1

Provide secure client access to project/request/quote/payment/delivery information. Support an appropriate lifecycle such as Request, Discussion, Proposal, Approved, In Progress, Review, Delivered, and Completed, with privacy-safe status/history and only the features justified for the project.

### 6.5 Proposal and milestone payments - P1

Extend approved project quotes into structured proposals and optional payment schedules such as deposit, milestones, and final payment. Preserve exact approved amounts, scope/deliverables, validity/expiry, ownership, payment state, replay protection, and immutable historical snapshots. A paid milestone must not be silently reopened or recalculated.

### 6.6 Service packages and add-ons - P2

Allow a service to be quote-based, fixed-price, starting-from, or package/tier based where appropriate. Support optional add-ons with clear pricing/quote semantics and prevent client-side price tampering.

### 6.7 Consultation/callback booking - P2

Add an optional consultation/callback scheduling flow with configurable availability or preferred time windows, contact method, timezone-safe storage, confirmation state, admin management, and spam/rate controls. External calendar integration is optional unless separately approved.

### 6.8 Digital-service testimonials - P2

Maintain client testimonials separately from retail product reviews. Provide consent/display controls, service/case-study association, moderation, ordering, and publication state.

### 6.9 FAQ, insights, blog, and knowledge content - P2

Expand managed public content for Digital Solutions SEO and education with reusable FAQs, insights/articles, service-related guides, categories/tags where useful, SEO metadata, draft/preview/publish/revision, and safe content/media handling.

### 6.10 Lead and project pipeline - P2

Evolve simple service-request status management into a lightweight pipeline such as New, Contacted, Qualified, Proposal, Approved, In Progress, Completed, and Lost/Closed. Support assigned owner/admin, internal notes, follow-up date, history, filters, and permissions without turning the product into a general-purpose CRM.

### 6.11 Private client file exchange - P2

Allow safe requirement/reference uploads from clients and controlled delivery of project files where appropriate. Use private object storage, authorization, content/size/type validation, immutable/audited references, retention rules, and no public bucket/path exposure.

### 6.12 Digital conversion analytics - P2

Track privacy-conscious conversion events across Digital Service page/CTA -> enquiry -> qualification -> quote/proposal -> approval -> payment/completion. Provide aggregate reporting by service/source/campaign where reliable, without storing unnecessary sensitive tracking data.

## 7. Cross-cutting acceptance requirements

All additions must follow the existing architectural rules:

- Laravel remains the single authoritative business backend.
- MySQL remains the single authoritative shared relational database.
- Next.js receives only defined public/authenticated API contracts; it never receives database credentials.
- Redis is used only for justified cache/queue/session/lock roles.
- S3-compatible/private storage is used where appropriate with authorization and safe uploads.
- Authorization must be enforced server-side; hidden UI is not authorization.
- Money uses exact server-authoritative calculations and historical snapshots.
- Destructive/reset/payment/stock/procurement actions require idempotency, concurrency, rollback, and audit controls where applicable.
- New external notification/payment/calendar/storage integrations remain disabled or safely mocked until authentically configured.
- POS and Website behavior must remain responsive and testable on supported devices.
- Public Website performance budgets and mode-aware loading rules are continuous acceptance criteria; inactive capabilities must not add unnecessary public data-fetching, rendering, JavaScript, media, navigation, or API overhead.
- Production-build performance checks must cover representative pages in `digital_only`, `hybrid`, and `commerce_only` modes, with regressions investigated rather than deferred wholesale to the final audit.
- Applicable backend tests, MySQL concurrency/integrity tests, frontend type/build tests, Playwright journeys, security checks, performance checks, and CI gates must be added before completion claims.

## 8. Explicit non-goals unless separately approved

Do not expand this addendum into unrelated complexity such as:

- multi-vendor marketplace architecture;
- consumer financing/installment lending workflows;
- subscription billing as a general platform capability;
- arbitrary multi-currency accounting;
- a full ERP/general-ledger/accounting suite;
- unnecessary microservices or a parallel Node/Express business backend.

Any future decision to add these requires separate explicit scope approval.

## 9. Roadmap reconciliation requirements

When this addendum is applied to the existing project:

1. Read this document completely together with the canonical Goal, Preferences, Source of Truth, current roadmap, implementation ledger, project registry, feature-parity register, and actual code/schema state.
2. Treat this addendum as approved binding scope expansion unless a direct conflict with the original Goal/Preferences is found. If a real conflict exists, stop and report it instead of guessing.
3. Preserve all completed roadmap point IDs/titles/status claims that remain valid.
4. Do not repeat completed work merely because the roadmap expands.
5. Determine whether any newly approved foundational requirement must be implemented before the currently next pending point; if so, insert a stable dependency-safe point without renumbering completed IDs.
6. Place later UI/CMS/Website features in their natural existing stages and avoid front-loading implementation.
7. Map mode-aware performance/loading requirements into the relevant Website/API acceptance gates and the existing performance/resilience audit; do not defer all performance work until final completion.
8. Update Source of Truth/requirement traceability and the implementation ledger's roadmap references as needed.
9. Perform one structural roadmap Markdown update and one synchronized DOCX regeneration/QA for this expansion.
10. Commit/push the documentation-only reconciliation and verify clean local/upstream/live-main equality.
11. Stop and report the newly resolved `Next` point. Do not implement that point in the same reconciliation checkpoint.

## 10. Approval state

The requirements in this addendum are approved for incorporation into the Mobisttech project plan. Implementation remains subject to the project's existing one-point-at-a-time verified execution process, HOLD boundaries, permissions, security controls, and evidence requirements.
