# MT-2.6 Warranty and Claim Migration

This checkpoint implements the internal warranty and claim authority required by `MT-2.6 - Warranty and claim migration`. It preserves the verified P05 source behavior while using the shared Laravel/MySQL authority established by earlier points. It does not expose a route or user interface, render warranty documents, import private source data, collect or refund money, or begin MT-2.7.

## Source traceability and decisions

The pinned POS source at `c61e47394e7b3db8a49cfe443c85b63835febc9b` supplies the P05 warranty/claim baseline: warranty durations, versioned clauses, claim identifiers and lifecycle, sale-time warranty evidence, outlet permissions and historical claim output. The source inventory records 15 P05 files and six routes. This implementation:

- reuses the clause validation and immutable snapshot approach;
- adapts lifecycle work to explicit service transactions, idempotency and append-only event snapshots;
- migrates source-qualified claim, sale, invoice, product, unit and actor relationships through strict mapping;
- preserves historical output data in a stable service payload for later MT-3.1 document rendering and MT-4.3 interfaces;
- keeps paid repair jobs separate for MT-2.16.

No original repository, database, runtime service or remote was changed or executed. Only synthetic rows were used.

## Warranty clauses and sale-time evidence

`WarrantyClauses` normalizes at most 20 ordered, unique, plain-text clauses. Publication requires `config.documents.manage`, writes a versioned `pos.warranty_clauses` configuration revision, records audit and idempotency evidence, and returns a canonical version and SHA-256 digest. The source-compatible three-clause Urdu default is used until a revision is published.

`SalesOperations` stores the current clause snapshot on each invoice. Each sale line already stores its product warranty type, duration and unit. Claim eligibility therefore uses the immutable sale-time line and invoice snapshots even if the current product or published clauses later change. The legacy product fallback is used only when an imported historical sale lacks a warranty snapshot and is marked explicitly in the claim snapshot.

Warranty arithmetic is UTC and calendar-aware: unit `0` means days, `1` means months without overflow, and `2` means years without overflow. The exact expiry instant is inclusive; the first later instant is expired. `no_warranty` and invalid or non-positive durations are rejected for new claims.

## Claim authority and integrity

`ClaimOperations` requires fresh `shop.claims` authorization for the selected outlet. Claim creation locks the sale aggregate and validates invoice/product/outlet ownership. Returned quantity and all active claim quantities are subtracted from the original sold quantity. Serialized claims require the exact sold, unreturned stock occurrence and always claim quantity one. A generated active-unit key plus a MySQL unique constraint prevents two active jobs for the same physical occurrence.

The explicit lifecycle is:

`received -> diagnosing -> repaired -> ready_for_collection -> delivered -> closed`

`received`, `diagnosing`, `repaired` and `ready_for_collection` may also move to `rejected`; `rejected` and `closed` are terminal. Resolution and delivery timestamps are set from lifecycle transitions and never inferred from mutable master data. Each accepted transition appends a sequenced `claim_events` row containing actor identity, timestamp, canonical snapshot and digest. The compatibility activity log is rebuilt from those events. Changed idempotent replays and invalid transitions fail without partial effects.

The historical output payload retains claim and invoice identities, customer contact snapshot, sale-time business snapshot, product identity, unit code/color/condition, IMEIs, handler, lifecycle details, warranty/clauses and complete activity history. This is the backend parity contract consumed by later document and interface points; MT-2.6 does not claim an A4/Thermal renderer or routed UI.

## Strict migration

`ClaimImporter` accepts only the complete pinned source claim shape and the `claim_rehearsal` scope. All parent references use source-qualified maps. It verifies invoice/sale/product/outlet ownership, serialized-unit membership, statuses, timestamps, quantities and active-claim limits before writing. Stable public IDs, claim numbers, historical operator text, lifecycle fields and event history are retained. A historical row whose sale relation is absent remains readable and transitionable with an explicit `unresolved_historical` eligibility marker; it cannot be used to manufacture a new covered claim.

Identical replay returns the prior result. Changed replay, missing mappings, invalid row shapes and integrity conflicts quarantine without a partial claim. Actual private-data migration remains MT-7.1 work.

## Verification and boundaries

The disposable MySQL 8.4.11 schema has 81 tables, 990 columns, 134 foreign keys and 361 indexes with normalized SHA-256 `10bc28f7fee21948a7099fa1d3e0d9d67a5550ab1742d87150512e8934e1511e`. Empty rollback/reapply restores the same schema. Tests cover clause versions and permissions; current-master changes after sale; expiry boundaries; lifecycle, history and role denial; quantity/return bounds; exact physical-unit and IMEI output; importer replay/quarantine; and two independent connections competing for the last eligible quantity.

Fresh results and artifact hashes are recorded in `MT_2_6_VERIFICATION.json`. The protected source snapshot and source-schema mapping pass unchanged. The structural roadmap, Source of Truth, registry, approved Goal, Preferences and addendum do not change, so the roadmap DOCX is not regenerated.

MT-2.7 remains responsible for unified orders, reservations, payment collection and financial refund execution. MT-3.1 owns document rendering/communication, MT-4.3 owns the POS warranty interface, and MT-7.1 owns real migration rehearsal.
