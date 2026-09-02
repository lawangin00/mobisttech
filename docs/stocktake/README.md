# MT-2.10 - Stocktake and cycle-count services

The shared Laravel backend now owns outlet-scoped full stocktake and selected-product cycle-count services for quantity and serialized/IMEI inventory. The [implementation ledger](../PROJECT_IMPLEMENTATION_STATUS.md) owns live progress. This checkpoint implements the backend domain only; HTTP publication remains assigned to MT-3.4 and the POS stock-control interface remains assigned to MT-4.5.

## Source and authority boundary

The approved addendum requires count sessions, expected-versus-counted variance, reason codes, approvals, recounts and auditable reconciliation without fabricated history. Earlier protected-source characterization found no stocktake/cycle-count workflow to migrate, so MT-2.10 is an approved additive extension over the existing target stock authority. No protected source runtime/database, private business data, provider or production system is used.

## Explicit count baseline

Every session is outlet-owned, server-numbered and either `full` or `cycle`. Full sessions select the complete active outlet catalogue server-side; cycle sessions require an explicit set of active outlet products. Session creation locks products and records, per line, the on-hand, held and available quantities, product version, capture time and the highest stock-movement ID already included in the baseline.

Serialized lines additionally retain immutable per-unit baseline rows with the stock-unit public identity/version, unit number and identifier snapshot digest. Counting reconciles the recorded baseline with every later authoritative movement for that product by movement ID. Reservations remain visible through held stock but do not falsely change physical on-hand quantity. A current stock snapshot must agree with the reconstructed movement baseline before a count is accepted.

## Counts, variances and recounts

`shop.stocktake` is required to start/read/count. Counting never mutates stock. Quantity lines accept an explicit non-negative counted quantity. Serialized lines accept only current, known stock-unit identities for the same product/outlet; duplicate, foreign, sold/retired or invented units are rejected. A physically found serialized unit that lacks authoritative inventory history must first enter through the normal acquisition/correction path and then be recounted.

Every accepted iteration is append-only in `stocktake_counts` with expected quantity, counted quantity, signed variance, actor, timestamp, immutable count snapshot and SHA-256 digest. Non-zero variance requires an approved reason code. Once all current line iterations are counted, the session becomes `submitted` automatically.

`shop.stocktake.approve` is required to request a recount or approve a submitted session. Recount requests preserve the previous count evidence in `stocktake_recounts`, advance only that line's iteration and return the session to counting. Earlier count rows are never overwritten.

## Approval and reconciliation

Approval is version-preconditioned and idempotent. It does not overwrite product counters with the physical count. Instead, each approved variance becomes an auditable `stocktake_adjustment` movement against the current stock state, so legitimate sale, restock and customer-return activity after the physical count remains intact.

A later physical/manual correction or another stocktake adjustment invalidates the older approval and requires recount. Negative quantity adjustments cannot consume held/unavailable stock. Missing serialized units can be adjusted out only while the exact counted unit remains current and unheld; its stock-unit and IMEI state becomes `adjusted_out`, its active global IMEI claims are removed, and the stock movement references the stocktake line. Positive serialized variance is never manufactured into history: it requires normal acquisition/correction evidence followed by recount.

Multi-line approval is one MySQL transaction. If any later line is stale, held, invalid or otherwise unsafe, all preceding stocktake adjustments and approval evidence roll back together.

## Concurrency and verification boundary

Real independent MySQL connections arbitrate stocktake approval against sale and reservation through the same product locks used by the existing stock authority. Exactly one conflicting removal/hold can win; negative stock, double adjustment and bypass of reservations are rejected. Focused tests also cover full/cycle scope, permission separation, cross-outlet access, duplicate/replayed counts, explicit movement baselines, reason codes, recount history, serialized identity, positive/negative quantity variance, stale approval and multi-line rollback.

The additive schema is verified on the isolated `mobisttech_test` MySQL 8.4 target with strict SQL and UTC. Empty-schema rollback/reapply is supported; rollback refuses to discard populated MT-2.10 business evidence or an `adjusted_out` IMEI state. The stocktake tables are classified as inventory for future guarded reset planning. No REST route or POS interface is exposed by this checkpoint.