# mobiST Tech - Project Source of Truth

Version: 1.9 | Date: 2026-09-02

## Authority, Goal and Preferences

`docs/PROJECT_GOAL.md` is the user's approved complete Goal. Original source: `C:\Users\msaee\OneDrive\Desktop\mobisttech-goal.md`. Original SHA-256: `7bce00947418d18151756ea7176b51546b0cbc8ae00c02fda3c1bf7c1344908f`.

`docs/PROJECT_PREFERENCES.md` contains the approved binding implementation Preferences and applies alongside the Goal. Original source: `C:\Users\msaee\OneDrive\Desktop\mobisttech-preferences.md`; SHA-256: `e37c3c2fd6a30ba7211ce73854c79501181b327a98ce7acafa5938ee9941d402`. All 39 numbered Preferences are preserved unchanged.

Approved scope expansion `docs/PROJECT_REQUIREMENTS_ADDENDUM_v1.1.md` (2026-09-01; SHA-256 `ec0947bac17002e6d003da31d53b2454d4cfb42be4c321b3b3097775613cccf5`) is binding alongside the original inputs. Full clause-to-point coverage and dependency rationale are in `docs/REQUIREMENTS_ADDENDUM_v1.1_RECONCILIATION.md`. The addendum does not replace either original file or reinitialize the project.

Approved superseding requirement `docs/PROJECT_REQUIREMENTS_UNIFIED_ADMIN_GOOGLE_v1.0.md` (2026-09-01; SHA-256 `76fa2f903fa6e3e07912cfcdb76cb76e10f1ea1d8fd0331591234fd07f5bb458`) is binding. It prospectively replaces the separate administrative credential providers implemented at MT-2.2 and the SMTP/Gmail App Password primary-email assumption. MT-2.2 evidence remains historical and unchanged. Reconciliation and implementation evidence are in `docs/remediation/` under `MT-2.18 - Unified Admin identity and Google integrations remediation`.

Approved requirement `docs/PROJECT_REQUIREMENTS_TEAM_MEMBERS_SESSION_POLICY_v1.0.md` (2026-09-01; SHA-256 `94b42b6462b8e0e70a85991a3609d3de0ef21bd8f3e87ddea600744b8803b472`) is binding. It extends, and does not reverse, MT-2.18: internal personnel are individual Team Members in the single Admin realm; Roles are permission bundles, outlet assignment is independent, delegation is ceiling-bound, and Admin/Customer session policies remain realm-specific. Reconciliation and implementation evidence are in `docs/team-members/` under `MT-2.19 - Team member roles, delegated access and session security remediation`. MT-2.18 and MT-2.7 evidence remain historical and unchanged.

Approved consolidated requirement `docs/PROJECT_REQUIREMENTS_DOCUMENTS_PAYMENTS_LEGAL_MANUAL_v1.0.md` (2026-09-02; SHA-256 `b682fed17cf6d955c2df0cac8eb081b0b641602e2b7391f3137c2e979e69b1a1`) is binding alongside the preceding authorities. It prospectively defines canonical Invoice/Warranty delivery, POS payment methods/destinations/split tender/settlement reconciliation, final legal-policy and software-license/notice review, and a complete final product manual. It adds MT-2.20 and MT-7.6 without reopening completed MT-2.5, MT-2.6, MT-2.7, MT-2.18, MT-2.19 or MT-2.9. Reconciliation is recorded in `docs/consolidated-requirements/RECONCILIATION.md`.

Goal, Preferences and all approved requirement documents are binding. This document is their execution map, not a replacement or scope reduction. Current user instructions have highest authority; the Goal defines intended outcomes, Preferences define implementation boundaries, Source of Truth/roadmap define required work, the ledger defines project position, and Git/code evidence proves implementation. Legacy application documents are migration evidence only; their separate-database architecture does not apply to this new project. Global project-control specifications remain in registry/reference documents and are not duplicated into Goal or Preferences.

## Documentation and communication language policy

