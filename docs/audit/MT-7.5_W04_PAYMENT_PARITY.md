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
- Explicit necessary CI request commit `7df4142a0909667cbc91e738669c81b5542fcba3` names exact source parent `89a5a66`, correct project ID and approved GITHUB execution mode. Clean hosted run `35628330989` PASSED request validation, isolated MySQL preparation, Composer/PHP Pint (**276 files**), backend build, full backend regression (**341 tests, 10059 assertions**), explicit MySQL race/reset (**23 tests, 1684 assertions**), Website build, unchanged bundle budgets and secret scan. W01/P02/W03 family-specific browser steps were intentionally SKIPPED by the full-scope request; they cannot be counted as focused acceptance in this run.
- **Terminal run `35628330989`: FAILURE** at monolithic `POS and Admin Playwright`: **19 failed, 15 passed, 1 skipped**. All subsequent Website browser and final schema/cleanup gates were skipped. First failure: known stale `backend/tests/browser/mt75-fresh-next-smoke.spec.ts:63` asserts `Qty 0` within a product `<button>` although the quantity renders outside the button, leaving the fresh-product journey incomplete. Other errors include follow-on missing fresh product / customer login 422 / sitemap fixture failure, multiple Admin `waitForURL('**/internal/admin/pos')` timeouts, `PosMasterDataLinkedE2eSeeder` requiring an empty accessory baseline, warranty-intake seeder failure, and one residual `identity_audit_events` row after cleanup. These are concrete browser/fixture failures; their common root cause and independence have not been proven. This full run does NOT establish W04 browser acceptance and does not undo independently passing PHP/payment tests.
- The prior Windows local Pint failure included additional rule names from its Windows checkout; clean hosted Pint after the one-line correction is PASS. No broad baseline reformatting or safety waiver was necessary.

## Initial current-target payment route and UI crosswalk (21-Sep-2026)

This is a **target-side partial crosswalk only**, not a disposition of the immutable 52 historical Website files / 16 source routes. Source inventory counts must not be confused with the target route count.

- `backend/config/commerce.php` and `backend/app/Commerce/PaymentProviders.php`: COD is internally enabled; JazzCash, Easypaisa and card are default-OFF and require both an enabled merchant configuration and a registered provider adapter. The registry advertises exactly the four approved channels. No authentic adapter has been accepted or activated by these synthetic tests.
- `backend/routes/identity.php` + `CustomerApiController`: customer-realm `GET /api/v1/checkout/channels`, `POST /api/v1/orders`, `POST /api/v1/payments/{payment}/initiate`, `POST /api/v1/orders/{order}/payments/retry`, `POST /api/v1/orders/{order}/cancel`, `POST /api/v1/project-milestones/pay` and `GET /api/v1/project-payment-channels` route through shared Laravel order/payment services. Payment initiation explicitly checks owned payment; retry requires one of the three external channels; milestone payment retains Customer identity and its own quote authority.
- `backend/routes/api.php`: public `POST /v1/payment-callbacks/{gateway}` accepts only the three external gateway slugs behind a callback throttle. Verified signature, amount/reference/currency and replay/reconciliation behavior are service/adapter responsibilities, not proof of authentic provider integration.
- `website/src/components/customer-checkout-form.tsx`: checkout reads server-advertised channel availability, never offers Website bank transfer or split tender, and accepts only HTTPS hosted redirects. It creates the durable order before attempting external initiation, then clears the cart; if initiation subsequently fails it displays an error but not the created order's direct recovery link.
- `website/src/components/customer-order-detail.tsx`: an owned pending external payment exposes **Continue payment** through the same initiation endpoint, and a failed payment offers available-channel retries. This provides a recovery path after initiation failure, but the complete failure-to-recovery browser journey and clear checkout-to-order navigation still need acceptance. Do not label this UX gap a proven security defect.

## First genuinely pending W04 parity substep

Isolate the **already observed** stale fresh-product quantity locator and fixture/login baseline in bounded browser diagnostics; do not broadly rewrite tested payment behavior or blindly rerun the 35-test monolithic suite. Independently disposition the historical 52 source files and 16 routes individually or in an explicitly exhaustive, count-reconciled table against current payment behavior, identifying missing target security/UI flows. Verify negative owner/reference/cross-provider/replay/refund and operating-mode cases through scoped API/browser acceptance, including external initiation failure-to-owned-order recovery. Preserve default-OFF external providers and authentic provider HOLD. Do not reopen completed W03 or mark W04 closed based on synthetic tests.
