# Approved addendum v1.1 - Reconciliation and requirement traceability

Date: 2026-09-01 | Source of Truth/roadmap: v1.6

## Authority and scope

Approved input: `docs/PROJECT_REQUIREMENTS_ADDENDUM_v1.1.md`, SHA-256 `ec0947bac17002e6d003da31d53b2454d4cfb42be4c321b3b3097775613cccf5`. Its original bytes, Goal and Preferences are preserved. The addendum supplements rather than replaces them. This checkpoint changes control documentation only and is not a roadmap implementation point or a second initialization. All leaf clauses are mapped below; full approved wording remains binding even where roadmap scopes are concise.

No direct conflict was found. Normal history preservation remains mandatory; the explicit guarded reset is a separately permissioned exception for the selected legally/operationally safe scope, not permission for indiscriminate history deletion. If retained history, current inventory or mandatory retention cannot be preserved by a chosen reset plan, execution must stop. Mode switching never acts as a reset. Optional/disableable features remain required implementation with safe off states; P1/P2/P3 express priority, not deferral authority.

## Verified dependency analysis

Baseline Git commit: `d2b2408797324528cff5160ef8b0485740f451e0`. Eight completed points remain valid: MT-0.1, MT-1.1, MT-1.2, MT-1.3, MT-2.1, MT-2.2, MT-2.3 and MT-2.4. Their original titles, scope, acceptance and dependency blocks are retained byte-for-byte in the structural plan. No completed point is reopened or counted again. Historical evidence remains immutable; prior 89 target tests/4,511 assertions are checkpoint evidence, not tests run by this documentation change.

`docs/schema/TARGET_SCHEMA.json`, current migrations, `Product`, `StockUnit`, `StockLedger`, `TransactionalStock`, `AcquisitionDocuments`, `PrivateObjects`, identity permissions and the minimal `website/src/app` were inspected. Product ownership is outlet-scoped; physical units and active claims point to products, while immutable product/unit codes and historical invoice/acquisition references must survive transfer. Existing acquisitions contain party snapshots but no formal supplier/PO, count session, custody transfer or cash-session domain. Existing digital services/requests/quotes and generic CMS/settings/revision tables are reusable foundations, not implemented rich landing/proposal/milestone/portal/mode workflows. There is no target reset service or full public Website to configure. No database or source service was started to manufacture evidence.

Therefore insert **MT-2.8 - Addendum foundations and capability contracts** immediately after MT-2.4 and before MT-2.5. It must choose the smallest backward-compatible schema/contract additions and validate current regressions before sales hardens money/custody/retention relationships. It does not implement all additions: supplier/count/transfer/cash/trade/promotion/bulk/loyalty/repair workflows, CMS/mode publication, digital projects, notifications, reset and interfaces have their own later points.

The foundational gate must resolve stable origin identity versus current transfer custody (never blindly reparent historic units), reset preservation versus RESTRICT financial references/current inventory, and authoritative discount/reward/trade-in/milestone snapshots and capability permissions. No exact new table design is claimed approved here; MT-2.8 must verify the additive design and MySQL rollback. Existing completed migrations are not rewritten. Money, stock and reset concerns justify independent backend points; related presentation and content fields extend still-pending points to avoid unnecessary fragmentation.

The structural plan now has 52 points: 32 original IDs retained and 20 stable new IDs. The ledger remains 8 completed / 44 pending; the denominator change reflects new scope, not lost completion. Execute explicit document/dependency order, not numeric sorting: new MT-2.8 precedes unchanged MT-2.5, and new later IDs are inserted where their prerequisites are available. Backend foundations/services precede APIs and UIs; reset follows verified backup and all resettable business domains; Website and performance gates remain incremental.

## Clause traceability

