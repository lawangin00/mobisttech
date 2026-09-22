# MT-7.5 / W04 — historical Website payment file and route disposition

**22-Sep-2026 | Source-only crosswalk complete; W04 functional acceptance remains IN PROGRESS.** This is a count-reconciled disposition of the frozen *Website* source at `04e7c49518f9f11f60c83ad44f9f4e2fd2539066`, not permission to import its database, activate a gateway, or claim a live merchant test. The target inspected here is `lawangin00/mobisttech` commit `427c04466278d0c267469c7d49b25f2cb16ec37c` (subsequent documentation commits do not alter application behavior). Historical references: `docs/migration/SOURCE_SYMBOL_INVENTORY.json` (`files[*].source=website`, `families` contains `W04`; `routes[*]` likewise); `docs/migration/SOURCE_FILE_INVENTORY.json`; `docs/migration/FEATURE_PARITY_REGISTER.md`. **Exactly 52 distinct source-file paths and 16 distinct source route entries are listed below.** Multi-family assignments are retained rather than attributed exclusively to W04.

## Disposition vocabulary and target anchors

- **A — adapt / source contract retained:** equivalent behavior now belongs in shared `backend/app/Commerce/PaymentProviders.php`, `OrderTransactions.php`, provider contracts, `backend/config/commerce.php`, customer-scoped endpoints in `backend/routes/identity.php`, public callback route in `backend/routes/api.php`, or the named Next.js customer component. Existing synthetic tests establish only the particular scenarios actually exercised, not the whole W04 family.
- **S — superseded presentation / transport:** old Blade, styling or duplicate endpoint is replaced by the approved single-backend + Next.js or canonical brand arrangement; original URL/file is not a mandatory target artifact. Preserve its payment and user-visible behavior where applicable.
- **G — gap / externally gated:** historical merchant-key mutation, authentic wallet/card adapter, verified vendor-specific callback/refund/settlement, payment-owner authorization, and complete Admin settings UX are **not** established by target read-only status views or synthetic adapters. Owner/vendor integration is H-02 HOLD; no real credentials in Git or chat. Every A/S row involving external providers inherits G for real-provider behavior.
- **T — adapt assertions, not source fixtures:** original source tests/fakes remain source characterization; target tests must cover the corresponding behavior using new isolated synthetic records. Never call a test-file's presence target acceptance.

Target evidence at this checkpoint: `backend/tests/Feature/OrderPaymentTransactionsTest.php`, `W04CrossProviderReceiptBoundaryTest.php`, `W04MalformedVerifiedProviderEventTest.php`, `W04CallbackRouteAllowlistHttpTest.php`, `W04ProviderConfigurationShapeTest.php`, `W04PaymentSettingsReadOnlyHttpTest.php`, `website/src/components/customer-checkout-form.tsx`, `customer-order-detail.tsx`, and hosted **full** CI run `35753196003` (terminal SUCCESS for its exact requested source). Those are scoped technical gates, **not** a vendor sandbox/live receipt or 52-file semantic test pass. The Website Admin's `backend/routes/website-payment-admin.php` exposes GET payment-settings and GET payment-channels only; mutation verbs are rejected and no writable credential/merchant enrollment workflow exists yet.

## All 52 historical Website W04 files (distinct paths; frozen-source relative paths)

