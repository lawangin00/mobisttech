# MT-7.5 P06 Reporting, Customer History and Documents Parity

Date: 2026-09-21

## Source route disposition

The source inventory assigns 36 routes to P06. All are disposed as follows:

| Source route group | Count | Current disposition |
|---|---:|---|
| Admin and Super Admin dashboard/document/report presentation read, preview, publish and rollback routes | 16 | Unified protected Platform administration. P06 consumes the published document/report settings; P07 retains presentation-editor parity. |
| Admin and Super Admin dashboard, account/password/photo routes | 10 | Unified Admin shell/account authority; P01 retains identity/profile parity. |
| Admin and Super Admin report pages/data routes | 4 | `/internal/admin/pos/workspace/reports` and protected operational report/CSV APIs. |
| `api/reports/category-data`, `api/reports/data`, `api/shop/profit-summary` | 3 | Consolidated role/outlet/date-scoped report with exact sales, profit, returns, refunds, payment/settlement, activity and category performance/inventory/claim projections. |
| Shop dashboard, invoice CNIC index and account route | 3 | Protected POS shell, bounded invoice/customer history and unified account page. |

## Acceptance evidence

- Current joined verification: 24/24 PASS, 456 assertions across canonical documents, customer/reporting HTTP, cash sessions, POS payments and operations interfaces.
- Invoice A4 and Thermal 80mm preview/print/PDF use immutable sale-time customer/business/product snapshots. Warranty receipts remain A4-only. Finalization never auto-downloads, prints or sends.
- Email uses validated templates/placeholders, explicit confirmation, idempotency, intentional resend and truthful provider failures. Assisted WhatsApp prepares the PDF and link, and opening it is never recorded as sent. Role/outlet denial cannot mutate invoices.
- Reports validate inclusive date bounds, count invoices once, separate sales/returns/refunds, POS tenders, Website payments, fees, expected/net settlement and variance, and export the same scoped state to CSV.
- The consolidated report now restores the missing category view: date-scoped units sold, net sales and profit plus current product/stock/default-cost and claim counts per category. The real React Reports workspace renders this as a responsive table.
- Current production TypeScript/Vite build PASS (588 modules). Existing current-candidate real Edge evidence covers explicit invoice/warranty document actions, delivery states, report filters/CSV/payment drill-down/mobile containment and a fresh sale/return/refund/payment report journey. Authentic Gmail delivery remains external unverified and is not represented as sent evidence.

## Decision

P06 is **Complete**. All 36 source routes are disposed, with overlapping identity and presentation editors owned by P01/P07. The P06 functional gate for scoped history, category/profit reporting, CSV, day-close/payment mix, immutable A4/Thermal documents and safe delivery is accepted.
