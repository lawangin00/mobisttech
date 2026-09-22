# MT-7.5 W04 — closed provider registry acceptance (22-Sep-2026)

**W04 IN PROGRESS: 15/27 DONE, 12 OPEN.** This checkpoint is a target security gate, not merchant-credential or authentic provider acceptance.

## Exact source and hosted verification

- Source change `e6c8062b2cc10416490147506656f5692648e937`: `backend/app/Commerce/PaymentProviders.php` now registers adapters only for `jazzcash`, `easypaisa` and `card`, and rejects duplicate provider-key registration before an existing verifier could be silently replaced. COD remains internal and cannot register an external adapter.
- Synthetic negative regression `b6ad42ac907343e33bf8339053c5d4ab029a8f3c`: `backend/tests/Feature/W04PaymentProviderRegistryTest.php` rejects undeclared gateway slugs and attempted overwrite, verifies that the original synthetic adapter remains in charge of initiation/verification, and preserves the exact four checkout labels/codes.
- Explicit full acceptance request `30618177401d2c7b28aad99bd3ee00e249bfbef3` identifies exact source `b6ad42ac907343e33bf8339053c5d4ab029a8f3c`. Hosted [full CI `35687903011`](https://github.com/lawangin00/mobisttech/actions/runs/35687903011): **request validator SUCCESS; clean-checkout acceptance SUCCESS**, including PHP style, backend build and regression, explicit MySQL race/reset, Website build and secret scan, first-outlet browser, POS/Admin and Website checkout/customer/project/public-content browser suites, performance budgets, final disposable schema/cleanup and tracked-runtime-artifact gate. W01/P02/W03 separately focused browser-family steps were intentionally SKIPPED, not freshly accepted by this W04 request.
- Separate [GitHub-only Website verification `35687903016`](https://github.com/lawangin00/mobisttech/actions/runs/35687903016): validator and clean-checkout Website TypeScript/lint/production build SUCCESS. The design and source-route crosswalk document commits after the CI request are documentation only, not an additional application-code acceptance run.

## Boundaries and next gates

- No real wallet/card adapter, merchant ID or credential was installed. Tests use synthetic provider objects and must not be presented as authentic JazzCash, Easypaisa or card verification; all three remain default-OFF.
- Accepted read-only Admin payment status and navigation do not provide editable merchant settings, encrypted credential storage/rotation or provider activation. The credential lifecycle's `MT-7.5_W04_CREDENTIAL_LIFECYCLE_DESIGN.md` records separately required real-provider contract evidence and owner-specific authority; the current owner binding is not enrolled by default. Do not infer payment-owner authority solely from a delegated Full Access role, `website.payment-credentials.manage` or offline owner recovery's independent enrollment.
- Outstanding: protected vendor-specific credential settings and lifecycle, nonsecret revision/publish/rollback, any remaining source negative-case gaps, joint W05 historical token disposition, and genuine signed callback/refund/settlement acceptance under H-02 HOLD. Do not close W04 or change 15/27 stage count based on this security checkpoint.