Roman Urdu is the default only for assistant chat/UI communication with the user. Git-tracked project documentation and technical artifacts must use standard English by default, including README files, Source of Truth, roadmaps, status ledgers, command registries, technical specifications/evidence and code comments. User-supplied source documents such as the approved Goal and Preferences must remain in their original language/content unless the user explicitly authorizes transformation. Exact technical identifiers, file paths, Git hashes, aliases and official task IDs/titles must remain exact.

## Non-negotiable boundaries

1. One new Git repository exists at `C:\mobisttech`. Components must not become nested Git repositories or inherit old remotes.
2. The original POS/Website repositories, their files, Git metadata, remotes, databases and running services must not be mutated by this project. Reference checks use `git --no-optional-locks` and direct reads; fetch/pull, tests/builds, Composer/npm, artisan, restore or server-control commands must not run against source paths.
3. Source code is inspected/exported from approved pinned commits. Source `.git`, `.env`, keys, customer data, live databases, dependencies, caches, uploads, backups and executable build artifacts must not be blindly copied.
4. Controlled migration preserves valid business behavior and security guarantees. Duplicate implementation may retire only after documented parity evidence and replacement verification; obsolete dual-database synchronization is not part of the target design.
5. Laravel backend and one master MySQL database own shared business state. Next.js must not receive master database credentials or direct transaction-writing access. Redis is derived cache/queue/session infrastructure, not another business master.
6. Authentication/authorization, transaction arithmetic, stock integrity, payment verification and immutable history remain application-controlled. UI visibility is not a security boundary.
7. Existing source completion is not completion of the new target. Fresh target gates are required; source test results may be used only as clearly labeled historical/characterization evidence.
8. Target runtime has one administrative credential identity, `Admin`, shared by POS and Website administration. POS, Website, integration, reset and outlet access are explicit permissions/assignments. Customer identity remains isolated. Legacy administrative source records never merge by email and require explicit verified mapping.
9. The singleton business profile is the current runtime authority for `mobiST Technologies`, `mobisttech@gmail.com` and `https://mobisttech.com`. Gmail API OAuth with only `gmail.send` is the primary transactional-email path; Gmail SMTP/App Password is not normal setup. Google Drive/rclone is backend-only, dynamically connected, private and portable.
10. Internal personnel are individual Team Members authenticated only through Admin. Roles are explicit permission bundles, Job Title grants no authority, outlet assignment is separate, and delegated administration cannot exceed the actor's own permission/outlet ceiling or grant protected Full Access indirectly.
11. Admin sessions expire after 30 minutes of true inactivity, cannot be remembered and use browser-close cookies; background requests do not extend human activity. Customer sessions retain a separate 120-minute inactivity policy and optional remembered login capped at 30 days. Classified sensitive Admin actions use one recent-authentication gate.
12. Invoice and Warranty delivery uses one Laravel-owned canonical document boundary over persisted historical snapshots. Finalization never forces a PDF download or delivery; Preview, Print, Save PDF, Gmail attachment and assisted WhatsApp are explicit authorized actions with truthful state and auditable retry behavior.
13. POS tenders are distinct from Website checkout. POS supports Cash, Card, Mobile Wallet and Bank Transfer with outlet-scoped Payment Destinations and exact split allocations; Website remains fixed to Cash on Delivery, one JazzCash integration, one Easypaisa integration and one hosted/tokenized Credit / Debit Card path, without Website Bank Transfer, split tender or internal destination selection.
14. Sale, payment/tender and settlement are separate records. Fees and settlement variance never rewrite the customer sale/payment. Card PAN, CVV, PIN and stripe data are prohibited. Operational Day Closing separates expected Cash from non-cash destination receipts and does not become a general ledger/ERP.
15. Final public policy content must match actual product, data, payment, delivery, warranty and retention behavior. Owner/legal approval status must remain truthful. Framework/dependency license metadata does not license mobiST-owned application code. A role-aware final user manual with current safe screenshots and verified Markdown/DOCX/PDF parity is required before FINAL-AUDIT.

## Binding implementation preferences

