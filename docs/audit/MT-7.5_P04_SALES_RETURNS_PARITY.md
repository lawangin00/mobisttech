# MT-7.5 P04 POS Sales, Invoices and Returns Parity

Date: 2026-09-21

## Source route disposition

| Source route | Current authority | Disposition |
|---|---|---|
| `GET api/invoice/{id}/details` | `GET /internal/admin/pos/invoices/{invoice}` | Accepted outlet/role-scoped invoice, tenders, refunds and immutable sale-line identity. |
| `POST api/pos/invoice/store` | `POST /internal/admin/pos/sales` | Accepted server price, exact discount, numbering, snapshots, idempotency and joined stock/payment transaction. |
| `GET api/pos/product/{productId}/imeis` | Protected POS catalogue/unit projection | Accepted with P03 serialized-stock evidence; sale consumes the selected sellable outlet unit. |
| `GET api/pos/products/presentation` | `GET /internal/admin/pos/catalogue` | Accepted protected React catalogue and server-authoritative quote/finalize flow. |
| `GET shop/invoices` | `/internal/admin/pos/workspace/invoices` and reporting API | Accepted bounded history search/pagination, customer grouping and cross-outlet denial. |
| `GET shop/pos` | `/internal/admin/pos/workspace/sales` | Accepted by the real fresh-owner sale, payment, return and refund journey. |
| `GET super-admin/business-profile` | Canonical Business Profile administration | Accepted unified Admin authority and immutable sale-time business/outlet snapshot. |
| `POST super-admin/business-profile` | Protected canonical Business Profile update | Accepted; later profile/product edits do not rewrite invoice snapshots. |

## Acceptance evidence

- Current joined backend regression: 17/17 PASS, 378 assertions across `SalesOperationsTest`, `SalesMigrationTest`, `PosTransactionInterfaceTest` and `PosCustomerReportingInterfaceTest`.
- Server pricing, cent-precise discount/profit, split tenders/change, returns, sellable stock restoration and bounded refunds are verified. Changed replay payloads, over-return, unsafe card fields, bad historical arithmetic/parents, archived outlet, permission and cross-outlet access are rejected.
- Protected invoice detail and warranty intake now read immutable `sale-line.v1` product names instead of later catalogue names. The focused HTTP test renames a product after sale and confirms the original line name and `canonical-business-at-sale.v2` outlet identity.
- Existing current-candidate real Edge evidence creates a fresh sale/payment, accepts a sellable return/refund, observes stock `3 -> 2 -> 3`, and reconciles invoice, return, refund and payment report totals. Protected document HTTP/browser evidence covers invoice preview/print/PDF bindings; P06 retains full delivery/report acceptance.

## Decision

P04 is **Complete**. All eight source routes are disposed and the target preserves authoritative arithmetic, invoice/customer/business/product history, payment/stock effects, returns, refunds, permissions and outlet isolation.
