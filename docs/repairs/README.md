# MT-2.16 Paid Repair Services

MT-2.16 adds an optional, disableable out-of-warranty paid-repair domain that remains separate from warranty claims.

The backend now owns repair intake, device identifiers, diagnosis, immutable estimate versions and approval/rejection, parts and labor lines, lifecycle status, collection, exact payment linkage and append-only repair history.

New intake requires the `shop.repairs` permission and an enabled repair setting. Disabling the feature blocks new intake only; existing jobs, estimates, payments and history remain operable.

Approved estimates are the amount authority. Rejected or superseded estimates remain immutable historical records and cannot silently change an approved obligation.

Repair parts reuse the authoritative inventory stock ledger. Consumption is transactional, honors existing holds, records stock movements and is replay-safe under separate MySQL connections.

Repair payment collection reuses approved POS payment destinations and tender allocation semantics. Collected allocations must exactly equal the approved estimate total, remain linked to the repair job/estimate and are idempotent.

Warranty `claims` remain a distinct domain and were not repurposed or weakened for paid repairs.

Verification includes schema checkpoint/rollback/reapply proof, focused lifecycle/payment/estimate tests, affected payment/retention regression, real MySQL repair-part concurrency, full backend regression and backend/Website production build gates.
