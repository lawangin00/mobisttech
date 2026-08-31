# mobiST Tech - Project Source of Truth

Version: 1.5 | Date: 2026-08-31

## Authority, Goal and Preferences

`docs/PROJECT_GOAL.md` is the user's approved complete Goal. Original source: `C:\Users\msaee\OneDrive\Desktop\mobisttech-goal.md`. Original SHA-256: `7bce00947418d18151756ea7176b51546b0cbc8ae00c02fda3c1bf7c1344908f`.

`docs/PROJECT_PREFERENCES.md` contains the approved binding implementation Preferences and applies alongside the Goal. Original source: `C:\Users\msaee\OneDrive\Desktop\mobisttech-preferences.md`; SHA-256: `e37c3c2fd6a30ba7211ce73854c79501181b327a98ce7acafa5938ee9941d402`. All 39 numbered Preferences are preserved unchanged.

Goal and Preferences are both binding. This document is their execution map, not a replacement or scope reduction. Current user instructions have highest authority; the Goal defines intended outcomes, Preferences define implementation boundaries, Source of Truth/roadmap define required work, the ledger defines project position, and Git/code evidence proves implementation. Legacy application documents are migration evidence only; their separate-database architecture does not apply to this new project. Global project-control specifications remain in registry/reference documents and are not duplicated into Goal or Preferences.

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
| Shared identities, customer data, roles/authentication | backend | MT-2.2, MT-3.4, MT-4.1, MT-5.2 |
| Products, variants/units, inventory, movements | backend + POS UI | MT-2.3, MT-2.4, MT-4.2 |
| Sales, returns, warranties, reports, documents | backend + POS UI | MT-2.5, MT-2.6, MT-3.1, MT-4.3 |
| Website orders, reservations, payments, integrations | backend + Website | MT-2.7, MT-3.3, MT-3.4, MT-5.3 |
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

## Intended transactional flows

POS product/inventory change -> shared Laravel service -> MySQL commit -> cache invalidation -> Website REST reads current catalogue/pricing/availability.

Website order -> Laravel validates authenticated ownership, prices and availability -> atomic reservation/payment/order processing -> MySQL stock/order state -> POS/admin sees the same records. Callbacks and retries must not duplicate payment, sale, invoice or stock movement.

## Migration design decisions to verify

MT-1.1 inventories 1,224 tracked files, 316 application routes, 42 models, 79 migrations, source methods/settings/commands and 438 fresh isolated test cases. `docs/migration/FEATURE_PARITY_REGISTER.md` records reuse/adapt/refactor/migrate decisions, target gates and gaps. MT-1.2 design is complete: `docs/design/README.md` indexes the shared schema, source-qualified mappings, versioned API, authorization, transaction and recovery contracts. POS users map to outlets; separate credential providers preserve guard semantics; Website products become listings over canonical inventory; customer linking requires verified ownership. The mapping covers 58 source-qualified tables, all 42 models, 79 migration files and 316 routes, with 36 designed API operations and 34 pending implementation cases. POS `User` represents an outlet, not Website customer identity; inventory confirms stock-return behavior but no routed full sale-refund flow. Goal-required returns remain open under MT-2.5. The design preserves immutable history and exact money, defines active IMEI uniqueness, idempotent stock/payment/return transactions, COD holds, cache publication versions and full-schema/key recovery. Specification validation does not prove runtime behavior: MT-2.1 must complete the column manifest and disposable MySQL gates; implementation and parity remain pending. No application foundation or data migration has run.

Website Admin/CMS moves into the shared backend's protected administration area, with separate permissions for POS and Website configuration. Shared branding has one master directory while controlled runtime copies/derivatives may live in application public/storage paths. Website and POS presentation settings may remain logically distinct within the single database.

## Deferred/HOLD and acceptance boundary

Existing payment-provider dependencies remain explicit: JazzCash/Easypaisa require verified contracts and authentic sandbox credentials; Card requires an approved hosted/tokenized processor. Do not collect raw PAN/CVV. Existing COD and safe disabled-provider behavior must be preserved. Do not fabricate sandbox acceptance.

Production-infrastructure design and reproducible deployment/recovery verification are in scope. Purchase, live hosting/domain changes, live provider activation, live customer-data cutover and destructive restore require separate explicit authorization at the relevant gate. Local isolated rehearsals must not write source data. These HOLD items do not waive required migration functionality or production-readiness artifacts.

## Completion

Every point requires source-to-target traceability, Goal/Preferences compliance, applicable focused/full tests and documentation, then a clean intended Git checkpoint in the new repository. Routine completion/progress updates only the implementation ledger. Regenerate the same-basename DOCX only when the roadmap structure/content materially changes. FINAL-AUDIT independently checks the entire Goal and Preferences, feature parity, security, migration/recovery, Windows Control, production readiness, CI and remote synchronization. Only a clean audit permits `Project complete: 100%`; unresolved required work cannot be relabeled Deferred merely to close the project.