- Before replacing any working feature, verify reuse, adaptation, refactoring and migration options. Rewrite only for documented technical necessity; architecture/frontend change alone is not sufficient justification. Do not copy legacy folders/files without assessing their actual role, dependencies and useful/required/authoritative/historical value. Preserve valid payment, authentication/customer, CMS/catalogue/reviews/checkout, POS/inventory/warranty/report/backup and Dynamic Platform behavior.
- Keep the approved target stack conventional and maintainable. Do not introduce Node/Express or another parallel business backend; Next.js framework/server rendering must not replace Laravel's shared business authority. Do not add unnecessary microservices or infrastructure. Record a stack deviation only for a verified unavoidable technical blocker and the minimum justified change; if it conflicts with the binding Goal, resolve it with the user instead of silently replacing the Goal.
- Use Redis only for a concrete justified role such as cache, queue, session, lock or background processing; document proposed roles and operational costs. Use S3-compatible storage where appropriate; enabling every available infrastructure feature is not a requirement.
- Migrate in incremental, independently verifiable batches. Collect parity, data-consistency, backend/Website-integration and regression evidence for every affected feature; do not defer all verification to the final stage. MySQL migration must preserve valid relationships, constraints, identifiers and business rules. Do not create separate authoritative POS/Website databases or perform a big-bang rewrite.
- Backend and Website are logically separate components, but coordinated commits, shared tooling/docs/brand/CI project-level resources are allowed. Maintain one canonical brand source and one canonical Control application. Framework/runtime-derived asset copies are allowed; duplicate master Brand Kits are not.
- Control must use the current approved logo and must not carry the older legacy logo forward as approved. For the new paths, verify Start, Stop, Restart, Open, Status, Start All and Stop All where applicable; record a concrete reason for any inapplicable control. Safely handle already-running services, duplicate processes and browser tabs. Windows is the local-development environment; Linux/Nginx are future production targets.

MT-0.1 re-verification and all-39-Preference coverage are recorded in `docs/INITIALIZATION_PREFERENCES_RECONCILIATION.md`. MT-1.1 source inventory and target-pending parity gates are recorded in `docs/migration/FEATURE_PARITY_REGISTER.md` and machine inventories. Fresh isolated source characterization does not constitute target implementation completion.

## Requirement map

| Requirement | Target owner | Roadmap coverage |
|---|---|---|
| Independent monorepo, new remote, Goal + Preferences registration | Root | MT-0.1 |
| Valid legacy functionality inventory/parity | docs + migrated tests | MT-1.1, MT-7.5, FINAL-AUDIT |
| Laravel 13 shared backend | backend | MT-1.3, MT-2.1 |
| Master MySQL; merged schema/data; Redis; S3 | backend | MT-1.2, MT-1.3, MT-2.1, MT-7.1 |
| Unified Admin identity, customer isolation, roles/authentication | backend | MT-2.2, MT-2.18, MT-3.4, MT-4.1, MT-5.2 |
| Team Members, Roles, delegated access, outlet ceilings and realm session security | backend + both UIs | MT-2.19, MT-4.1, MT-4.4, MT-5.2, MT-7.2, FINAL-AUDIT |
| Products, variants/units, inventory, movements | backend + POS UI | MT-2.3, MT-2.4, MT-4.2 |
| Sales, returns, warranties, canonical documents and customer delivery | backend + POS UI | MT-2.5, MT-2.6, MT-3.1, MT-4.3, MT-7.2, MT-7.5 |
| Canonical business identity, Gmail API and Google Drive/rclone | backend + Admin + Website | MT-2.18, MT-3.1, MT-3.3, MT-4.4, MT-7.4 |
| Website orders, reservations and payments | backend + Website | MT-2.7, MT-3.4, MT-5.3 |
| POS payment methods/destinations, split tender, refunds and settlement reconciliation | backend + POS UI | MT-2.20, MT-2.12, MT-3.1, MT-4.2, MT-4.6, MT-7.2, MT-7.5 |
| Actual legal/policy drafts, publication, privacy/data-flow consistency and sign-off | backend CMS + Website | MT-3.2, MT-4.4, MT-5.4, MT-7.2, MT-7.5, FINAL-AUDIT |
| Project ownership/license and third-party notice review | Root + release audit | MT-7.2, MT-7.5, FINAL-AUDIT |
| Complete role-aware product user manual and safe final screenshots | docs + final product surfaces | MT-7.6, FINAL-AUDIT |
| React/TypeScript/Inertia/Tailwind POS | backend | MT-4.1 through MT-4.4 |
| Next.js/React/TypeScript/Tailwind Website | website | MT-5.1 through MT-5.4 |
| Catalogue, filters, product pages, SEO | website + REST API | MT-3.4, MT-5.1 |
| Account, cart, checkout, orders, reviews | website + REST API | MT-5.2, MT-5.3 |
| Digital solutions/public content | backend CMS + website | MT-3.2, MT-5.4 |
| Completed Dynamic Platform, Website Admin/CMS | backend + both UIs | MT-3.2, MT-3.3, MT-4.4, MT-5.4 |
| One canonical brand directory; runtime placement | brand + both UIs | MT-6.1 |
| One canonical Control; current logo; Start All/Stop All | tools/mobist-control | MT-6.2, MT-6.3 |
| Windows local development | entire monorepo | MT-1.3, MT-6.3 |
| Linux/Nginx/TLS/backups/S3 production target | deployment configuration/docs | MT-7.4 |
| Pest/PHPUnit, Playwright, GitHub Actions | backend + website + CI | All applicable points, MT-7.2, MT-7.3, MT-7.5 |

