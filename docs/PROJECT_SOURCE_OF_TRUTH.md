# mobiST Tech - Project Source of Truth

Version: 1.1 | Date: 2026-08-31

## Authority, Goal aur Preferences

`docs/PROJECT_GOAL.md` user ka approved complete Goal hai. Original source: `C:\Users\msaee\OneDrive\Desktop\mobisttech-goal.md`. Original SHA-256: `7bce00947418d18151756ea7176b51546b0cbc8ae00c02fda3c1bf7c1344908f`.

`docs/PROJECT_PREFERENCES.md` approved binding implementation Preferences hain, Goal ke saath apply hongi. Original source: `C:\Users\msaee\OneDrive\Desktop\mobisttech-preferences.md`; SHA-256: `e37c3c2fd6a30ba7211ce73854c79501181b327a98ce7acafa5938ee9941d402`. Tamam 39 numbered preferences unchanged preserve ki gayi hain.

Goal aur Preferences dono binding hain. Yeh document unka execution map hai, replacement ya scope reduction nahi. User ki current instructions sab se upar hain; Goal outcome, Preferences implementation boundaries, Source of Truth/roadmap required work, ledger position aur Git/code evidence define karte hain. Purani application documents sirf migration evidence hain; unki two-database architecture naye project par apply nahi hoti. Global project-control specifications registry/reference docs mein rahengi; Goal ya Preferences mein duplicate nahi hongi.

## Non-negotiable boundaries

1. Aik naya Git repository `C:\mobisttech` par hoga. Components nested Git repositories ya old remotes nahi banenge.
2. Purane POS/Website repositories, unki files, Git metadata, remotes, databases aur running services ko is project se mutate nahi karna. Reference checks `git --no-optional-locks` aur direct reads se hon; fetch/pull, tests/builds, Composer/npm, artisan, restore ya server control source paths par nahi chalega.
3. Source code approved pinned commits se inspect/export hoga. Source `.git`, `.env`, keys, customer data, live databases, dependencies, caches, uploads, backups aur executable build artifacts ko blind-copy nahi karna.
4. Controlled migration mein valid business behavior aur security guarantees preserve honge. Duplicate implementation sirf documented parity evidence aur replacement verification ke baad retire hogi; obsolete dual-database synchronization target design nahi hai.
5. Laravel backend aur aik master MySQL database shared business state own karenge. Next.js ko master database credentials ya direct transaction-writing access nahi milega. Redis derived cache/queue/session infrastructure hai; separate business master nahi.
6. Authentication/authorization, transaction arithmetic, stock integrity, payment verification aur immutable history application-controlled rahenge. UI visibility security boundary nahi.
7. Existing source completion naye target ki completion nahi. Fresh target gates required hain; source test results historical evidence ke label ke saath hi use hon.

## Binding implementation preferences

- Har working feature ko replace karne se pehle reuse, adapt, refactor aur migrate options verify karein. Rewrite sirf documented technical necessity par; architecture/frontend change khud sufficient reason nahi. Legacy folders/files ko actual role/dependencies aur useful/required/authoritative/historical value assess kiye baghair copy nahi karna. Valid payment, auth/customer, CMS/catalogue/reviews/checkout, POS/inventory/warranty/report/backup aur Dynamic Platform behavior preserve hoga.
- Approved target stack conventional aur maintainable rahe. Node/Express ya koi parallel business backend nahi; Next.js framework/server rendering Laravel ki shared business authority replace nahi karegi. Unnecessary microservices ya infrastructure add nahi karna. Stack deviation sirf verified unavoidable technical blocker aur minimum justified change ke saath record ho; binding Goal se conflict ho to user se resolve karein, silent replacement nahi.
- Redis cache, queue, session, lock ya background processing mein sirf concrete justified role ke liye use ho; har proposed role aur operational cost document ho. S3-compatible storage jahan appropriate ho; har available infrastructure feature enable karna requirement nahi.
- Migration incremental, independently verifiable batches mein ho. Har affected feature par parity, data consistency, backend/Website integration aur regression evidence collect ho; tests sirf final stage tak defer nahi hon. MySQL migration valid relationships, constraints, IDs aur business rules preserve kare. Separate authoritative POS/Website databases ya big-bang rewrite nahi.
- Backend/Website logically separate components hain, lekin coordinated commits, shared tooling/docs/brand/CI project-level resources ho sakte hain. Aik canonical brand aur aik canonical Control hi rahenge. Runtime-derived asset copies allowed hain, duplicate master Brand Kits nahi.
- Control current approved logo use kare, legacy older logo ko approved samajh kar carry forward nahi karna. New paths ke liye Start, Stop, Restart, Open, Status, Start All aur Stop All jahan applicable hon verify karein; inapplicable control ka concrete reason record ho. Already-running services, duplicate processes aur browser tabs ko safely handle karein. Windows local hai; Linux/Nginx future production ke liye hain.

MT-0.1 re-verification aur all-39-preference coverage: `docs/INITIALIZATION_PREFERENCES_RECONCILIATION.md`. Yeh implementation inventory nahi; MT-1.1 pending rahega.

## Requirement map

| Requirement | Target owner | Roadmap coverage |
|---|---|---|
| Independent monorepo, naya remote, Goal + Preferences registration | Root | MT-0.1 |
| Valid legacy functionality ki inventory/parity | docs + migrated tests | MT-1.1, MT-7.5, FINAL-AUDIT |
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

MT-1.1 will inventory every required source route, role, model, integration, screen, job, report, print surface and test. MT-1.2 will resolve overlapping `User`, `Product`, order/payment and setting concepts with ID mappings, relationship preservation, historical snapshots and public/private data separation. Detailed entity and API design must come from source code; this initialization does not invent a completed schema.

Website Admin/CMS moves into the shared backend's protected administration area, with separate permissions for POS and Website configuration. Shared branding has one master directory while controlled runtime copies/derivatives can live in application public/storage paths. Website and POS presentation settings may remain logically distinct in the single database.

## Deferred/HOLD and acceptance boundary

Existing payment-provider dependencies remain explicit: JazzCash/Easypaisa require verified contracts and authentic sandbox credentials; Card requires an approved hosted/tokenized processor. No raw PAN/CVV collection. Existing COD and safe disabled-provider behavior must be preserved. No fabricated sandbox acceptance.

Production infrastructure design and reproducible deployment/recovery verification are in scope. Purchase, live hosting/domain changes, live provider activation, live customer-data cutover and destructive restore require separate explicit authorization at the relevant gate. Local isolated rehearsals must not write source data. These holds do not waive required migration functionality or production-readiness artifacts.

## Completion

Each point needs source-to-target traceability, Goal/Preferences compliance, applicable focused/full tests and documentation, then a clean intended Git checkpoint in the new repository. Roadmap changes regenerate the same-basename DOCX. FINAL-AUDIT independently checks the entire Goal and Preferences, feature parity, security, migration/recovery, Windows Control, production readiness, CI and remote sync. Only a clean audit permits `Project complete: 100%`; unresolved required work cannot be relabeled Deferred merely to close the project.
