# MT-7.5 P05 POS Warranty and Claims Parity

Date: 2026-09-21

## Source route disposition

| Source route | Current authority | Disposition |
|---|---|---|
| `GET shop/claims` | `/internal/admin/pos/workspace/claims` and protected claims listing API | Accepted outlet-scoped claim history with bounded search/pagination and role checks. |
| `POST shop/claims` | `POST /internal/admin/pos/customer-reporting/claims` | Accepted sale-time eligibility, inclusive expiry, quantity/unit ownership, idempotency and active-claim exclusion. |
| `GET shop/claims/{claim}` | Protected claim detail/history API | Accepted immutable claim output plus ordered, actor-attributed hashed lifecycle events. |
| `PATCH shop/claims/{claim}` | Protected claim transition API | Accepted explicit transition graph, replay safety, version checks and terminal-state rejection. |
| `GET shop/warranty` | `/internal/admin/pos/workspace/warranty` | Accepted outlet-scoped warranty receipt/history UI with role isolation. |
| `GET shop/warranty/search` | Protected warranty-intake search | Accepted old-sale discovery with saved category/paging, immutable sale-line warranty data and no cross-outlet/role leakage. |

## Acceptance evidence

- Current joined backend verification: 17/17 PASS, 282 assertions across `WarrantyClausesTest`, `ClaimOperationsTest`, `PosCustomerReportingInterfaceTest` and `DocumentReportingServicesTest`.
- Warranty clauses are versioned, validated, permission-scoped and stored on each invoice. Later clause/product changes cannot rewrite sale-time warranty eligibility.
- Claim coverage uses the exact inclusive expiry boundary, accepted-return and active-claim quantity limits, and the exact unreturned serialized sale occurrence. Invalid transitions, terminal updates, archived outlets, wrong roles and cross-outlet searches are rejected.
- Claim lifecycle events are append-only, sequenced and snapshot-hashed; replay returns the same result. Existing MySQL contention evidence proves only one active claim can acquire the last eligible quantity/unit.
- Warranty Claim Receipt is generated from canonical historical snapshots, is A4-only by contract, and requires an explicit preview/print/PDF action. Existing current-candidate real Edge fresh-owner evidence opens a claim from an actual sale, transitions it to diagnosing, previews the receipt with the claim number and observes warranty history in the real UI.

## Decision

P05 is **Complete**. All six source routes are disposed and expiry, sale-time clauses, claim intake/lifecycle, historical receipt output, concurrency, role and outlet boundaries are accepted. P06 retains broader document delivery and report-output acceptance.
