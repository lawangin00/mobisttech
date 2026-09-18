# MT-5.4 Verification

- Point: MT-5.4 - Dynamic public content and digital solutions.
- Managed public CMS content, published typed legal policies, Digital Service catalogue/detail/package/add-on presentation, public enquiry flow, case-study content and reusable Software Product routes are implemented through the existing authoritative backend publication/services models.
- Software overview, Privacy, Terms, FAQ, Releases and version-detail routes render only published content; current-version and historical release presentation remain associated with the intended Software Product.
- Common/digital/commerce discovery is capability-aware. The dedicated acceptance verifies commerce-only pruning of Digital Service discovery/enquiry/case-study routes while common legal content and published Software history remain accessible.
- Published CMS/policy/service/software payloads are server-authoritative; private drafts are absent from public output. The sitemap includes the published Digital Service, managed case-study, policy and Software release routes used by acceptance.
- Dedicated post-remediation MT-5.4 production Playwright: 2/2 functional tests PASS. The same optimized-production run also executed the representative performance acceptance 1/1 PASS.
- Settled mobile performance at 390x844 meets the unchanged MT-5.1 targets: managed page LCP 1,360 ms / CLS 0 / interaction 16 ms; Digital Service detail LCP 1,240 ms / CLS 0; Software overview LCP 1,240 ms / CLS 0. Full diagnostic/remediation evidence is in `docs/website/MT-5.4_PERFORMANCE.md`.
- Full Website production regression before the final request-deduplication optimization was 8/8 PASS across MT-5.1 through MT-5.4. The final optimization touched only the three MT-5.4 dynamic route loaders, and all three changed route families were re-exercised post-change in the dedicated functional/performance production run.
- Clean affected backend/CMS/Digital regression remains PASS at 14 tests / 358 assertions. Clean full backend regression PASS: 249 tests / 7,591 assertions. No backend code changed after that full PASS.
- Default full Playwright regression PASS: 9/9. No POS/Admin/default-browser code changed after that PASS.
- Final Website gates: typecheck PASS; lint PASS; optimized production build PASS through the post-change Playwright web-server build. Composer strict validation/platform requirements, scoped Pint and git diff gates PASS from the final regression checkpoint; the final remediation changed Website TypeScript only.
- Exact post-acceptance synthetic residue is zero for the MT-5.4 Digital Service, managed pages, Privacy policy, Software Product, browser lead and MT-5.1 product fixtures.
- The earlier cold and pre-remediation performance failures were not relabeled as PASS and no acceptance threshold was weakened. Request duplication was fixed in product code and the unchanged targets then passed.
- No unresolved MT-5.4 production or acceptance defect remains.
