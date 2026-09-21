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

- Repository/source baseline: `451b66536dcd4f19af4778a834491c21133b7813`; W04 follow-up evidence commit `3d30e4d49c5c21b36f732be507bfabaee36f9824`. Explicit necessary CI request `8c7fc0cf4eede6ca44dbe1da37652f29895e1580` has exact source parent `3d30e4d` and valid project/mode identity.
- CI run `35627284111`: request validation, clean checkout, PHP/Node/dependency install and isolated MySQL migration PASS. **Composer and PHP style gate FAILED**: Pint reported `276 files, 1 style issue`, exclusively `tests/Feature/OrderPaymentTransactionsTest.php`, beginning `class_attributes_separation`; backend builds and browser/whole-suite gates were skipped. This run is NOT a W04 acceptance PASS. The independent GitHub-only Website static/build workflow `35627284122` passed; it does not validate PHP style.
- Exact source inspection confirms a missing blank separator between newly inserted `test_website_four_channel_matrix_and_synthetic_provider_replay_security` and pre-existing `test_disabled_provider_fails_before_order_and_verified_callback_is_replay_safe_across_mode_switch`. The prior accepted source at `142e5b8` had that separator. Local Pint also reported other style rule names on the same test file; determine whether any remain after the minimal separator fix, without indiscriminately reformatting unrelated legacy lines.
- Next necessary correction: insert only the missing separator in the full GitHub file with unchanged test behavior; read back and compare the exact file diff against `451b665`; then issue one properly scoped exact-source hosted style recheck and inspect any remaining failure before advancing. Do not bypass the Pint gate or weaken tests. GitHub contents API replaces whole files, so avoid partial-content overwrite of this approximately 500-line test file.

## First genuinely pending W04 parity substep

After the style gate, disposition all 52 source files and 16 routes against current authoritative Website payment behavior, identifying concrete missing target security/UI flows. Verify negative owner/reference/cross-provider/replay/refund and operating-mode cases through scoped API/browser acceptance. Preserve default-OFF external providers and authentic provider HOLD. Do not reopen completed W03 or run unrelated repeated full-suite CI.