| # | Historical path | Disposition and target responsibility / outstanding gate |
|---:|---|---|
| 01 | `app/Contracts/Payments/HostedCardGateway.php` | A/G: use registered hosted-provider interface; authentic chosen processor API, verification and refunds pending. |
| 02 | `app/Contracts/Payments/PaymentProvider.php` | A: shared Laravel `PaymentProvider` / `PaymentProviders` contract; no source provider object copied. |
| 03 | `app/Http/Controllers/Admin/EasypaisaConfigurationController.php` | S/G: protected Admin payment-status view exists; separate permissioned merchant settings/secret write, masked reads, recent-auth and audit pending. |
| 04 | `app/Http/Controllers/Admin/JazzCashConfigurationController.php` | S/G: same Admin gap for JazzCash, independent merchant ownership and environment validation. |
| 05 | `app/Http/Controllers/Admin/PaymentsCheckoutController.php` | S/G: fixed four-channel server allowlist; Admin editable settings/publish/owner controls not replaced by GET status. |
| 06 | `app/Http/Controllers/CheckoutController.php` | S: Next.js customer checkout + server-created order and payment; failed initiation returns owned-order recovery link; browser proof scoped. |
| 07 | `app/Http/Controllers/EasypaisaCallbackController.php` | S/G: one throttled `POST /api/v1/payment-callbacks/easypaisa`; signed synthetic contract only, vendor payload and signatures unverified. |
| 08 | `app/Http/Controllers/JazzCashCallbackController.php` | S/G: one throttled `POST /api/v1/payment-callbacks/jazzcash`; vendor payload and signatures unverified. |
| 09 | `app/Http/Controllers/ProjectPaymentController.php` | S/G: account-owned project/milestone payment through Customer REST, not public legacy token purchase; cross-family W05 quote/token-equivalence acceptance pending. |
| 10 | `app/Models/Payment.php` | A: unified shared order/payment and receipt data with immutable owner/reference/history boundaries. |
| 11 | `app/Payments/AbstractPendingPaymentProvider.php` | A/G: shared pending-intent lifecycle and exact server reference; authentic provider initiation pending. |
| 12 | `app/Payments/CashOnDeliveryPaymentProvider.php` | A: internal COD order state and POS collection remain distinct from external paid callback. |
| 13 | `app/Payments/EasypaisaPaymentProvider.php` | A/G: closed provider registry and synthetic validation; real Easypaisa adapter pending. |
| 14 | `app/Payments/Exceptions/UnsupportedPaymentOperation.php` | A: disabled/unconfigured operations fail closed; don't silently mark paid. |
| 15 | `app/Payments/HostedCardPaymentProvider.php` | A/G: hosted HTTPS redirect only, no merchant selection, raw PAN/CVV capture or real processor adapter. |
| 16 | `app/Payments/JazzCashPaymentProvider.php` | A/G: closed provider registry and synthetic validation; real JazzCash adapter pending. |
| 17 | `app/Payments/PaymentProviderResult.php` | A: explicit verified-event fields, nonempty string/type, amount/currency/reference/status and hash validation. |
| 18 | `app/Payments/UnconfiguredHostedCardGateway.php` | A: card default OFF and absent adapter fail closed; not evidence of card readiness. |
| 19 | `app/Payments/WalletPendingPaymentProvider.php` | A/G: pending/retry/replay state shared; genuine wallet API and settlement pending. |
| 20 | `app/Services/CardConfiguration.php` | S/G: target config default OFF; approved processor/encrypted owner-managed credentials and mode pending. |
| 21 | `app/Services/CashOnDeliveryCollectionService.php` | A: COD reconciliation/collection requires server-authoritative payment transition, not a fake external receipt. |
| 22 | `app/Services/CashOnDeliveryConfiguration.php` | A: approved internal COD channel controlled by server, not a merchant secret. |
| 23 | `app/Services/EasypaisaConfiguration.php` | S/G: target config default OFF; protected editable merchant/env and encrypted credentials pending. |
| 24 | `app/Services/JazzCashConfiguration.php` | S/G: target config default OFF; protected editable merchant/env and encrypted credentials pending. |
| 25 | `app/Services/PaymentManager.php` | A: shared `OrderTransactions`/`PaymentProviders` replace source dual-database orchestration; retain idempotent receipt behavior. |
| 26 | `app/Services/VerifiedPaymentService.php` | A/G: receipt source/order/reference/amount/currency and replay checks; vendor-specific true verification/refunds not evidenced. |
| 27 | `app/Services/WebsiteCredentials.php` | S/G: never import source secrets; encrypted/rotatable per-merchant Admin write path and secret masking pending. |
| 28 | `app/Support/WebsiteCredentialRegistry.php` | A/G: defined allowlisted secret names; target owner-scoped write/read/rotate and secret-lifecycle acceptance pending. |
| 29 | `config/payments.php` | A/G: canonical `backend/config/commerce.php` fixed COD/JazzCash/Easypaisa/card allowlist; external default OFF. |
| 30 | `public/brand/card-payment-logo.svg` | S: historical payment logo remains reference only; target canonical branded runtime assets / accessible channel labels, B01. |
| 31 | `public/brand/jazzcash-logo.svg` | S: historical provider mark is not a merchant connection; approved target asset only after licensing/brand review. |
| 32 | `public/css/checkout-selection.css` | S: Next.js responsive checkout selection styling; keep disabled-channel clarity. |
| 33 | `public/css/payment-brand.css` | S: target canonical brand tokens and payment affordances; do not copy old CSS wholesale. |
| 34 | `public/css/payment-fields.css` | S: Next.js hosted redirect payment UI; never reintroduce raw card inputs. |
| 35 | `public/js/payment-fields.js` | S: new Customer UI + server channel availability; no client-selected merchant or raw card fields. |
| 36 | `resources/views/admin/payments-checkout.blade.php` | S/G: protected Admin status display exists; editable four-channel config and publish/recent-auth UX missing. |
| 37 | `resources/views/admin/payments-easypaisa.blade.php` | S/G: provider status view not equivalent to merchant and credential editor. |
| 38 | `resources/views/admin/payments-jazzcash.blade.php` | S/G: provider status view not equivalent to merchant and credential editor. |
| 39 | `resources/views/checkout.blade.php` | S: `customer-checkout-form.tsx` + owned order/recovery; complete mode and failure browser coverage remains independent. |
| 40 | `resources/views/order-payment.blade.php` | S: `customer-order-detail.tsx` continue/retry owned payment; W03 order UI joins independently. |
| 41 | `resources/views/partials/payment-options.blade.php` | S: fixed four Website-only options, unavailable-provider messaging; no Website bank transfer/split tender. |
| 42 | `resources/views/project-payment-lookup.blade.php` | S/G: owned project portal replaces anonymous lookup; W05 consent/ownership/token equivalence and mode journey pending. |
| 43 | `resources/views/project-payment.blade.php` | S/G: project milestone payment through Customer-owned service; authentic provider payment pending. |
| 44 | `tests/Fakes/FakeHostedCardGateway.php` | T: synthetic adapter remains test-only; no inference of a real merchant/card network. |
| 45 | `tests/Feature/CashOnDeliveryProviderTest.php` | T: adapt COD state/collection/negative assertions in target OrderPaymentTransactions suite. |
| 46 | `tests/Feature/EasypaisaAdapterConfigurationTest.php` | T/G: target fail-closed/signed mock tests; real official Easypaisa callback contract pending. |
| 47 | `tests/Feature/HostedCardSecurityTest.php` | T/G: hosted HTTPS/no PAN, owner/reference and replay negatives; real processor sandbox/refund pending. |
| 48 | `tests/Feature/JazzCashAdapterConfigurationTest.php` | T/G: target fail-closed/signed mock tests; real official JazzCash callback contract pending. |
| 49 | `tests/Feature/PaymentManagerContractTest.php` | T: target shared order/payment transaction, idempotency and tamper negative gates. |
| 50 | `tests/Feature/PaymentsCheckoutAdminTest.php` | T/G: fixed four channels and protected GET display covered; writable credential/settings and owner UI pending. |
| 51 | `tests/Feature/VerifiedPaymentConfirmationTest.php` | T/G: existing target synthetic idempotent paid/replay/cross-provider/shape checks; genuine vendor receipt pending. |
| 52 | `tests/Feature/WebsiteCredentialMediaFoundationTest.php` | T/G: preserve encryption/key-mismatch/redacted audit contracts; real settings secret write/rotation and role scope pending. |

