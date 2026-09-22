# MT-7.5 W04 — historical Website payment source-file crosswalk (22-Sep-2026)

**Disposition inventory complete; functional parity NOT closed.** This table reconciles the **52 Website files explicitly tagged `W04`** in the immutable `docs/migration/SOURCE_SYMBOL_INVENTORY.json` (source Website commit `04e7c49518f9f11f60c83ad44f9f4e2fd2539066`). These are historical source paths, **not** paths to be copied or claimed to exist in the target. All 52 remain separately counted even when one target service absorbs multiple files. Route-by-route disposition of the **16 distinct tagged source routes** is in `MT-7.5_W04_ROUTE_CROSSWALK.md`; neither number describes current target route count.

**Disposition vocabulary:** `ADAPT` = target counterpart identified, but only specified tests/contracts evidenced; `GAP` = material target parity not demonstrated; `HOLD` = genuine external provider contract/callback/settlement/refund cannot be accepted using fakes; `REPLACE` = historical UI/asset/test implementation replaced or retired with retained behavioral requirement (no byte-identical or visual parity assertion). `ADAPT + HOLD` does not imply production payment readiness. Historical source tests are characterizations, not target passes.

## A. Interfaces, payment controllers, and project payment (9)

| # | Pinned source path | Target disposition and evidence boundary |
|---:|---|---|
| 01 | `app/Contracts/Payments/HostedCardGateway.php` | **ADAPT + HOLD:** target `backend/app/Commerce/PaymentProvider.php` / `PaymentProviders.php` separate provider-specific adapter registration; actual selected hosted-card processor and authentic gateway contract remain unconfigured. |
| 02 | `app/Contracts/Payments/PaymentProvider.php` | **ADAPT:** target `backend/app/Commerce/PaymentProvider.php`, `PaymentProviders.php`, `OrderTransactions.php` implement common initiation/verification and durable receipt contracts; source methods must not be assumed identical, especially refund/status/cancel network operations. |
| 03 | `app/Http/Controllers/Admin/EasypaisaConfigurationController.php` | **GAP + HOLD:** no verified protected Easypaisa merchant/account settings or encrypted credential replacement Admin UI/API in target; `backend/config/commerce.php` default-OFF is not an editable account. |
| 04 | `app/Http/Controllers/Admin/JazzCashConfigurationController.php` | **GAP + HOLD:** no verified protected JazzCash merchant/account settings or encrypted credential replacement Admin UI/API in target; default-OFF registry is not an editable account. |
| 05 | `app/Http/Controllers/Admin/PaymentsCheckoutController.php` | **GAP:** fixed four-channel Website registry exists in `backend/config/commerce.php`, `PaymentProviders.php` and `website/src/components/customer-checkout-form.tsx`, but source Admin publishable four-channel settings, limits, permissions and revision lifecycle have no proven equivalent. |
| 06 | `app/Http/Controllers/CheckoutController.php` | **ADAPT:** customer `GET /api/v1/checkout/channels`, `POST /api/v1/orders`, and Next `customer-checkout-form.tsx`; focused four-channel COD and hosted synthetic recovery Playwright are accepted. Source controller URL/guest-cookie compatibility not implied. |
| 07 | `app/Http/Controllers/EasypaisaCallbackController.php` | **ADAPT + HOLD:** target `POST /api/v1/payment-callbacks/easypaisa` and `OrderTransactions::callback` support registered verifier/receipt semantics; source callback/webhook are consolidated and no authentic Easypaisa signed packet has been accepted. |
| 08 | `app/Http/Controllers/JazzCashCallbackController.php` | **ADAPT + HOLD:** target `POST /api/v1/payment-callbacks/jazzcash` and `OrderTransactions::callback` support registered verifier/receipt semantics; source callback/webhook consolidated; authentic JazzCash transport/signature unverified. |
| 09 | `app/Http/Controllers/ProjectPaymentController.php` | **ADAPT + GAP + HOLD:** target account-owned `GET /api/v1/projects/{project}`, `POST /api/v1/project-milestones/pay`, payment initiation and Next `customer-project-portal.tsx` preserve scoped milestone/HTTPS continuation; source public token lookup/deep-link semantics require joint W05 disposition and authentic provider remains HOLD. |

