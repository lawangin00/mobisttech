# MT-7.5 / W04 — hosted acceptance evidence and remaining gates (22-Sep-2026)

Status: **IN PROGRESS**; W04 is not closed. This evidence supplements `MT-7.5_W04_PAYMENT_PARITY.md` and the canonical implementation-status ledger; it does not change the stage count (15/27 DONE, 12 OPEN).

## Confirmed full-scope hosted acceptance

- Source checkout / disposable CI environment run: https://github.com/lawangin00/mobisttech/actions/runs/35671199123 ; both explicit request validator and clean checkout acceptance jobs completed **success** on source revision `e74587a2d3644a4e4cad5fb79484571034b9682a`.
- That clean-hosted run included PHP style, backend TypeScript/build, full backend regression (341 passed; 10059 assertions), explicit MySQL race/reset tests (23 passed; 1684 assertions), Website TypeScript/lint/production build, tracked secret checks, POS/Admin browser, Website checkout, Website customer/project, public content and performance browser suites. Final schema/cleanup verifier reported `result: PASS`, `business_rows: 0`, `canonical_seed_rows: 316` on the isolated `mobisttech_test` service. Intermediate fixture diagnostics must not be substituted for the final verifier.
- This closes the *previous CI fixture-residue failure only*. It does **not** dispose the immutable historical W04 source inventory, establish authentic payment provider adapters, or prove genuine bank/provider sandbox or production callbacks.

## Additional W04 negative browser gate

- Source-only test commit: https://github.com/lawangin00/mobisttech/commit/c6fcf6393129f2e32ed282d163a26b412aebd418 . The diff adds one test in `backend/tests/browser-website/checkout.spec.ts` and changes no production payment logic.
- The browser-only stub advertises JazzCash for this one simulated UI journey while all authentic external providers remain default-OFF. After an order is synthetically created, payment initiation supplies an insecure `http://` redirect. Expected assertions: the page must refuse navigation, display `Payment provider returned an unsafe redirect.`, keep the exact created order recovery link, disable any duplicate Place order action, and record exactly one order-submit and one payment-initiate request. Stubbed order IDs and responses are not server-side real orders or provider receipts.
- Explicit full-scope CI request commit `c8f15d51b379afabea72134b93ec9a12444cb66b` triggered https://github.com/lawangin00/mobisttech/actions/runs/35673288185 . Request validation passed; clean checkout job **was running when this checkpoint was written**. Do not mark this new negative case PASS until the Website checkout test and terminal final schema/cleanup gates complete successfully.

## Still OPEN; no implicit closure

1. Reconcile **each** of the immutable 52 historical Website W04 source files and 16 W04 source routes against retained/adapted/retired target behavior with explicit evidence. The existing target-only route/UI crosswalk is preliminary; do not use the 16 source-route number as a target-route count.
2. Complete uncovered negative owner/amount/reference/cross-provider/replay/refund, provider-unavailable and operating-mode cases with focused API/browser evidence. Existing scoped synthetic tests may satisfy individual cases only when linked to exact source and behavior.
3. Keep genuine JazzCash, Easypaisa and card merchant credentials, signed webhook contract fixtures, settlement and external refunds **EXTERNAL UNVERIFIED / HOLD** pending actual provider documentation and authorized sandbox access. Never activate a fake adapter, weaken schema/cleanup or signature guards, store raw PAN/CVV, or count synthetic callbacks as authentic provider acceptance.
