# MT-2.9 - Supplier and procurement services

The shared Laravel backend now owns outlet-scoped supplier profiles, purchase orders, partial receiving, acquisition traceability and reorder recommendations. The [implementation ledger](../PROJECT_IMPLEMENTATION_STATUS.md) owns live progress. This checkpoint does not add HTTP routes or POS interfaces; those contracts remain assigned to MT-3.4 and MT-4.5. It also does not introduce accounts payable, a general ledger or broader ERP behavior.

## Source characterization and reuse

The protected POS source at `c61e47394e7b3db8a49cfe443c85b63835febc9b` contains acquisition-party snapshots and low-stock reporting, but no supplier profile or purchase-order workflow. The protected Website source at `04e7c49518f9f11f60c83ad44f9f4e2fd2539066` has no procurement authority. Read-only inspection therefore classifies supplier and purchase-order services as an approved addendum extension while preserving the verified acquisition and stock semantics already migrated under MT-2.4.

`StockReceiptWriter` extracts the internal acquisition write from `InventoryOperations` so standard acquisitions, correction units and purchase-order receipts use one stock authority. Procurement receipts retain the managed acquisition-source option, write normal stock acquisitions and units, and add immutable `acquisition_source_references` keyed by the receipt-line public identifier. Existing stock counters, unit identity, option usage, movements and purchase-price updates remain authoritative.

## Supplier and purchase-order contracts

`SupplierProcurement` is the internal application service. Every operation refreshes the Admin actor, verifies the server-owned outlet and requires `shop.procurement`. Strict input allowlists reject caller-supplied identities, state and stock counters. Supplier changes and reorder policies use version preconditions. Every mutation uses a durable actor/operation/idempotency key inside the owning MySQL transaction.

Suppliers and contacts are outlet scoped. Purchase orders retain immutable supplier code, name and primary-contact snapshots so later profile edits do not rewrite history. Lines record positive exact-decimal ordered and planned landed unit costs. Order and receipt numbers come from locked document sequences. Purchase-order events retain ordered snapshots, actor/Role/outlet context and a SHA-256 digest.

Receiving locks the order and all selected lines, rejects cancelled or fully received orders, verifies each quantity against the remaining amount and creates all receipt, acquisition, stock and audit rows atomically. A multi-line failure rolls back the entire receipt. Replays return the committed response. Concurrent duplicate attempts cannot over-receive. Status moves from `ordered` to `partially_received` and then `received`; received quantities and acquisition quantities/costs reconcile exactly.

Cancellation is allowed only for ordered or partially received orders. It preserves previous receipts and acquisitions, records a separate reason and prevents further receiving. Supplier history joins the immutable order/receipt relationship to the resulting acquisitions without deriving ownership from mutable contact details.

## Reorder recommendations

Each active reorder policy is unique to an outlet/product pair and requires a target above its threshold. The bounded recommendation query uses the current stock quantity plus outstanding quantities on open purchase orders. It reports low or out-of-stock state and the quantity needed to reach the target after expected incoming stock. Outlet, active-state and status/date lookup paths are indexed. This projection supports procurement decisions; it does not place an order automatically or replace the existing inventory reports.

## Verification boundary

Fresh MySQL apply, rollback and reapply prove the additive schema. Focused tests cover supplier authorization, exact costs, immutable snapshots, partial/full receiving, cancellation, retry, rollback, acquisition history, reorder scoping and tracked-unit receipt. A real two-connection race proves duplicate receipt safety. Full backend regression and both production frontend builds pass. No protected source runtime/database, private business data, provider, external message or production system is used.