## B. Model and provider implementations (10)

| # | Pinned source path | Target disposition and evidence boundary |
|---:|---|---|
| 10 | `app/Models/Payment.php` | **ADAPT:** shared `payments`, `orders`, `payment_receipts` storage is driven by `OrderTransactions.php`; new-system records only, historical source payment IDs/rows are not imported. |
| 11 | `app/Payments/AbstractPendingPaymentProvider.php` | **ADAPT + HOLD:** `PaymentProviders.php` provider registry and `OrderTransactions::initiate` enforce pending-intent/reference handling; source abstraction is not copied and real external implementation not verified. |
| 12 | `app/Payments/CashOnDeliveryPaymentProvider.php` | **ADAPT:** `OrderTransactions::collectCod` performs authorized, idempotent internal COD receipt, invoice and sale flow; source class is superseded by shared service. |
| 13 | `app/Payments/EasypaisaPaymentProvider.php` | **HOLD:** no genuine Easypaisa merchant network adapter/response signatures validated; only synthetic `PaymentProvider` registration tested and provider remains default-OFF. |
| 14 | `app/Payments/Exceptions/UnsupportedPaymentOperation.php` | **ADAPT + HOLD:** target `PaymentProviders.php` and `OrderTransactions.php` fail closed for unsupported/unavailable provider operations; individual real provider refund/cancel/status capabilities remain unverified. |
| 15 | `app/Payments/HostedCardPaymentProvider.php` | **HOLD:** target card is default-OFF; no selected authentic card processor, real hosted-card redirect/callback/refund, or local raw PAN/CVV collection is permitted. |
| 16 | `app/Payments/JazzCashPaymentProvider.php` | **HOLD:** no genuine JazzCash merchant adapter or provider-signed network callback verified; synthetic provider contract is not equivalent to integration. |
| 17 | `app/Payments/PaymentProviderResult.php` | **ADAPT:** target `PaymentProvider.php` verified event arrays, `OrderTransactions::applyReceipt` and durable `payment_receipts` replace source result DTO; only documented status/amount/reference/replay checks accepted. |
| 18 | `app/Payments/UnconfiguredHostedCardGateway.php` | **ADAPT + HOLD:** default-OFF card entry in `backend/config/commerce.php` fails closed without a registered authenticated adapter; actual processor selection remains H-02. |
| 19 | `app/Payments/WalletPendingPaymentProvider.php` | **ADAPT + HOLD:** shared pending/initiated/failed/reconciliation state machine in `OrderTransactions.php`; genuine JazzCash/Easypaisa wallet provider-specific behaviors not accepted. |

## C. Settings, receipt services, secret registration, configuration (10)

| # | Pinned source path | Target disposition and evidence boundary |
|---:|---|---|
| 20 | `app/Services/CardConfiguration.php` | **GAP + HOLD:** fixed default-OFF card configuration exists, but source runtime processor selection, merchant controls, Admin publishing and genuine hosted gateway setup lack target acceptance. |
| 21 | `app/Services/CashOnDeliveryCollectionService.php` | **ADAPT:** `OrderTransactions::collectCod`, POS authorized sale and `OrderPaymentTransactionsTest.php` test COD collection/replay; authenticated operator and amount/outlet checks remain required. |
| 22 | `app/Services/CashOnDeliveryConfiguration.php` | **ADAPT + GAP:** `backend/config/commerce.php` and four-channel checkout UI expose COD, but source Admin-configurable COD bounds/label/instructions/revision behavior not proved. |
| 23 | `app/Services/EasypaisaConfiguration.php` | **GAP + HOLD:** default-OFF registry is not a substitute for protected configurable merchant account and provider-specific activation blockers. |
| 24 | `app/Services/JazzCashConfiguration.php` | **GAP + HOLD:** default-OFF registry is not a substitute for protected configurable merchant account and provider-specific activation blockers. |
| 25 | `app/Services/PaymentManager.php` | **ADAPT + HOLD:** target `PaymentProviders.php` + `OrderTransactions.php` handle registry, durable initiation/receipt, replay, COD and synthetic external payment security; original refund/status/cancel provider API methods require separate parity, genuine network unverified. |
| 26 | `app/Services/VerifiedPaymentService.php` | **ADAPT + HOLD:** target `OrderTransactions::callback` / receipt application and targeted `OrderPaymentTransactionsTest.php` cover amount, signature, replay, paid/reconciliation and stock/sale effects under synthetic fakes; authentic vendor callbacks/settlement remain HOLD. |
| 27 | `app/Services/WebsiteCredentials.php` | **GAP + HOLD:** no verified encrypted, masked, permissioned Website merchant-credential write/rotation and runtime account-switching target contract; never copy source secrets. |
| 28 | `app/Support/WebsiteCredentialRegistry.php` | **GAP + HOLD:** source provider-specific credential allowlist, masking and key-rotation controls need target design and acceptance with authentic provider contracts. |
| 29 | `config/payments.php` | **ADAPT + HOLD:** `backend/config/commerce.php` explicitly registers COD and three default-OFF external channels; not evidence of source Admin-configurable values or an authentic provider connection. |