| Addendum clause | Approved requirement | Implementation or control gates | Required acceptance detail |
|---|---|---|---|
| 1 | Supplemental authority | Control pointers, preserved inputs | No replacement/translation of Goal or Preferences; no completed ID renumbering. |
| 2 | Incremental execution | MT-2.8; all added points | Documentation-only adoption now; one verified implementation point per later command. |
| 3.1 | Transactional Data Reset | MT-2.8, MT-3.8, MT-4.7, MT-7.1 | Selected sales/invoices/returns/refunds/warranties/claims/orders/carts/payments/service/quote history and dependent documents; preserve products/inventory, identities, permissions, configuration, branding, master data and infrastructure. Stop on legal/operational retention or integrity conflicts. |
| 3.2 | Business Data Reset | MT-2.8, MT-3.8, MT-4.7, MT-7.1 | Transactional plus selected products/units/inventory/acquisitions/customers/suppliers/procurement/business content; retain minimum bootstrap, authorized admin/superadmin/outlet identities, permissions, configuration, branding and required system/master data. |
| 3.3 | Factory Reset | MT-3.8, MT-4.7, MT-7.1 | Document the clean operational baseline and retain only the minimum authorized access/configuration bootstrap. |
| 3.4 | Reset safeguards | MT-2.8, MT-3.3, MT-3.8, MT-4.7, MT-7.1, MT-7.2 | Permission, recent re-authentication, domain record/file dry-run counts, dependency-safe deletion/integrity, automatic verified backup or stop, typed level confirmation, preserved actor/scope/time/backup/result audit and DB-matched private object cleanup. No migrate:fresh, raw indiscriminate truncation or schema destruction; production authorization is separate. |
| 4.1 | P1 stocktake/cycle counting | MT-2.10, MT-4.5 | Quantity and serialized/IMEI sessions, expected/counted variance, reason codes, approvals, recounts and auditable adjustments; no fabricated history. |
| 4.2 | P1 inter-outlet transfers | MT-2.8, MT-2.11, MT-4.5 | Dispatch/in-transit/receive/reject/partial receive, quantity and serialized identity/custody, immutable history, role control, IMEI uniqueness, race/rollback checks. |
| 4.3 | P1 suppliers and purchase orders | MT-2.9, MT-4.5 | Supplier contacts/profiles, PO lines/status/expected dates, partial receiving, landed/unit cost, supplier history and acquisition traceability. |
| 4.4 | P1 cash drawer, daily close, expenses | MT-2.12, MT-3.1, MT-4.6 | Opening/receipts/authorized payouts/expenses/closing counts, variance/reconciliation, operator/outlet ownership, audit and reports; not ERP accounting. |
| 4.5 | P1 scanner entry and retail labels | MT-3.1, MT-4.2, MT-4.5 | Barcode/QR/IMEI lookup/entry and authorized identifier label printing; scanner input never bypasses validation. |
| 4.6 | P2 low-stock/reorder | MT-2.9, MT-4.5 | Product/outlet thresholds, low/out-of-stock alerts, recommendations and procurement links preserve existing reports. |
| 4.7 | P2 trade-in/buyback | MT-2.13, MT-4.6 | Device identity/condition/diagnostics/valuation/source ownership, exact purchase or sale credit, intake/audit and active-identifier safeguards. |
| 4.8 | P2 promotions/coupons | MT-2.8, MT-2.14, MT-4.2, MT-4.4, MT-5.3 | Fixed/percentage, validity, min/max, product/category applicability, usage limits, customer/order restrictions and stacking; one server calculation across POS/Website. |
| 4.9 | P2 bulk import/export | MT-2.17, MT-4.5 | CSV/XLSX-style preview, row validation/errors, permission, replay/idempotency and rollback/recovery; private-field minimization and safe exports. |
| 4.10 | P2 availability/price notifications | MT-3.9, MT-5.2 | Verified product/customer links, opt-in/consent/preferences, rate limits, deduplication, unsubscribe and disabled external delivery. |
| 4.11 | P3 wishlist/save for later | MT-3.9, MT-5.2 | Customer ownership, appropriate guest/account transition, unavailable products and mode awareness. |
| 4.12 | P3 optional loyalty/rewards | MT-2.8, MT-2.15, MT-4.2, MT-4.4, MT-5.2, MT-5.3 | Earning/redemption/expiry/cancellation/return reversal, abuse prevention, audit/configuration and disablement; separate from authoritative money. |
| 4.13 | P3 optional paid repair jobs | MT-2.16, MT-4.6 | Intake/device/diagnosis/estimate approval/parts/labor/lifecycle/collection/payment/history; disableable and distinct from warranty claims. |
| 5.1 | Three Website modes | MT-2.8, MT-3.7, MT-4.4, MT-5.1 | Digital Solutions Only=digital_only; Digital Solutions + E-commerce=hybrid; E-commerce Only=commerce_only. One codebase/repo/database, no duplicated full page trees. |
| 5.2 | Central capability authority | MT-2.8, MT-2.7, MT-3.7, MT-3.4, MT-5.1, MT-5.3, MT-5.4 | Published Laravel profile governs home/navigation/footer/CTAs, product/category/compare/cart/checkout, digital discovery/enquiries, About/Contact, promotions, sitemap/indexability, SEO/canonicals/structured/social data, public API allowlists and Next cache revalidation. Never CSS-only. |
| 5.3 | Common and variant content | MT-3.2, MT-3.7, MT-4.4, MT-5.1, MT-5.4 | Evolve modular home content: common/digital/commerce plus digital_only/hybrid/commerce_only hero/About/Contact/CTA variants where needed. |
| 5.4 | Safe switching and historical access | MT-2.8, MT-2.7, MT-3.6, MT-3.7, MT-3.4, MT-5.2, MT-5.3, MT-5.5 | Never delete products/services/orders/quotes/customers/content/history on mode switch. Explicitly test owned status/invoices, approved project-payment links and completed records despite disabled marketing; deny new inactive-capability creation. |
| 5.5 | Preview/publish/rollback | MT-3.7, MT-4.4 | Three-way selector, per-mode preview, section/route/CTA impact validation, separate publish permission, audit/revisions, one-click valid rollback and cache/sitemap/SEO revalidation. |
| 5.6 | Mode-aware performance/loading | MT-3.4, MT-5.1, MT-5.3, MT-5.4, MT-5.5, MT-7.2, MT-7.3 | Prefer Server Components and freshness/security-appropriate SSR/SSG/ISR; interaction-only client components, route/feature splitting/lazy loading, mode-aware queries/cache/data fetching, indexed pagination (never full inventory), responsive optimized/lazy media/cache/CDN, minimal JS/third parties/duplicate libraries/fonts and deferred noncritical assets. Invalidate affected caches only. Measure all modes using production builds; targets LCP<=2.5s, INP<=200ms, CLS<=0.1, stable representative mobile Lighthouse90+. Exceptions need cause, measured impact and remediation/acceptance, not hidden instability or wholesale final-stage deferral. |
| 6.1 | P1 rich digital service landing pages | MT-3.2, MT-3.5, MT-4.4, MT-5.4 | Hero/summary/problem/outcome/features/deliverables/process/technologies/FAQ/related cases/testimonials/CTA, SEO/social metadata and publication. |
| 6.2 | P1 portfolio/case studies | MT-3.2, MT-4.4, MT-5.4 | Title/service/category, client/industry disclosure, problem/solution/technology/media/outcomes/testimonial links, visibility/order, SEO and draft/preview/publish/revision. |
| 6.3 | P1 progressive project enquiry | MT-3.5, MT-4.8, MT-5.4 | Service/project type/existing URL/budget/timeline/requirements/contact and safe reference attachments; unnecessary fields optional, low-friction entry retained. |
| 6.4 | P1 client project portal | MT-3.6, MT-4.8, MT-5.5 | Owned request/quote/payment/delivery lifecycle Request, Discussion, Proposal, Approved, In Progress, Review, Delivered, Completed with privacy-safe history. |
| 6.5 | P1 proposals and milestone payments | MT-2.8, MT-2.7, MT-3.6, MT-4.8, MT-5.5 | Approved scope/deliverables/validity/ownership and optional deposit/milestone/final schedules; exact historical amounts, replay protection, immutable paid milestones. |
| 6.6 | P2 service packages/add-ons | MT-3.5, MT-4.8, MT-5.4 | Quote-based/fixed/starting-from/package tiers, optional add-ons, clear authoritative price semantics and no client tampering. |
| 6.7 | P2 optional consultation/callback | MT-3.5, MT-4.8, MT-5.4, MT-5.5 | Configured availability or preferred windows, contact method, timezone-safe persistence, confirmation/admin/spam/rate controls; external calendar optional. |
| 6.8 | P2 digital testimonials | MT-3.2, MT-4.4, MT-5.4 | Separate from retail reviews; consent/display controls, service/case association, moderation/order/publication. |
| 6.9 | P2 FAQ/insights/blog/knowledge | MT-3.2, MT-4.4, MT-5.4 | Reusable FAQs, articles/guides, useful categories/tags, service associations, SEO, draft/preview/publish/revisions and safe media/content. |
| 6.10 | P2 lead/project pipeline | MT-3.5, MT-3.6, MT-4.8 | New, Contacted, Qualified, Proposal, Approved, In Progress, Completed, Lost/Closed; assigned owner/internal notes/follow-up/history/filters/permissions, not a general CRM. |
| 6.11 | P2 private client file exchange | MT-3.6, MT-4.8, MT-5.5 | Private requirement/reference uploads and deliveries with authorization, content/size/type checks, immutable audited refs/retention; no public bucket/path exposure. |
| 6.12 | P2 digital conversion analytics | MT-3.6, MT-4.8, MT-5.5 | Privacy-conscious service/CTA-enquiry-qualification-proposal-approval-payment/completion events; reliable aggregate service/source/campaign reports with minimal sensitive data. |
| 7 | Cross-cutting gates | All affected points; MT-7.1, MT-7.2, MT-7.3, MT-7.5, FINAL-AUDIT | Single Laravel/MySQL authority, defined REST without DB credentials, justified Redis/private storage, server permissions/exact money, idempotency/concurrency/rollback/audit, safe disabled integrations, responsive UI, production-build per-mode budgets, MySQL/frontend/Playwright/security/CI before completion. |
| 8 | Explicit non-goals | Source of Truth; H-04; FINAL-AUDIT | No marketplace, consumer financing/lending, general subscription billing, arbitrary multi-currency accounting, full ERP/general ledger, unnecessary microservices or parallel Node/Express backend absent separate approval. |
| 9 | Reconciliation lifecycle | This documentation checkpoint; MT-2.8 next | Read all authorities/code, preserve completed IDs/status, map dependencies and performance continuously, update controls, generate/QA Word once, commit/push clean and stop without implementation. |
| 10 | Approved scope, controlled execution | All later point commands | Approval incorporates scope; existing permissions, HOLD and one-point verification govern implementation and live actions. |