## Approved expansion architecture

Use one published Laravel-owned Website operating profile (`digital_only`, `hybrid`, `commerce_only`) consumed through a versioned cache-safe Next.js contract. Capability guards govern discovery/new creation, APIs, navigation/home/About/Contact/CTAs, promotions and sitemap/SEO/canonical/structured/social metadata; they are never CSS-only. Evolve modular common/digital/commerce content and mode copy variants, not three page trees. Switching deletes no records and preserves explicitly authorized order/invoice/project-payment/history access. Separate preview/edit/publish permissions, impact validation, audit/revision/one-click rollback and affected-cache revalidation are required.

Retail expansion includes guarded three-level reset, stocktake, transfers, supplier/PO/receiving, cash sessions/expenses, scanner/labels, reorder, trade-in, promotion/coupons, bulk workflows, consented notifications, wishlist, optional loyalty and optional paid repair. Digital expansion evolves existing services/requests/quotes into rich landings, case studies, progressive enquiries, private portal/proposals/milestones, packages/add-ons, optional booking, distinct testimonials, knowledge content, lightweight pipeline, private files and aggregate conversion analytics. Preserve reuse and exact money/history; optional enablement does not waive implementation. Full clause details and priority are retained in the approved addendum and its traceability register.

The consolidated delivery/payment/legal/manual requirement is prospective. Existing Invoice, sale, Warranty, claim, Website payment, Gmail, business-profile, permission and audit foundations are reused rather than reopening their completed checkpoints. MT-2.20 adds POS-only Payment Methods, outlet-scoped Payment Destinations, atomic split tenders, safe external-terminal references, cash/change, refund allocation history and settlement reconciliation. MT-2.12 consumes those records for Day Closing. Website checkout remains exactly COD/JazzCash/Easypaisa/Card and classifies into shared reporting without exposing internal destinations.

MT-3.1 owns one canonical Laravel document renderer/delivery service for Invoice and Warranty snapshots, optional historical customer-email capture, six managed Email/WhatsApp templates, explicit Print/Save PDF, Gmail PDF attachments, assisted WhatsApp and truthful idempotent delivery audit. MT-3.2/MT-4.4/MT-5.4 own typed reviewed policy drafts, approval/version/effective dates, protected publication and footer destinations. MT-7.2/MT-7.5 verify facts, security, licensing/notices and final journeys. MT-7.6 creates the complete role-aware manual only from the final verified product and current safe screenshots; FINAL-AUDIT depends on it.

