# Documents, Payments, Legal and User Manual Reconciliation

Date: 2026-09-02

## Authority and execution boundary

The approved source is `docs/PROJECT_REQUIREMENTS_DOCUMENTS_PAYMENTS_LEGAL_MANUAL_v1.0.md`, preserved verbatim at 65,000 bytes with SHA-256 `b682fed17cf6d955c2df0cac8eb081b0b641602e2b7391f3137c2e979e69b1a1`. It is binding alongside the canonical Goal, Preferences, requirements addendum v1.1, unified Admin/Google requirement and Team Member/session requirement.

This run performs documentation and structural reconciliation only. It does not implement MT-2.10 or any runtime feature, reopen completed checkpoints, send external messages, activate providers, migrate private data, change production or modify the protected sources. Source of Truth and roadmap advance from v1.8 to v1.9. Existing point IDs/titles remain stable; only MT-2.20 and MT-7.6 are added Pending. The live position remains 15/56 complete with `MT-2.10 - Stocktake and cycle-count services` first Pending.

## Verified current foundations and gaps

- Target `SalesOperations` already owns exact Invoice/sale/return transactions and persists Invoice-time business, outlet, customer, salesperson and Warranty snapshots. It does not yet own optional customer-email snapshots, POS tender allocations or document delivery.
- Target Warranty services preserve sale-time clauses, eligibility, claim lifecycle and historical snapshots. They do not yet provide the consolidated Warranty Claim Receipt delivery workflow.
- Target Website commerce owns fixed COD, JazzCash, Easypaisa and hosted-card provider contracts with disabled-provider safety. These remain the only Website checkout channels; POS destination/split-tender work is a separate future authority.
- Target Gmail uses Laravel-owned OAuth 2.0, the Gmail API and exact `gmail.send`; tokens remain encrypted/server-side and the canonical business profile owns `mobiST Technologies <mobisttech@gmail.com>`. MT-3.1 must reuse this boundary for attachment delivery and keep send permission separate from integration management.
- Target Admin permissions, outlet assignments, recent authentication and identity audit are established. Future document, template, destination and reconciliation permissions must remain explicit and server-enforced.
- The protected POS source provides reusable Invoice/Warranty preview, Thermal/A4/print, document settings and assisted WhatsApp concepts. Its browser/WhatsApp behavior cannot prove automatic PDF attachment or delivery and must be adapted with truthful states.
- The protected Website source provides the fixed four payment providers and generic legal-policy managed-page purpose, protected slugs, draft/publish and revisions. Generic infrastructure alone does not satisfy actual policy content/sign-off.
- The repository has no root application LICENSE. Backend Composer `license: MIT` is framework/package metadata, not verified licensing authority for all mobiST-owned code. Project ownership terms and distributed third-party notice obligations require an explicit owner decision and final audit; this reconciliation does not invent legal approval.

## Canonical decisions

1. Invoice finalization persists the authoritative transaction and then presents explicit Document Actions. It never automatically downloads or sends a document. Historical Invoice and Warranty records expose the same applicable actions without recreating business records.
2. One Laravel-owned renderer uses persisted snapshots for Preview, Thermal where applicable, A4, Print, Save PDF, Email attachment and assisted WhatsApp. Browser-only DOM-to-PDF cannot be the sole authority.
3. Optional POS customer email is a backward-compatible future extension. Send-time recipient override never mutates historical customer evidence. Gmail delivery uses the approved sender and API; failures remain truthful and cannot roll back completed sales/claims.
4. Invoice/Warranty Email and WhatsApp templates are separately managed, validated and revisioned. Unknown placeholders, header injection, HTML/script leakage and uncontrolled retry duplicates fail. Intentional resend is explicit and audited.
5. WhatsApp remains assisted without a separately approved Business Platform/API: normalize number, prepare message, generate/download the PDF only when manual attachment needs it, open the chat and tell the operator to attach. `Prepared`/`Opened in WhatsApp` are not `Sent`/`Delivered`.
6. POS Payment Method describes how money was tendered; outlet-scoped Payment Destination describes where it was received/expected to settle. Cash, Card, Mobile Wallet and Bank Transfer may be split across exact positive allocations that atomically equal the final Invoice payable amount.
7. Website checkout remains exactly COD, JazzCash, Easypaisa and Credit / Debit Card. It has no Bank Transfer, split tender, multi-account choice or visibility into internal POS destinations.
8. Sale, tender and settlement remain distinct. Provider fees/variance never rewrite sale or gross customer payment. PAN, CVV, PIN and stripe data are prohibited. Refund method/destination changes require explicit reason, actor, authority and audit.
9. Day Closing separates Cash drawer expectation/count/variance from non-cash destination expected receipts and later settlement/fee/variance. Dashboard uses one compact Payment Mix summary with drill-down, not one card per account.
10. Typed final policies must match actual product/data/payment/delivery/warranty/retention behavior, record version/effective date and truthful approval state, and preserve historical snapshots. Cookie policy is conditional on actual non-essential tracking.
11. The final manual is written only after the verified product exists, covers role-aware real workflows with safe current screenshots, and ships as content-matched Markdown, DOCX and PDF before FINAL-AUDIT.