## New stable points

- `MT-2.8 - Addendum foundations and capability contracts`; prerequisite `MT-2.4`.
- `MT-2.9 - Supplier and procurement services`; prerequisite `MT-2.7`.
- `MT-2.10 - Stocktake and cycle-count services`; prerequisite `MT-2.9`.
- `MT-2.11 - Inter-outlet stock transfer services`; prerequisite `MT-2.10`.
- `MT-2.12 - Cash sessions and operational expense services`; prerequisite `MT-2.11`.
- `MT-2.13 - Trade-in and buyback services`; prerequisite `MT-2.12`.
- `MT-2.14 - Promotion and coupon services`; prerequisite `MT-2.13`.
- `MT-2.17 - Validated bulk data workflows`; prerequisite `MT-2.14`.
- `MT-2.15 - Customer loyalty services`; prerequisite `MT-2.17`.
- `MT-2.16 - Paid repair job services`; prerequisite `MT-2.15`.
- `MT-3.7 - Website operating mode publication`; prerequisite `MT-3.2`.
- `MT-3.5 - Digital service catalogue and lead services`; prerequisite `MT-3.7`.
- `MT-3.6 - Client projects, proposals and milestone services`; prerequisite `MT-3.5`.
- `MT-3.9 - Customer engagement and notification services`; prerequisite `MT-3.6`.
- `MT-3.8 - Guarded data reset services`; prerequisite `MT-3.3`.
- `MT-4.5 - Procurement and stock control interfaces`; prerequisite `MT-4.2`.
- `MT-4.6 - Cash, trade-in and repair interfaces`; prerequisite `MT-4.5`.
- `MT-4.8 - Digital operations administration interfaces`; prerequisite `MT-4.4`.
- `MT-4.7 - Data reset administration interface`; prerequisite `MT-4.8`.
- `MT-5.5 - Client project portal and digital conversion journeys`; prerequisite `MT-5.4`.