Reset levels must distinguish transactional history, business data and factory baseline, showing preserved identity/configuration/branding/master data or minimum bootstrap as applicable. Permission/recent re-authentication, affected record/file-count preview, dependency checks, automatic verified backup or stop, typed destructive confirmation and surviving actor/scope/time/backup/result audit are mandatory. Private object cleanup matches database semantics; no normal reset may use migrate:fresh, indiscriminate truncation or schema destruction. Retention/inventory conflicts block a plan instead of weakening historical integrity. Production reset/cutover remains separately authorized.

Mode-aware performance is continuous acceptance. Prefer Server Components and freshness/security-appropriate SSR/SSG/ISR, interaction-only client components, feature splitting/lazy loading, mode-aware data/cache keys, indexed pagination, responsive optimized/lazy media and minimal global scripts/libraries/fonts. Inactive capabilities must not impose unnecessary JS, fetching, rendering, hydration, media, preloads or background work. Representative production-build audits in all modes target LCP <= 2.5 s, INP <= 200 ms, CLS <= 0.1 and stable mobile Lighthouse Performance 90+; record conditions, regressions and any exception's cause, measured impact and remediation/acceptance. Lighthouse alone does not prove interaction latency.

MT-2.8 is the new foundational dependency before sales migration: validate additive custody/procurement, capability, money/milestone, reset preservation and permission contracts against existing schema and immutable identifiers. Feature services follow in their individual points; later UI/CMS/Website stages are not front-loaded. All original completed IDs remain unchanged. Explicit document/dependency order takes precedence over numeric sorting. Marketplace, lending, general subscription billing, arbitrary multi-currency accounting, full ERP/general ledger and unnecessary microservices remain out of scope absent separate approval.

## Intended transactional flows

POS product/inventory change -> shared Laravel service -> MySQL commit -> cache invalidation -> Website REST reads current catalogue/pricing/availability.

Website order -> Laravel validates authenticated ownership, prices and availability -> atomic reservation/payment/order processing -> MySQL stock/order state -> POS/admin sees the same records. Callbacks and retries must not duplicate payment, sale, invoice or stock movement.

POS sale preparation -> canonical prices/discounts and optional customer email -> Preview -> authorized exact tender allocations -> atomic final Invoice/sale/payment/stock commit -> explicit document actions. Delivery failure occurs after finalization and cannot roll back or falsify the completed transaction.

Invoice/Warranty history -> persisted transaction-time snapshots -> one canonical renderer -> Preview/Thermal/A4/Print/Save PDF/Gmail attachment or assisted WhatsApp. Opening WhatsApp is not delivery; Gmail success is recorded only from the backend provider result.

POS tenders -> method plus authorized outlet destination -> gross customer payment -> later separate settlement/fee/variance evidence -> Day Closing Cash and non-cash destination reconciliation. The sale total is counted once regardless of allocation count.

## Migration design decisions to verify

MT-1.1 inventories 1,224 tracked files, 316 application routes, 42 models, 79 migrations, source methods/settings/commands and 438 fresh isolated test cases. `docs/migration/FEATURE_PARITY_REGISTER.md` records reuse/adapt/refactor/migrate decisions, target gates and gaps. MT-1.2 design is complete: `docs/design/README.md` indexes the shared schema, source-qualified mappings, versioned API, authorization, transaction and recovery contracts. POS users map to outlets; the original design used separate credential providers to preserve source guard semantics; Website products become listings over canonical inventory; customer linking requires verified ownership. The mapping covers 58 source-qualified tables, all 42 models, 79 migration files and 316 routes, with 36 designed API operations and 34 pending implementation cases. POS `User` represents an outlet, not Website customer identity; inventory confirms stock-return behavior but no routed full sale-refund flow. Goal-required returns remain open under MT-2.5. The design preserves immutable history and exact money, defines active IMEI uniqueness, idempotent stock/payment/return transactions, COD holds, cache publication versions and full-schema/key recovery. Specification validation does not prove runtime behavior: MT-2.1 must complete the column manifest and disposable MySQL gates; implementation and parity remain pending. That was the MT-1.2 design baseline, not the current implementation position. Subsequent foundation, schema, identity, product and stock checkpoints are evidenced in the implementation ledger; private source-data migration remains unperformed. Addendum v1.1 requires additive design under MT-2.8 rather than retroactively reopening the original design checkpoint. MT-2.18 prospectively supersedes only the target administrative credential/integration behavior; source mapping/history remains evidence.

