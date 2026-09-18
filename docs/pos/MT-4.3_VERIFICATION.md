# MT-4.3 Verification

- Point: MT-4.3 - POS customer, warranty and reporting interfaces
- Scope verified: customer/invoice history; Prepare Sale -> Preview/output choice -> Finalize -> explicit Document Actions; A4/Thermal invoice preview/print/PDF; controlled Email and assisted WhatsApp; historical delivery actions; warranty claim receipt; claim intake/lifecycle/history; compact outlet reports, CSV export and Payment Mix destination drill-down.
- Authorization: existing POS shell permissions remain authoritative. Sales document-send visibility is projected from shop.documents.send; Invoices, Warranty, Claims and Reports retain their separate permissions. Warranty-only roles use historical receipt/document actions while ClaimOperations intake/lifecycle remains shop.claims scoped.
- Transaction/document semantics: finalization itself performs no automatic download or send. Print/Save PDF/Email/WhatsApp are explicit actions after finalization. CanonicalDocuments remains authoritative for historical snapshots, A4/Thermal behavior, Gmail failure/retry, idempotency/intentional resend and truthful WhatsApp prepared/opened state.
- Warranty semantics: Warranty Claim Receipt is A4-only; thermal warranty rendering remains rejected by CanonicalDocuments. Claim eligibility/lifecycle remains ClaimOperations-owned and sale-time warranty snapshots remain authoritative.
- Reporting semantics: OperationalReports remains authoritative. Payment Mix keeps POS tenders and Website payments separate, avoids destination-card clutter by default and exposes destination drill-down plus CSV export.
- Focused HTTP/permission acceptance: 3 tests / 89 assertions PASS.
- Focused browser acceptance: 1/1 PASS, including real role navigation, no-auto-action assertions and 390px responsive checks.
- Affected regression: 17 tests / 298 assertions PASS.
- Full backend regression: 235 tests / 7288 assertions PASS.
- Full Playwright suite: 6/6 PASS.
- Build/style gates: backend production TypeScript/Vite build PASS; backend TypeScript typecheck PASS; Composer validate --strict PASS; Composer platform requirements PASS; changed PHP/seed/test files scoped Pint-clean; git diff check PASS.
- Browser-recovery notes: remaining acceptance failures were isolated to Playwright selector/navigation synchronization (hidden option, desktop link at mobile width, persistent details menu state). No production permission, transaction, delivery, claim or report rule was weakened.
- No schema migration was introduced by MT-4.3.
