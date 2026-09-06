# Software Product Publishing Requirement Reconciliation

Date: 2026-09-06
Requirement: `docs/PROJECT_REQUIREMENTS_SOFTWARE_PRODUCT_PUBLISHING_v1.0.md`
Requirement SHA-256: `8ecf91807c2d0e838d1e2daf7ccfc130c522159e42ee5cef7d57d60c4276c452`

## Decision

The existing roadmap already contains the correct CMS, Admin, public-content, legal-policy, API, audit and final-acceptance stages. The new requirement therefore extends existing point scopes instead of adding or renumbering a roadmap point.

Live implementation position remains unchanged at 16/56 complete with `MT-2.11 - Inter-outlet stock transfer services` next.

## Requirement-to-roadmap map

- Backend software content model, template, policies, FAQs, releases and revision identity -> MT-3.2.
- Public versioned software/content API -> MT-3.4.
- Admin Software list, New Software template, editing, preview, releases and publishing -> MT-4.4.
- Public `/software/{slug}` Overview/Privacy/Terms/FAQ/Releases routes -> MT-5.4.
- Security, policy accuracy, release disclosure and performance audit -> MT-7.2.
- End-to-end create/publish/update/rollback acceptance -> MT-7.5.
- Administrator operating instructions -> MT-7.6.
- Independent closure -> FINAL-AUDIT.
## mobiST POS seed/reference set

The first planned software entry is `mobiST POS` with canonical slug `mobist-pos`.

Its factual seed/reference documents are preserved under `docs/reference/mobiST POS-IMS/`:

- `SOFTWARE_OVERVIEW.md` SHA-256 `6667c6163dafcce8df802c50822142d733c3fb09b88f5b01fb0543a28e958a7d`
- `PRIVACY_POLICY.md` SHA-256 `9fe1b40854a793de8d34751ac83038bf96e7961c69f8acf710755f677197533c`
- `TERMS_OF_SERVICE.md` SHA-256 `65ea5fec790b3ec4312118c1dfddf07210fe3cf88c3fbc54f8ba9c8861ca964c`
- `FAQ.md` SHA-256 `8f795afae2eb36909d003344cd56be5d77eaa287493f1fe3c39e4de494b5239c`

These files are not the Website database/CMS source of truth. During MT-3.2/MT-4.4 they are reconciled into the managed software record and reviewed before publication.

## Boundaries

This structural reconciliation does not implement Website routes, change the live domain, publish content, call Google/provider APIs, migrate private data or advance MT-2.11.

H-01 remains authoritative for production domain/DNS/TLS activation. The planned route family becomes live only after the domain/deployment gate is separately authorized.