**File reconciliation:** 27 app contracts/controllers/model/payment/services + 1 support + 1 config + 2 legacy logos + 3 CSS + 1 JS + 8 Blade + 1 fake + 8 feature tests = **52 unique paths**. The eight Blade rows are 36–43; the eight feature tests are 45–52. No POS source path, generated dependency or old source-business-data import is in scope. The two callback handlers have separate source paths but are deliberately consolidated to one constrained target route per gateway.

## All 16 historical W04 route entries (method is part of identity)

| # | Old Website route | Approved target disposition; remaining acceptance |
|---:|---|---|
| 01 | `GET admin/payments` | S/G → authenticated `GET /internal/admin/website/payment-settings` and channel status; permission-scoped owner/Admin view proof pending. |
| 02 | `PUT admin/payments` | S/G → **not** a current target write endpoint; add protected four-channel configuration/update/publish with version, recent-auth, authorization and audit before claiming parity. |
| 03 | `GET admin/payments/easypaisa` | S/G → protected masked provider/channel status; editable environment/merchant panel pending. |
| 04 | `PUT admin/payments/easypaisa` | S/G → no current write endpoint; allowed merchant settings/env, conflict and disable/enable policy pending. |
| 05 | `PUT admin/payments/easypaisa/credentials` | S/G → no current write endpoint; encrypted redacted secret set/rotate/clear with independent owner privilege pending. |
| 06 | `GET admin/payments/jazzcash` | S/G → protected masked provider/channel status; editable environment/merchant panel pending. |
| 07 | `PUT admin/payments/jazzcash` | S/G → no current write endpoint; allowed merchant settings/env, conflict and disable/enable policy pending. |
| 08 | `PUT admin/payments/jazzcash/credentials` | S/G → no current write endpoint; encrypted redacted secret set/rotate/clear with independent owner privilege pending. |
| 09 | `POST payments/easypaisa/callback` | S/G → `POST /api/v1/payment-callbacks/easypaisa` (one adapter-verified route); authentic callback signature/payload contract pending. |
| 10 | `POST payments/easypaisa/webhook` | S/G → same constrained Easypaisa callback route, deduped by provider event/ref; actual vendor webhook mapping/ack pending. |
| 11 | `POST payments/jazzcash/callback` | S/G → `POST /api/v1/payment-callbacks/jazzcash`; authentic signature/payload contract pending. |
| 12 | `POST payments/jazzcash/webhook` | S/G → same constrained JazzCash callback route, deduped by provider event/ref; actual vendor webhook mapping/ack pending. |
| 13 | `GET project-payment` | S/W05 → authenticated Next.js account/projects portal, project-owned GET `/api/v1/projects`; anonymous legacy token lookup intentionally not copied without approved business requirement. |
| 14 | `POST project-payment` | S/W05 → authenticated project/approved proposal lookup via owner-scoped `/api/v1/projects/{project}`; arbitrary client amount and open anonymous find are not admitted. |
| 15 | `GET project-payment/{token}` | S/W05 → project detail/proposal/milestone views guarded by Customer identity, not open bearer token; historical token-expiry equivalence requires W05 comparison. |
| 16 | `POST project-payment/{token}` | S/W05/G → owned `POST /api/v1/project-milestones/pay`, then owned `POST /api/v1/payments/{payment}/initiate`; amount/owner/expiry and full project-mode UI test pending, genuine provider receipt HOLD. |

