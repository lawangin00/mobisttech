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

## First genuinely pending W04 substep

Disposition all 52 source files and 16 routes against current authoritative Website payment behavior, identifying any concrete missing target security/UI flow. Then verify negative owner/reference/cross-provider/replay/refund and operating-mode cases through scoped API/browser acceptance where applicable. Preserve default-OFF external providers and authentic provider HOLD. Do not reopen completed W03 or run unrelated full-suite CI.