## D. Historic visual assets and JavaScript (6)

| # | Pinned source path | Target disposition and evidence boundary |
|---:|---|---|
| 30 | `public/brand/card-payment-logo.svg` | **REPLACE / B01:** historic logo need not be copied; target checkout text label is present, approved payment-icon asset licensing/appearance must be checked separately if used. |
| 31 | `public/brand/jazzcash-logo.svg` | **REPLACE / B01:** historical provider logo asset not automatically authoritative; target JazzCash text label works without copying source art; future brand/logo use requires asset review. |
| 32 | `public/css/checkout-selection.css` | **REPLACE / W07:** Next `customer-checkout-form.tsx` and shared UI styles supersede Blade selector CSS; four-radio availability/disabled behavior has Playwright evidence, visual pixel parity unclaimed. |
| 33 | `public/css/payment-brand.css` | **REPLACE / B01 + W07:** old payment branding stylesheet not copied; Next checkout payment labels and shared theme replace presentation, payment brand review remains separate. |
| 34 | `public/css/payment-fields.css` | **REPLACE / W07:** Next checkout forms replace old CSS; source field styling and edge/responsive behavior must not be assumed visually identical. |
| 35 | `public/js/payment-fields.js` | **REPLACE:** Next `customer-checkout-form.tsx` uses controlled channel selection and HTTPS hosted continuation; do not carry forward raw-card input or obsolete DOM hooks. |

## E. Historical Blade views (8)

| # | Pinned source path | Target disposition and evidence boundary |
|---:|---|---|
| 36 | `resources/views/admin/payments-checkout.blade.php` | **GAP / W07:** no proven target Admin merchant-channel settings/limits/publishable revision UI; four-channel consumer checkout does not close Admin parity. |
| 37 | `resources/views/admin/payments-easypaisa.blade.php` | **GAP + HOLD / W07:** no verified masked credential + nonsecret Easypaisa Admin configuration panel; default-OFF maintained. |
| 38 | `resources/views/admin/payments-jazzcash.blade.php` | **GAP + HOLD / W07:** no verified masked credential + nonsecret JazzCash Admin configuration panel; default-OFF maintained. |
| 39 | `resources/views/checkout.blade.php` | **REPLACE / ADAPT:** Next `customer-checkout-form.tsx` renders available four approved channels; COD/disabled-provider flow and synthetic hosted failure/unsafe URL recovery tested; old Blade not copied. |
| 40 | `resources/views/order-payment.blade.php` | **REPLACE / ADAPT / W03:** Next `customer-order-detail.tsx` provides owned pending-payment continuation and failed-payment retry; exact source page/deep-link and browser negative cross-owner acceptance remain separate. |
| 41 | `resources/views/partials/payment-options.blade.php` | **REPLACE / ADAPT:** fixed `PaymentProviders.php` four-channel catalogue rendered by `customer-checkout-form.tsx`; no Website bank transfer or split tender, local PAN/CVV UI not accepted. |
| 42 | `resources/views/project-payment-lookup.blade.php` | **REPLACE + GAP / W05:** account-owned Next project portal replaces public source reference lookup; original token discovery/deep-link and privacy contract require explicit joint W05 disposition. |
| 43 | `resources/views/project-payment.blade.php` | **REPLACE / ADAPT + GAP / W05:** owned `customer-project-portal.tsx` + milestone/pay API replace token payment view; original token semantics and provider-authentic payments remain open. |

