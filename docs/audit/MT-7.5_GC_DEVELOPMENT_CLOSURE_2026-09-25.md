# MT-7.5 23/G-C Development Closure — Consolidated Documents, Payments & Notices

Status: **DONE DEVELOPMENT** on 25-Sep-2026 PKT. MT-7.5 advances to **22/27 DONE, 5 OPEN**; next stable gate is **24/G-S**.

This closes the target-development/evidence portion of the approved consolidated Documents/Payments/Legal requirement. It does **not** claim authentic Gmail/provider execution, final owner/legal approval, a project-level LICENSE decision, production policy wording, or the final user manual. Those remain under H-02, 26/G-L, MT-7.6 and 27/Q01 as applicable.

## Finite reconciliation

| Requirement family | Current target disposition / evidence |
|---|---|
| B–L Invoice/Warranty document delivery | ACCEPTED from P06/current document evidence: one snapshot-driven document boundary; Invoice A4/Thermal and Warranty A4; explicit Preview/Print/Save PDF/Send via Email/Send via WhatsApp; no auto-download/send on finalization; historical actions; safe templates/placeholders; idempotent/truthful delivery state and assisted WhatsApp. Current P06 joined backend **24/24 PASS (456 assertions)** plus accepted real Edge document/report evidence reused. Authentic Gmail send stays external HOLD. |
| M Website fixed payment model | ACCEPTED: Website checkout remains exactly COD/JazzCash/Easypaisa/Card; no Bank Transfer, split tender or internal POS destination selection. W04/OrderPayment current accepted evidence reused; authentic hosted provider activation/refund/settlement remains H-02. |
| N–W POS tenders/reconciliation/reporting | ACCEPTED from MT-4.2/4.6/P06/W04 evidence: explicit method vs destination, outlet-scoped destinations, exact split tender, cash/change, sale/tender/settlement separation, no PAN/CVV/PIN/stripe storage, refund override/audit, Day Closing cash vs noncash expected receipt, compact Payment Mix/drill-down and scoped reporting/CSV. Existing focused/Edge evidence reused unchanged. |
| X–AH legal/policy administration | ACCEPTED DEVELOPMENT: all required/conditional policy types have protected slugs, draft/review/approval/effective-date/publication/rollback and footer/public route architecture. Genuine gap fixed: protected Platform Admin can now record real owner-approval/factual-review/applicability/unresolved-decision/review metadata rather than only creating unreviewed drafts. Focused PHP **1/1 PASS (67 assertions)**; protected 390px Admin Edge **1/1 PASS (35.6s)**; public footer/routes 390px Edge **1/1 PASS (36.5s)** for seven applicable synthetic policies while Cookie remains absent when not required. Synthetic approval is test evidence only, never production legal approval. |
| AI–AJ ownership/notices | ACCEPTED TECHNICAL POSTURE: `NOTICE.md` exists; dependency/license audit records exact lockfile posture and removes inherited Laravel MIT ambiguity. Root application LICENSE remains intentionally absent pending owner choice; actual license/owner/legal decision remains 26/G-L. |
| AK–AP final user manual | DEFERRED by approved requirement to **MT-7.6 after MT-7.5**, not a G-C development blocker. No assumption-based early manual is accepted. |
| AQ–BI structural/recovery integration | ACCEPTED from the already completed structural reconciliation: canonical requirement preserved/hash-tracked and registered in Source of Truth/roadmap/ledger; completed historical points are not reopened. |

## Current targeted validation

- `GCLegalPolicyMatrixTest`: **1/1 PASS (67 assertions)** after final scoped Pint.
- `g-c-policy-admin.spec.ts`: protected Microsoft Edge **1/1 PASS (35.6s)** at 390px; complete policy types plus truthful approval/review/applicability controls, responsive containment and logout cleanup.
- `g-c-policy-public.spec.ts`: public Microsoft Edge **1/1 PASS (36.5s)** at 390px; all seven applicable policy footer links/routes/version/effective date render; Cookie Policy is not fabricated; exact-owned policy cleanup PASS.
- Backend TypeScript and production Vite build PASS; public bundle budgets PASS.
- A broader historical `platform-administration.spec.ts` attempt redirected to login before reaching Platform Administration. It is retained as a non-G-C diagnostic/harness failure and is **not** relabeled PASS; G-C uses the narrower real protected route acceptance above. Full exact-candidate regression remains 27/Q01.

## Holds preserved

- Live Gmail OAuth consent/send is not exercised.
- Live JazzCash/Easypaisa/card payment, refund and settlement actions are not exercised.
- Final policy wording, real owner/legal approvals, root application license choice and release notice/legal sign-off remain **26/G-L**.
- Final role-aware Markdown/DOCX/PDF product manual and screenshots remain **MT-7.6**.
- Final exact-candidate clean checkout/full regression remains **27/Q01**.