Website Admin/CMS moves into the shared backend's protected administration area, with separate permissions for POS and Website configuration. Shared branding has one master directory while controlled runtime copies/derivatives may live in application public/storage paths. Website and POS presentation settings may remain logically distinct within the single database.

MT-2.19 adds relational role and permission assignments without creating another credential realm or rewriting MT-2.18 history. Existing explicit per-Admin permissions remain compatible during controlled migration; new Team Members receive named roles. Full Access is a protected explicit permission snapshot, supplied business roles are stable templates, and Custom Roles never receive future permissions implicitly. Team Member management validates permission and outlet ceilings server-side, blocks self-promotion and indirect Full Access, revokes disabled/password-changed sessions and records immutable actor/role/outlet snapshots for administration and operational audit events. Laravel owns the realm session clocks, capped remember duration and recent-authentication classification; POS and Website clients consume these contracts rather than implementing competing policies.

The protected POS source provides reusable Invoice/Warranty preview, Thermal/A4/print and assisted WhatsApp concepts, but browser chat opening is not proof of attachment or delivery. The protected Website source provides fixed provider and generic legal managed-page foundations. Target implementation must adapt these behaviors into the shared Laravel authority, preserve historical snapshots and reject duplicate/scattered renderers, misleading send states or mutable public copy that rewrites financial/warranty history.

The repository currently has no root application license. Backend Composer `license: MIT` is inherited package metadata and must not be treated as a license grant for the complete private mobiST Tech product. MT-7.2 must inventory distributed/runtime dependency and asset obligations, surface the owner decision for project-level proprietary/open-source terms, and produce appropriate LICENSE/NOTICE artifacts only after that decision. No legal approval is fabricated by this structural reconciliation.

## Deferred/HOLD and acceptance boundary

Existing payment-provider dependencies remain explicit: JazzCash/Easypaisa require verified contracts and authentic sandbox credentials; Card requires an approved hosted/tokenized processor. Do not collect raw PAN/CVV. Existing COD and safe disabled-provider behavior must be preserved. Do not fabricate sandbox acceptance.

Gmail document sending must use the already approved OAuth/Gmail API `gmail.send` boundary and approved sender; authentic connection/test-send remains an external HOLD. Automated acceptance uses safe fakes and never sends a real message. WhatsApp remains assisted unless a separately approved Business Platform/API integration is configured; opening a chat must never be labeled Sent/Delivered.

Final policy drafting may require owner decisions for return windows, eligibility, delivery commitments, commercial terms, jurisdiction and software ownership. These are explicit release/sign-off inputs, not permission to invent terms or omit the policy/manual gates. Cookie policy/consent is conditional on actual non-essential tracking.

Production-infrastructure design and reproducible deployment/recovery verification are in scope. Purchase, live hosting/domain changes, live provider activation, live customer-data cutover and destructive restore require separate explicit authorization at the relevant gate. Local isolated rehearsals must not write source data. These HOLD items do not waive required migration functionality or production-readiness artifacts.

## Completion

Every point requires source-to-target and approved-requirement traceability, Goal/Preferences compliance, applicable focused/full tests and documentation, then a clean intended Git checkpoint in the new repository. Routine completion/progress updates only the implementation ledger. Regenerate the same-basename DOCX only when the roadmap structure/content materially changes. FINAL-AUDIT independently checks every authority including the consolidated document/payment/legal/manual requirement, feature parity, security, migration/recovery, Windows Control, production readiness, CI and remote synchronization. Only a clean audit permits `Project complete: 100%`; unresolved required work cannot be relabeled Deferred merely to close the project.