## Requirement-to-roadmap traceability

| Approved section | Structural owner | Reconciliation |
|---|---|---|
| B-L: Invoice/Warranty documents, Email, WhatsApp, Print/PDF, security/failures | MT-3.1, MT-4.3, MT-4.4, MT-7.2, MT-7.5, FINAL-AUDIT | Canonical renderer, optional email snapshot, six templates, explicit actions, Gmail attachment, assisted WhatsApp, truthful/idempotent audit and negative paths added prospectively. |
| M: fixed Website payment model | MT-2.7 history; MT-5.3, MT-7.5, FINAL-AUDIT | Fixed COD/JazzCash/Easypaisa/Card contract made explicit; POS additions cannot leak into Website checkout. |
| N-T: POS methods/destinations, split tender, cash/change, settlement and refund traceability | MT-2.20, MT-4.2, MT-4.4, MT-7.2, MT-7.5 | New MT-2.20 added after MT-2.11 with exact atomic tender, security, destination, fee and refund contracts. |
| U-W: Day Closing, compact Payment Mix and reporting | MT-2.12, MT-3.1, MT-4.3, MT-4.6, MT-7.5 | MT-2.12 now depends on MT-2.20; Cash/non-cash reconciliation, destination drill-down and no split-sale double counting added. |
| X-AH: actual legal/policy documents and sign-off | MT-3.2, MT-4.4, MT-5.4, MT-7.2, MT-7.5, FINAL-AUDIT | Typed policies, actual data-flow consistency, owner/legal decisions, version/effective date, protected publication and footer routes added. |
| AI-AJ: application ownership and third-party notices | MT-7.2, MT-7.5, FINAL-AUDIT | Framework MIT metadata is explicitly non-authoritative; ownership choice and dependency/asset notice audit remain verified release work. |
| AK-AP: complete product manual | MT-7.6, FINAL-AUDIT | New MT-7.6 added after MT-7.5 for role-aware final screenshots and Markdown/DOCX/PDF parity; FINAL-AUDIT now depends on it. |
| AQ-BD: roadmap integration and final audit | Roadmap v1.9 | Affected existing points expanded without renumbering or reopening completed evidence. |
| BE-BI: reconciliation, recovery, counts and stop | This checkpoint | Canonical requirement preserved first; SOT/roadmap/DOCX/ledger/evidence reconciled; expected 15/56 position retained; MT-2.10 not started. |

## Dependency and lifecycle result

The MT-2 sequence is now MT-2.10 -> MT-2.11 -> MT-2.20 -> MT-2.12. MT-2.20 depends on MT-2.11 and MT-2.12 depends on MT-2.20. MT-7.6 follows MT-7.5, and FINAL-AUDIT depends on MT-7.6. These additions preserve every completed point and all historical checkpoint evidence.

The canonical roadmap Markdown is the structural source. Its same-basename DOCX is regenerated once after final edits, content-compared, rendered and visually inspected page by page. Detailed hashes, counts, source protection, render evidence and Git checkpoint are recorded in `STRUCTURAL_RECONCILIATION_VERIFICATION.json`.