## F. Historical source fake and feature tests (9)

| # | Pinned source path | Target disposition and evidence boundary |
|---:|---|---|
| 44 | `tests/Fakes/FakeHostedCardGateway.php` | **REPLACE / Q01:** target `FakePaymentProvider` in `backend/tests/Feature/OrderPaymentTransactionsTest.php` and Playwright response stubs are isolated simulations; fake receipts never count as authentic provider acceptance. |
| 45 | `tests/Feature/CashOnDeliveryProviderTest.php` | **ADAPT / Q01:** target `OrderPaymentTransactionsTest.php` COD checkout, authorized collection and replay, plus `backend/tests/browser-website/checkout.spec.ts` COD UI; source assertions not automatically ported one-to-one. |
| 46 | `tests/Feature/EasypaisaAdapterConfigurationTest.php` | **GAP + HOLD / Q01:** target synthetic multi-provider test includes Easypaisa amount/signature/replay, but original protected Admin nonsecret settings, owner-only secret replacement and reauthentication cases have no verified counterpart. |
| 47 | `tests/Feature/HostedCardSecurityTest.php` | **ADAPT + GAP + HOLD / Q01:** card stays default-OFF, Next checkout has no local card-entry fields and synthetic provider negative tests exist; crafted raw-card POST rejection and authentic hosted processor integration require explicit target-specific evidence. |
| 48 | `tests/Feature/JazzCashAdapterConfigurationTest.php` | **GAP + HOLD / Q01:** target synthetic multi-provider test includes JazzCash amount/signature/replay, but original protected Admin settings, owner-only credential replacement and reauthentication cases have no verified counterpart. |
| 49 | `tests/Feature/PaymentManagerContractTest.php` | **ADAPT + GAP / Q01:** target `OrderPaymentTransactionsTest.php` checks fixed four channels, pending initiation replay and unsupported state/disabled-provider cases; source unsupported provider cancellation/refund/status cases require independent mapping. |
| 50 | `tests/Feature/PaymentsCheckoutAdminTest.php` | **GAP / Q01:** target browser/backend proves consumer four-channel ordering/default-OFF, NOT the original Admin four-method publish, COD bounds and unknown-key/permission matrix. |
| 51 | `tests/Feature/VerifiedPaymentConfirmationTest.php` | **ADAPT + HOLD / Q01:** target verified synthetic paid/replay/amount/reference/stock-reconciliation coverage in `OrderPaymentTransactionsTest.php`; untested source cases and genuine vendor signed payloads remain open. |
| 52 | `tests/Feature/WebsiteCredentialMediaFoundationTest.php` | **GAP / Q01 + W07:** target Website payment credentials' encrypted storage, key mismatch, masked media and admin recovery need explicit feature acceptance; original source test alone is not target proof. |

## Reconciliation and outstanding acceptance

- **52/52 exact W04-tagged source files** individually listed: 9 interfaces/controllers + 10 model/providers + 10 services/config + 6 visual assets + 8 Blade views + 9 source tests = 52. This records disposition only; `GAP` and `HOLD` are deliberately unresolved. Cross-family W03/W05/W07/B01/Q01 requirements are not silently marked complete.
- The separate 16/16 source-route crosswalk is `MT-7.5_W04_ROUTE_CROSSWALK.md`. Historical `OrderController@checkout` routes tagged W03 must not be relabeled W04 to pad this count.
- Accepted hosted synthetic CI `35673288185` proves a limited target checkpoint, including refused insecure hosted redirect, preserved owned-order recovery and passing final disposable-schema gate. It does not prove real JazzCash, Easypaisa or card callbacks, refunds, settlement, merchant credentials or admin account switching.
- **Next functional gates:** design and test protected provider settings/credential lifecycle without fake activation; perform remaining source-negative-case-to-target test mapping; preserve genuine provider H-02 HOLD pending authorized contracts and signed sandbox fixtures. W04 remains **IN PROGRESS**, stage count remains **15/27 DONE; 12 OPEN**. No production payment code, user records, POS source or external-provider configuration was changed by this documentation-only crosswalk.