**Route reconciliation:** 8 protected Admin settings routes + 4 provider callback/webhook routes + 4 legacy project-payment routes = **16**. The old `checkout` presentation, order-detail route and `Payment` model do not create extra W04 route assignments merely because their files have the W04 tag; they retain independent W03/W05 joins as specified in the frozen inventory. Do not re-expose obsolete source aliases as unauthenticated mutating target routes.

## Discovered implementation gaps and first next executable action

1. **Concrete target gap (not merely missing test):** only GET-only Website payment settings/channels currently exist. Build a protected non-secret channel/merchant configuration contract and accompanying Admin UI with version/conflict handling, permission/recent-auth, per-provider environment and publication/rollback audit; external adapters must remain OFF until separate owner/vendor approval. No secret acceptance or live activation may be inferred from synthetic config.
2. **Payment negatives still independent:** owned-order vs other Customer, tampered/reference/cross-provider/replay, disabled/mode, failed initiation recovery, refund-state and payment-vs-order reconciliation at HTTP and actual browser levels; preserve the already-PASS exact-source CI and avoid a duplicate unchanged run.
3. **H-02:** payment owner identity, processor choice, approved official wallet/card sandbox contracts, actual secure credentials, signed vendor callbacks and real refunds/settlement require explicit future inputs and acceptance. The crosswalk alone is not W04 family closure.

**First next bounded action:** implement and test the **non-secret**, protected Admin merchant/channel settings contract, keeping existing read-only GET endpoints and default-OFF external providers, then verify actual Admin UI/permission/negative cases before addressing separate official integration/secret activation gates. W04 and MT-7.5 remain IN PROGRESS at 15/27 DONE, 12 OPEN; MT-7.6 remains unstarted.
