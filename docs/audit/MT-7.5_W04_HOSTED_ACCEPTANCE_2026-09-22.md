# MT-7.5 / W04 — hosted acceptance evidence and remaining gates (22-Sep-2026)

Status: **IN PROGRESS**; W04 is not closed. This evidence supplements `MT-7.5_W04_PAYMENT_PARITY.md` and the canonical implementation-status ledger; the stage count remains 15/27 DONE, 12 OPEN. All acceptance runs below use synthetic test fixtures; none constitutes live provider acceptance.

## Confirmed full-scope hosted acceptance

- Initial fixture-cleanup recovery: https://github.com/lawangin00/mobisttech/actions/runs/35671199123 ; request validator and clean-checkout acceptance jobs completed **success** on source revision `e74587a2d3644a4e4cad5fb79484571034b9682a`. Backend regression passed 341 tests/10059 assertions; MySQL race/reset passed 23 tests/1684 assertions; POS/Admin, Website checkout, customer/project, public content, performance, production build, and final schema/cleanup gates passed. Final disposable `mobisttech_test` verifier reported `result: PASS`, `business_rows: 0`, `canonical_seed_rows: 316`. Intermediate fixture diagnostics are not interchangeable with the final verifier.
- This fixes the previous CI fixture-residue failure, but does **not** prove source payment parity or genuine provider callbacks/settlement.

## W04 negative browser gate — clean-host PASS

- Test commit https://github.com/lawangin00/mobisttech/commit/c6fcf6393129f2e32ed282d163a26b412aebd418 adds a browser-only JazzCash stub journey in `backend/tests/browser-website/checkout.spec.ts`; production payment code is unchanged. The stub returns an insecure `http://` redirect after synthetic order creation. Assertions require no navigation, an unsafe-redirect error, retention of the created-order recovery link, no duplicate Place order, and exactly one order-submit and one payment-initiate request. Stubbed IDs are not genuine server orders or provider receipts.
- Full run https://github.com/lawangin00/mobisttech/actions/runs/35673288185 finished **success**: Website checkout browser including this test, backend regression, MySQL race/reset, Website build, POS/Admin and other Website suites, final schema/cleanup and tracked-runtime-artifact gate passed. W01/P02/W03 separately scoped isolated family steps were skipped by this run; do not count them as newly accepted.

## W04 raw-card negative backend gate — clean-host PASS

- Test-only commit https://github.com/lawangin00/mobisttech/commit/5dd13d8fb10bccfe8d7ac14e55fb9143e2d30f75 adds `backend/tests/Feature/W04RawCardSubmissionTest.php`. Crafted `card_number`, `card_cvv` and `card_expiry` submitted to Website checkout must fail validation before any order, payment or idempotency record is created. It does not simulate or enable a hosted-card processor and changes no production payment code.
- Full run https://github.com/lawangin00/mobisttech/actions/runs/35675531876 finished **success**: explicit request validator, full backend regression (including the new test), MySQL race/reset, Website build/static gates, POS/Admin and Website browser suites, and final disposable schema/cleanup gate all succeeded. These are synthetic application-security tests, not external card-provider certification.

## Historical Website W04 disposition — inventoried, not accepted

- The 16 **historical source routes** are individually accounted for in `MT-7.5_W04_ROUTE_CROSSWALK.md` (commit `aa529d68fccf36883ef0ff6ac85ac861221f5985`); the 52 **historical source files** are individually accounted for in `MT-7.5_W04_FILE_CROSSWALK.md` (commit `0dd47d9961f6c680ccca4b53a44cf56250436106`). These are immutable source-family assignments, not target file/route counts. An ADAPT or REPLACE disposition does not by itself prove functional equivalence; GAP and HOLD entries remain OPEN.

## Still OPEN; no implicit closure

1. Resolve and test historical W04 **Admin payment-channel settings, publish/revision controls, permission boundaries, and editable encrypted merchant credentials**; the current default-OFF channel registry is not a substitute for an authorized editable integration. Retain source-by-source behavior evidence from both crosswalks.
2. Finish missing focused negative owner/amount/reference/cross-provider/replay/refund, provider-unavailable and operating-mode cases. Link individual cases to exact target HTTP/API/browser evidence rather than counting the full-suite pass as blanket parity.
3. Genuine JazzCash, Easypaisa and selected hosted-card processor agreements, merchant credentials, signed callback/webhook contract fixtures, settlement and external refunds remain **EXTERNAL UNVERIFIED / HOLD**, pending genuine provider documentation and authorized sandbox access. Never activate a fake adapter, weaken schema/cleanup or signature guards, store raw PAN/CVV, or treat synthetic callbacks as authentic provider acceptance.
