# MT-2.5 - Sales, invoices and returns migration

This checkpoint establishes the shared backend authority for operational customers, POS sales, invoices and accepted returns. The implementation ledger owns live progress. It does not expose a public HTTP/UI surface, collect payment, create a refund, implement warranty claims or import private source data.

## Source traceability and reuse

The isolated POS source invoice workflow was characterized at pinned source commit `c61e47394e7b3db8a49cfe443c85b63835febc9b`. Its invoice/customer/business/warranty snapshots, sale quantity and cost fields, gross/discount/net/profit arithmetic, product locking, physical-unit selection, IMEI retirement and stock movement are adapted. The large controller transaction and direct model orchestration are refactored into one target service and the existing transactional stock authority.

The source contains a stock-only return-to-inventory operation but no complete routed sale return/refund authority. That operation is not treated as financial parity. The explicit target contract below preserves the original sale and invoice, appends accepted return lines and stock movements, and reports an exact refund amount due without creating a payment refund. MT-2.7 retains payment/refund ownership.

## Sale and customer authority

`SalesOperations::sell` is the single current sale authority. It requires a fresh `shop.sales` grant for the selected open/assigned outlet and owns the complete MySQL transaction. Request fields use a strict allowlist. Product/outlet identity, active state, price, purchase cost, invoice/document identity, salesperson and business snapshots are server selected. A product may occur once per request and products are locked in stable public-ID order.

Money uses canonical decimal strings and BCMath only. Gross is the sum of persisted unit sale price times quantity. A manual discount cannot exceed gross and requires a reason. Discount cents are allocated deterministically across sorted lines, with the final line taking the exact residual, so allocated line discounts and net totals reconcile to the invoice. The existing MT-2.8 monetary-adjustment primitive stores the immutable manual-discount reference; it remains a reference and never recalculates invoice totals.

Operational customers are independent from Website credentials. A caller may select an existing active customer by public ID, explicitly create a new operational record, or retain a guest snapshot on the invoice. Contact similarity never links or merges a Website user. Customer, business, salesperson, warranty-contract marker, product code/name and line financial values are historical snapshots; later changes do not recalculate an invoice.

Invoice numbers use the locked `document_sequences` row for namespace, outlet and business date. Sale and invoice public IDs are stable UUIDs. The existing `TransactionalStock::consumeSale` is invoked inside the same owning transaction, so invoice, sale, manual adjustment, unit/IMEI retirement, product counters, stock movement, audit, idempotency result and publication event commit or roll back together.

## Accepted return contract

`SalesOperations::acceptReturn` locks the invoice, each sale and each product in stable order. It accepts only original sale quantities and physical units belonging to that sale. `sales.returned_quantity` is the current cumulative bound; concurrent requests cannot accept more than the original quantity. The original invoice totals, sale cost, discount, profit and snapshots remain unchanged.

Each return line stores its original invoice/sale identity, quantity, unit price, allocated discount, net amount due, purchase amount, currency, complete canonical sale snapshot and SHA-256 digest. Partial quantity discounts use integer-cent proportional allocation; the final accepted quantity receives the exact remaining discount. This supplies compensating revenue/cost evidence without rewriting financial history.

Every accepted return reduces the net sold counter and appends a `customer_return` stock movement. A sellable return restores on-hand stock; damaged or quarantined dispositions record a zero-quantity movement and do not become available. Quantity stock uses cumulative returned quantity. A serialized return creates a new physical occurrence linked forward from the sold source through `stock_unit_lineage`; it never reopens or reparents the sold row. IMEI history is copied to the successor, and an active IMEI claim is restored only for a sellable successor. The lineage uniqueness and active-IMEI constraints arbitrate duplicate physical returns.

The return response states `refund_status=not_created`. No `payments`, `refunds`, provider references, cash-session entries or outbound messages are written. MT-2.7 must validate collected funds and execute/refuse the actual refund against these accepted return records.

## Schema and migration

The additive `2026_09_01_110000_add_sales_return_integrity` migration preserves the existing 80-table set and adds stable sale identity/version/returned-quantity fields plus immutable return-line financial/snapshot/successor fields. Composite invoice relationships prevent a return line from referencing a sale or return under another invoice. MySQL check constraints enforce sale/return money shape, cumulative quantity, accepted status, disposition and PKR currency. Historical rows remain protected by RESTRICT relationships.

`SalesImporter::import` supports only complete POS `invoices` and `sales` rows under an explicit `sales_rehearsal` run targeting the connected disposable database. It uses the frozen source column manifest, resolves outlet/admin/product/invoice parents through source-qualified identity mappings, converts declared timestamps to UTC and preserves signed historical profit. Exact invoice and line arithmetic and outlet ownership must reconcile before insert. Identical replay returns the prior target identity; changed input, unknown/missing fields, unresolved parents, arithmetic errors and collisions quarantine with a digest and no private row payload. It does not infer customer identity from historical invoice contact snapshots or replay stock movements as new sales.

## Verification and boundaries

Focused acceptance covers exact sale arithmetic and discount residuals, server price authority, explicit/guest customer behavior, idempotent replay, immutable invoice totals, bounded partial returns, no premature refund, forward serialized lineage, IMEI re-entry, strict source import, UTC dates, negative historical profit and quarantine. A real independent-connection race proves that two return requests for the same remaining quantity produce exactly one accepted return.

`tools/migration/verify_mysql_schema.php sales` verifies the disposable target schema is MySQL 8.4 on the owned loopback port with UTC/strict mode, the exact 80-table checkpoint set, all MT-2.5 columns, zero business rows and the normalized schema hash. Reproduction and exact final evidence are in `MT_2_5_VERIFICATION.json`.

MT-2.6 owns warranty/claim behavior and documents. MT-2.7 owns unified orders, reservations, payment collection and actual refunds. Interfaces, private-data rehearsal/cutover, providers, cash effects, reporting documents and production changes remain their later points. The approved Goal, Preferences, addendum, Source of Truth, registry and structural roadmap/DOCX are unchanged by this routine point.
