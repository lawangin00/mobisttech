# MT-7.5 W04 Website payment parity — active checkpoint

Status: IN PROGRESS. This is a bounded synthetic payment-matrix acceptance, not W04 family closure.

## Binding scope

- Website channels remain exactly Cash on Delivery, JazzCash, Easypaisa and hosted credit/debit card. No Website bank transfer or split tender.
- The 52 source-file / 16 route W04 inventory still requires explicit feature/route disposition. Existing W03 and POS tender acceptance do not close W04.
- Real merchant/provider contracts, genuine sandbox or live provider callbacks, external refunds and bank settlement remain EXTERNAL UNVERIFIED and must not be represented as synthetic PASS.

## Verified target-only checkpoint (21-Sep-2026)

- Added one focused `OrderPaymentTransactionsTest` case verifying the fixed four-channel ordering, default-OFF for the three external providers and synthetic registration for each provider separately.
- Each synthetic provider created its own new commerce order, initiated exactly one durable reference, rejected a wrong signed amount and a tampered signature, accepted its valid paid event and repeated that event idempotently. Three provider receipts and three target sales were recorded in the isolated transactional test.
- Focused test: **1/1 PASS, 24 assertions**; full neighboring `OrderPaymentTransactionsTest.php`: **16/16 PASS, 121 assertions**. No authentic network/provider operation occurred.

## GitHub-mode continuation: exact pending style/source gates

- The source at commit `451b66536dcd4f19af4778a834491c21133b7813` contains a missing blank line between the new four-channel test and the immediately following existing test in `backend/tests/Feature/OrderPaymentTransactionsTest.php`. The preceding commit `142e5b8` already had the expected blank separator before the existing test. This is a bounded new formatting defect, not a commerce behavior failure.
- The previous Windows local scoped Pint check also reported other style/line-ending rules on that test file; their cause has not been independently established. The earlier hosted W03 run `35622831675` passed its pre-W04 PHP style gate. No hosted Pint/CI result has been collected for `451b665` or this documentation checkpoint; do not assert that the W04 source passed hosted style.
- Next: correct only the demonstrated blank-line defect without altering test assertions, verify the exact source through an authorized GitHub-mode CI request and inspect any remaining style failure before changing unrelated baseline formatting.
- Continue source inventory disposition for all **52 W04 files / 16 routes** against canonical COD collection, customer checkout/retry/cancel, payment initiation/callback, authenticated owner/reference/amount/replay, accepted-return refunds, provider default-OFF and three Website operating modes. Do not call the source inventory disposition complete until every item is mapped, and retain authentic external-provider acceptance as HOLD.

## First genuinely pending W04 substep

Correct and verify the isolated test style defect; then disposition all 52 source files and 16 routes against current authoritative Website payment behavior, identifying any concrete missing target security/UI flow. Verify negative owner/reference/cross-provider/replay/refund and operating-mode cases through scoped API/browser acceptance where applicable. Preserve default-OFF external providers and authentic provider HOLD. Do not reopen completed W03 or run unrelated full-suite CI.
