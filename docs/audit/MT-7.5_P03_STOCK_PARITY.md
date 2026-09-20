# MT-7.5 P03 acquisition and stock parity

Date: 21-Sep-2026 PKT  
Scope: P03 only; protected legacy POS sources remain read-only.

## Source route disposition

| Legacy route contract | Current disposition |
|---|---|
| Inventory index and price catalogue | Protected outlet-scoped React Inventory workspace and `/internal/admin/pos/catalogue?mode=inventory`; current prices and stock come from shared MySQL authority. |
| Product create/update/delete | Consolidated into protected product definition save and zero-stock archive rules; P02 owns master-data definition acceptance while P03 retains stock/history guards. |
| Product restock and acquisition history | Protected acquire transaction plus bounded acquisition history in the Inventory workspace. History exposes source type, quantity, exact unit cost and time but omits seller CNIC/phone/address and private document paths. |
| Acquisition CNIC document | Reused through private `AcquisitionDocuments` storage/read authorization; no public path or client-selected object key. |
| Stock adjustment | Protected, idempotent `InventoryOperations::adjust`, with quantity/serialized rules, hold checks and durable movement history. |
| Device configuration | Protected versioned unit attributes backed by managed condition/PTA/color state. |
| IMEI list/update | Current in-stock unit list and protected versioned IMEI write preserve every required slot, global active uniqueness and historical occurrences. |
| Stock movement history | Bounded protected Inventory history now shows type, signed delta and before/after total for the newest 30 movements. Internal references and private acquisition identity are not exposed. |

All 12 P03 source routes in `SOURCE_SYMBOL_INVENTORY.json` are represented by these consolidated target contracts. The fresh-business decision excludes importing real legacy business rows; source-qualified synthetic migration/quarantine remains the safety evidence.

## Acceptance evidence

- `StockMigrationTest` and `InventoryConcurrencyTest`: **22/22 PASS (1,597 assertions)** on a freshly migrated isolated MySQL schema. This covers acquisition/movement/IMEI mapping and quarantine, historical/current IMEI identity, rollback, sale/reservation/return arbitration, stocktake, procurement and transfer concurrency.
- `PosTransactionInterfaceTest` focused current HTTP contract: **1/1 PASS (34 assertions)**, including protected acquisition/movement history, exact totals and private seller/document-field suppression.
- Current protected production frontend: TypeScript and Vite build PASS; scoped Laravel Pint PASS.
- Actual Microsoft Edge fresh-target journey: **1/1 PASS (39.5s)**. A newly provisioned owner was denied Inventory before outlet assignment, created/configured the first outlet, created a zero-stock product, received three units, observed protected acquisition/restock history, sold one unit, accepted a sellable return and observed authoritative `restock +3`, `sale -1` and `customer_return +1` transitions returning stock to three. The same joined journey retained customer/payment/warranty/report behavior and completed exact fixture cleanup.
- Existing unchanged real-browser evidence for a freshly created tracked phone verifies physical-unit acquisition, required IMEI entry, lookup and public-private separation. Current HTTP/concurrency evidence covers returned serialized successor history and active-IMEI re-entry without duplicate claims. Cross-outlet and missing-permission requests remain denied by existing current target tests.

## Decision

P03 acquisition and stock is **Complete**. Quantities, physical units, IMEI slots/history, acquisition evidence, stock movements, outlet/role scope, rollback and concurrency satisfy the family gate. Later P04/P05/P06/X01 journeys retain their own sales, claims, documents and reservation acceptance without reopening P03.
