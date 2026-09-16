# Inter-outlet stock transfer services

MT-2.11 implements backend-domain stock transfers between authorized mobiST outlets. HTTP publication remains assigned to MT-3.4 and POS stock-control interfaces remain assigned to MT-4.5.

## State and authorization

A transfer is created as `draft`, dispatched to `in_transit`, may become `partially_received`, and closes as `received`, `rejected`, or completed `partially_received` when both outcomes exist. Every mutation uses durable idempotency and a transfer version precondition.

`shop.transfers.dispatch` is required in the source outlet for create/dispatch access. `shop.transfers.receive` is required in the destination outlet for receiving/rejection. Details are visible only to an actor authorized on one of those two scoped sides.

Source and destination must be different active outlets. Each line binds one active source product to one active destination product with the same server-derived stock-definition fingerprint. Active transfer custody prevents those product definitions from being changed underneath the workflow.

## Dispatch custody

Dispatch does not pretend that in-transit stock has already changed outlet ownership. It creates `inventory_custody_holds`, so quantity and serialized stock remain physically recorded at the source but are unavailable to sales, reservation, adjustment, IMEI editing, archive, or competing transfer consumption.

Quantity lines reserve an exact aggregate quantity. Serialized lines reserve exact source `stock_units`, require complete active IMEI identity, and persist immutable SHA-256 source-unit snapshots before transit.
## Receipt and rejection

Receiving quantity stock creates balanced `transfer_out` and `transfer_in` stock movements only for the accepted quantity. Rejected quantity releases its remaining source custody without fabricating an outbound/inbound movement.

Receiving a serialized unit retires the source occurrence as `transferred_out`, moves its historical IMEI rows to that state, removes the source active IMEI claim, creates a destination `in_stock` successor occurrence, recreates the same active IMEI identity at the destination, and records forward-only `stock_unit_lineage` with reason `transfer`.

Rejecting a serialized unit releases its exact custody hold while preserving the original source occurrence and active IMEI claims unchanged. Partial receipts can resolve any safe subset; unresolved custody remains held until a later receipt/rejection.

Every receipt stores immutable receipt and line snapshots with SHA-256 digests. A stale version, changed product definition, changed serialized identity, duplicate resolution, insufficient availability, or concurrent competing receipt fails atomically.

## Boundaries

MT-2.11 does not publish transfer HTTP routes, build POS transfer screens, migrate private source data, contact an external provider, send messages, deploy production infrastructure, or perform a production transfer. The protected legacy POS and Website repositories remain read-only evidence.

The canonical stock ledger remains the only quantity authority. Transfer tables record workflow/custody/history and do not introduce a second inventory engine.