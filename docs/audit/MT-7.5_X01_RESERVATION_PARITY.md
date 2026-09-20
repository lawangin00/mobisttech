# MT-7.5 X01 Reservation Confirmation, Release and Reconciliation

Date: 2026-09-21

## Source route disposition

| Source route | Current authority | Disposition |
|---|---|---|
| `GET api/website/catalog/health` | `GET /api/v1/health` | Accepted shared API health; catalogue behavior is covered by completed W02. |
| `GET api/website/catalog/products` | `GET /api/v1/catalogue/products` | Accepted by W02 with current POS-authoritative stock and hold-aware availability. |
| `GET api/website/orders/health` | Shared Laravel API health and checkout contract | Separate duplicated POS order API retired; one Laravel authority owns order, reservation, payment and stock state. |
| `POST api/website/orders/reservations` | Website checkout service | Accepted: repricing, atomic order/item/reservation/allocation/payment creation and bounded stock hold. |
| `GET api/website/orders/{websiteOrderNumber}` | Owned Website order projection | Accepted: customer-scoped status reads from the shared order/payment/reservation authority. |
| `POST api/website/orders/{websiteOrderNumber}/confirm` | COD collection or verified provider callback | Accepted: reservation consumption and sale movement are idempotent and transactionally joined. |
| `POST api/website/orders/{websiteOrderNumber}/release` | Customer cancellation, payment failure and expiry services | Accepted: allocation release is idempotent; late verified payment remains reconciliation state without an unsafe sale. |
| `GET admin/orders` | Protected Admin order management | Retained under W03 for full UI parity; its reservation/payment state is supplied by the accepted X01 authority. |
| `GET admin/orders/report.csv` | Protected Admin order export | Retained under W03; X01 establishes consistent underlying joined state. |
| `GET admin/orders/{order}` | Protected Admin order detail | Retained under W03; reads shared order, payment and reservation records. |
| `PATCH admin/orders/{order}` | Protected Admin order transition | Retained under W03; no independent stock mutation may bypass X01 services. |

## Acceptance evidence

- `OrderPaymentTransactionsTest`: 15/15 PASS, 97 assertions. It covers exact replay, conflicting input hash, atomic partial-failure rollback and retry, COD confirmation, provider callback replay, payment failure, unknown outcome, expiry, late-paid reconciliation, archived-outlet barriers and bounded refund behavior.
- `InventoryConcurrencyTest::test_separate_mysql_connections_serialize_reserved_confirmation_versus_release`: 1/1 PASS, 88 assertions. Two independent MySQL connections contend for the same held unit; confirmation and release serialize to one terminal reservation state, zero live allocations and one consistent stock/sale result.
- Existing current-candidate Edge evidence exercises real customer checkout/cancellation against Laravel and Next.js and observes public stock move to zero while held and return after release. X01 adds no production UI change, so this Git-backed browser evidence remains applicable.
- Scoped Pint PASS. All fixtures use the isolated `mobisttech_test` schema; no protected source, production, provider or real customer data was touched.

## Decision

X01 is **Complete**. The duplicated source reservation bridge is replaced by the shared Laravel MySQL transaction authority with verified replay/hash conflict handling, atomic retry, expiry, confirmation-versus-release serialization and joined order-stock-payment reconciliation. W03 retains its separate Admin/customer order UI and export acceptance without reopening X01.
