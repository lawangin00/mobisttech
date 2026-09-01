# MT-2.7 - Unified orders, reservations and payments

This checkpoint establishes one Laravel/MySQL authority for Website orders, stock reservations, COD collection, external-provider intents and verified callbacks, project milestone payments, and verified manual refunds. It does not expose REST/UI routes, import private source rows, activate a real payment provider, or perform production/cutover work.

## Reuse and transaction boundary

The implementation adapts the pinned Website order, checkout, COD, gateway callback and project-payment rules documented in the feature parity register. It retires the target need for Website-to-POS synchronization: `OrderTransactions` writes orders, reservation lines, payments and receipts in the shared schema, then calls `SalesOperations::finalizeReservedOrder` and `TransactionalStock` inside the same owning MySQL transaction. The existing invoice snapshots, identifiers, stock selection, IMEI retirement, stock movements and publication events remain the shared sale authority rather than being copied into a parallel Website engine.

No business route is added at this point; MT-3.4 owns versioned REST exposure and its customer/session/capability middleware. Private source-data rehearsal remains MT-7.1. Legacy transport fields and events remain inactive historical evidence and are never used as current stock, payment or authorization.

## Order and reservation creation

Checkout accepts a server-derived customer or guest owner scope and a 16-128 character idempotency key. Persisted `owner_scope_hash` prevents contact fields or a guessed order number from authorizing another order. Input uses a strict allowlist and never accepts price, total, outlet, paid state, cost, actor or stock fields.

The service locks each canonical product, rejects duplicate lines and mixed-outlet physical checkout, checks current variant availability, and derives exact PKR prices with BCMath. It stores immutable order/reservation snapshots, then creates either a time-limited active hold for a configured provider or an explicit `held_cod` allocation. Website operating mode is read and locked through `WebsiteCapabilities`; inactive commerce prevents new checkout, while later verified callbacks and authorized historical project payments remain processable after a mode switch.

Cancellation verifies the same stored owner-scope hash, refuses collected orders, and releases allocations atomically. The expiry operation releases only overdue active reservations; COD holds do not silently expire. Released, expired and confirmed allocations cannot be resurrected.

## Payment boundary and recovery

`PaymentProvider` is the narrow adapter contract. `PaymentProviders` refuses an unavailable adapter before creating a transaction. JazzCash, Easypaisa and Card remain disabled in committed configuration; COD is internal. A test-only signed fake proves initiation and callback behavior without network traffic, credentials or fabricated provider acceptance. The adapter must verify provider authenticity and return an exact canonical event; the shared service then rechecks gateway, merchant, mode, order/payment reference, amount, currency and payload digest.

External initiation occurs after a durable payment intent exists and outside a database transaction. Matching initiation replay returns the stored safe result without issuing a second provider request. Callback application is transactional. Matching event replay returns the existing result; changed replay, amount/currency/reference tampering and a second collection conflict. A verified failure releases an active hold and leaves retryable evidence. An authorized retry is allowed only after a definitive failure, while commerce remains active and the same products, outlet and prices can be reserved again; unknown results require reconciliation instead of a new charge. A verified payment received after cancellation/release/expiry is preserved as `paid_reconciliation`, releases any remaining hold, creates no sale and requires explicit reconciliation. Failure cannot overwrite a completed payment.

COD collection requires the current Admin `shop.sales` permission and the reservation outlet assignment. It records an immutable internal receipt and follows the same payment-to-invoice settlement path. No raw PAN/CVV input or generic provider payload is stored by this service.

## Shared sale settlement

Physical settlement locks the reservation, order, outlet, items and products. Order, reservation and line totals must match exactly. It creates one invoice linked to the order, retains canonical business and warranty snapshots, creates sale rows from immutable Website item snapshots, and consumes exactly the held quantity or physical units through `TransactionalStock::consumeSale`. Invoice, sales, allocation release, stock/IMEI state, receipt, payment and order confirmation commit or roll back together.

The additive migration records owner scope, payment intent/failure/reconciliation/completion evidence, verified receipt outcome and terminal refund evidence. Database checks require paid payments to have a completion timestamp, enforce coherent refund terminal timestamps, and pair a paid milestone with one payment. The empty disposable MySQL rollback/reapply gate proves the extension can be removed only before it owns transaction evidence.

## Project milestones and refunds

Project milestone payment creation is an authorized historical-resource path and therefore does not depend on current public commerce mode. The service locks the immutable MT-2.8 milestone and approved quote, verifies current validity and exact customer email or mobile ownership, and creates a digital order/payment for the approved amount only. One milestone maps to one order item and one paid payment. A paid milestone cannot be reopened or reassigned; the quote becomes paid only when verified paid milestones equal its approved amount.

Manual refund recording requires an accepted return, the original order payment, a fresh authorized Admin/outlet relationship and a SHA-256 evidence digest. It locks the payment and all accepted return/refund balances. Pending, unknown and completed refund amounts reserve collected funds, so retries or parallel requests cannot exceed either collected payment or accepted-return net value. This checkpoint records only already-verified manual evidence; automatic provider refund remains unavailable until an authentic adapter contract passes H-02.

## Verification and boundaries

Focused tests cover server repricing, duplicate/mixed-outlet rejection, idempotent COD settlement, shared stock/sale effects, disabled providers, signed provider initiation and callbacks, event replay/tampering, failure release, unknown outcomes, late-payment reconciliation, mode switching, project ownership/amount identity and bounded refunds. Existing independent-connection inventory tests continue to arbitrate competing reservations and sales in MySQL.

The full backend suite, strict schema/UTC checks, empty-schema rollback/reapply, Pint, Composer validation/platform requirements, Laravel optimize/clear, POS TypeScript/Vite build, and Website ESLint/TypeScript/Next.js build pass. Exact evidence is in `MT_2_7_VERIFICATION.json`.

Authentic JazzCash/Easypaisa/Card contracts, credentials, sandbox/live callbacks and automatic refunds remain H-02. REST endpoints and customer-facing flows remain MT-3.4/MT-5.2/MT-5.3. Supplier/procurement services start at MT-2.9. The approved Goal, Preferences, addendum, Source of Truth, registry and structural roadmap Markdown/DOCX are unchanged by this routine point.
