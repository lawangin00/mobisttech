# MT-2.12 Cash Sessions and Operational Expense Services

MT-2.12 adds backend authority for outlet-scoped operational cash control without introducing a general ledger.

## Implemented contracts

- One active cash session per outlet, with opening cash, operator identity, business date, versioning and immutable close history.
- Authorized Cash In, Expense and Payout entries use explicit reasons, approval state, actor attribution and idempotent writes.
- Cash POS tenders and Cash refunds require and bind to the active outlet cash session; non-cash tenders remain operationally separate.
- Closing computes exact `Opening Cash + Cash Sales + approved Cash In - Cash Refunds - Expenses - Payouts = Expected Cash`.
- Actual cash count is compared with Expected Cash; non-zero variance requires a reason and `shop.cash.approve` authority.
- Non-cash receipt summaries remain grouped by immutable Payment Destination snapshot and retain gross expected receipts, settlement fees, adjustments, expected net, received net and settlement variance separately.
- Closed sessions retain a canonical closing snapshot and SHA-256 digest so later Payment Destination edits cannot rewrite history.
- Row locking, outlet locking, version preconditions and idempotency prevent duplicate close and close-versus-sale omission races.
- Reset retention classifies cash sessions and entries as transactional history, and populated financial history blocks unsafe migration rollback.

## Boundaries

This point provides backend cash/session/expense authority only. MT-3.1 owns broader reporting/document communication, and MT-4.6 owns the operator Day Closing and cash-management interfaces. No private source data, provider activation, external message, production deployment or general-ledger/accounting workflow is introduced.

## Verification

Fresh MySQL rollback/reapply, schema integrity, focused cash/payment/concurrency tests, the complete backend suite, Pint, Composer/platform checks, Laravel optimize/clear, POS production build, Website lint/typecheck/production build and Git diff checks passed. Detailed evidence is recorded in `docs/cash/MT_2_12_VERIFICATION.json`.