## Publication, performance and reset acceptance decisions

Mode contract design must distinguish anonymous discovery/new creation from required authenticated history. The test matrix must include all three modes, direct URLs/APIs, stale caches, publish/rollback and mode changes during order/payment/enquiry requests. Owned invoices/status, already approved project-payment links, completed records and active project delivery access require explicit exceptions; exceptions never permit a new inactive commerce/lead flow. Mode publication and content revision versions must invalidate only affected cache/sitemap/SEO resources; inactive code/data/media must not be globally preloaded.

Performance evidence must identify production build, device/network conditions, representative routes and sample stability for each mode. LCP/INP/CLS targets apply as stated in clause 5.6; mobile Lighthouse is a representative lab target and does not prove INP alone. Capture interaction evidence separately. Exceptions must state cause, measured impact and remediation/acceptance decision. API pagination/indexing and payload/request/bundle budgets are implementation gates, not work postponed to MT-7.2.

Reset must preview preserved and removed records/objects by domain, capture a verified automatic backup and recovery reference, revalidate the preview/permissions/recent authentication at execution, require typed reset-level confirmation, and retain a reset audit outside the cleared scope. A changed dependency set, unavailable backup, unsafe outstanding payment/stock state or unresolved retention rule blocks execution. Database/object deletion and retry/recovery semantics must be consistent. Isolated fixtures test all levels and restored bootstrap; live production reset/cutover remains H-01/H-03. The backup implementation gate precedes reset, even though schema classification is designed earlier.

## Checkpoint verification and boundaries

`docs/REQUIREMENTS_ADDENDUM_v1.1_VERIFICATION.json` records input preservation, completed-block equality, acyclic dependency order, complete clause/point mappings, documentation-only Git paths, Markdown/DOCX content parity, one DOCX generation and every rendered page's QA. The existing renderer/style is reused without altering tooling. No application code, schema manifest, migrations, dependencies, runtime, tests, source repository or historical evidence changes in this checkpoint. Application tests/builds are not applicable to unchanged code and are not represented as newly passed.

Registry changes only add scope/traceability pointers and version the local documentation; universal alias definitions and the loaded command behavior remain unchanged. Original source fingerprint checks and clean intended main/origin/live-main synchronization remain completion gates. Stop before MT-2.8; its implementation requires the next applicable user command.
