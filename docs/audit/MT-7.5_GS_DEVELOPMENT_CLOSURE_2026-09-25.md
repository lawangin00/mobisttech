# MT-7.5 24/G-S Software Publishing Development Closure

Date: 25-Sep-2026 PKT  
Stable gate: `24/G-S`  
Status: **DONE DEVELOPMENT**  
Requirement: `docs/PROJECT_REQUIREMENTS_SOFTWARE_PRODUCT_PUBLISHING_v1.0.md`

## Accepted finite scope

The approved reusable Software Product publishing requirement is accepted for development without publishing any real mobiST product or inventing owner-approved release facts.

- Protected Admin starts from an empty `New Software` template and keeps create/edit separate from publish authority.
- Private route-family preview covers Overview, Privacy, Terms, FAQ and Releases before publication; draft/private content remains absent from public routes.
- A second synthetic product proves template reuse and product isolation without a new hard-coded page tree.
- Real published Next routes cover Overview, Privacy, Terms, FAQ, Releases and version detail; unknown/draft/archived routes fail closed.
- Media, SEO/social metadata, sitemap state, canonical slug redirect, revision publication/rollback and archive behavior are accepted from the existing W06 real Admin -> Next evidence.
- Published release history remains immutable and current-version updates preserve earlier records.
- `WebsiteCmsTest::test_software_template_preserves_private_drafts_releases_routes_and_history` already proves the material documentation-impact gate cannot be bypassed.
- The new focused `backend/tests/browser-website/g-s-software-performance.spec.ts` gives route-specific 390x844 responsive/LCP/CLS/event-budget acceptance in hybrid, digital_only and commerce_only. The terminal Playwright artifact `backend/storage/framework/testing/playwright-website/.last-run.json` reports `status=passed` and no failed tests. Backend TypeScript typecheck and `git diff --check` pass.

## Reused evidence

- `docs/audit/MT-7.5_W06_NEW_SOFTWARE_DRAFT_PREVIEW_2026-09-24.md`
- `docs/audit/MT-7.5_W06_SECOND_SOFTWARE_PUBLIC_JOURNEY_2026-09-24.md`
- `docs/audit/MT-7.5_W06_SOFTWARE_SEO_SITEMAP_JOIN_2026-09-24.md`
- `docs/audit/MT-7.5_W06_DEVELOPMENT_CLOSURE_2026-09-25.md`
- `docs/audit/MT-7.5_ACCEPTANCE.md`
- `backend/tests/Feature/WebsiteCmsTest.php`
- `backend/tests/Feature/PlatformAdministrationInterfaceTest.php`
- `backend/tests/browser-website/dynamic-content.spec.ts`

No unchanged accepted W06 suite was rerun.

## Loop-Guard / orchestration record

The first four G-S performance launches did not yield product acceptance: Windows `npx` executable resolution, over-escaped seeder class syntax, and two transient cooperative lock preflight races were retained in the implementation ledger. LOOP_GUARD switched execution to an independently equivalent two-step atomic reservation: reserve the canonical test-DB lock with an owned marker, require a stable-free 13000/18080 window, run Playwright directly, then verify process/port exit and release only the matching owned reservation. That materially different strategy produced the terminal PASS and no lock/process was force-removed.

## Holds not converted to PASS

Real mobiST POS factual reconciliation/publication, real version/date/download claims and owner-approved product content remain `25/G-R` / D08 scope. Final release privacy/terms/notices/license/ownership and owner/legal approval remain `26/G-L`. No live domain/provider/production publication is authorized here. Q01 remains the final exact-candidate joined milestone.

**Result: 24/G-S DONE DEVELOPMENT. MT-7.5 = 23/27 DONE, 4 OPEN. Next stable gate: 25/G-R.**
