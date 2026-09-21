# MT-7.5 W04 Website payment parity — active checkpoint

Status: IN PROGRESS. This is a bounded synthetic payment-matrix acceptance, not W04 family closure.

## Binding scope

- Website channels remain exactly Cash on Delivery, JazzCash, Easypaisa and hosted credit/debit card. No Website bank transfer or split tender.
- The 52 source-file / 16 route W04 inventory still requires explicit feature/route disposition. Existing W03 and POS tender acceptance do not close W04.
- Real merchant/provider contracts, genuine sandbox or live provider callbacks, external refunds and bank settlement remain EXTERNAL UNVERIFIED and must not be represented as synthetic PASS.

## Verified target-only checkpoint (21-Sep-2026)

- Added one focused `OrderPaymentTransactionsTest` case verifying the fixed four-channel ordering, default-OFF for the three external providers and synthetic registration for each provider separately.
- Each synthetic provider created its own new commerce order, initiated exactly one durable reference, rejected a wrong signed amount and a tampered signature, accepted its valid paid event and repeated that event idempotently. Three provider receipts and three target sales were recorded in the isolated transactional test.
- Focused local test: **1/1 PASS, 24 assertions**; full neighboring `OrderPaymentTransactionsTest.php`: **16/16 PASS, 121 assertions**. No authentic network/provider operation occurred.

## GitHub-mode hosted style verification (21-Sep-2026)

- Original exact-source preflight `35627284111` on request commit `8c7fc0cf4eede6ca44dbe1da37652f29895e1580` passed its CI request validation and isolated setup but FAILED clean-hosted Pint on exactly one of 276 PHP files: `tests/Feature/OrderPaymentTransactionsTest.php` (`class_attributes_separation`); all subsequent acceptance steps were skipped. Independent Website static build `35627284122` passed but did not establish PHP style acceptance.
- Scoped source correction `89a5a66d3e2516bd0152009c00788657d3b71a10`: GitHub commit diff proves **only one blank line** inserted between the new four-channel test and the next existing method; test assertions and production code are unchanged. The full file was preserved while using GitHub's complete-file replacement API.
- Explicit necessary CI request commit `7df4142a0909667cbc91e738669c81b5542fcba3` names exact source parent `89a5a66`, correct project ID and approved GITHUB execution mode. Clean hosted run `35628330989` has PASSED request validation, isolated MySQL preparation, **Composer and PHP style gate including Pint**, and backend TypeScript/production build. The full backend regression and later steps were still running at the time of this documentation checkpoint; do not represent the entire run or W04 family as accepted unless terminal verification supports that claim.
- The prior Windows local Pint failure included additional rule names from its Windows checkout; clean hosted Pint after the one-line correction is PASS. No broad baseline reformatting or safety waiver was necessary.

## First genuinely pending W04 parity substep

Collect the terminal result of exact hosted run `35628330989`, then disposition all 52 source files and 16 routes against current authoritative Website payment behavior, identifying concrete missing target security/UI flows. Verify negative owner/reference/cross-provider/replay/refund and operating-mode cases through scoped API/browser acceptance. Preserve default-OFF external providers and authentic provider HOLD. Do not reopen completed W03 or run unrelated repeated full-suite CI.
