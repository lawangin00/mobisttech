# MT-7.5 W04 — callback/configuration shape and P02 browser targeted correction (22-Sep-2026)

**W04 IN PROGRESS; 15/27 DONE, 12 OPEN.** This checkpoint accepts its scoped synthetic callback/configuration and P02 browser regression only. It does not close W04 or authorize an external provider.

## Scope and earlier failure

- Previous full CI run `35751789253` on source `c717bc547e9b7d28b60b10ea67fc64790731d60c` finished FAILURE at the unrelated P02 `pos-master-data-variants.spec.ts:19` inventory navigation (`page.goto`, `net::ERR_ABORTED`). Its 353 backend tests / 10,190 assertions and MySQL/static gates passed; later Website browser and final cleanup were skipped. The original event-type test was not the observed failure.
- W04 source additions: `backend/tests/Feature/W04CallbackRouteAllowlistHttpTest.php` at `e4d1c9574336e9a2a8c56f8d31a857a92e81c205`; `PaymentProviders::assertAvailable` merchant/mode fail-closed shape hardening at `ad5c5f6abbc5c77aa7ec87fa998554f074b52f40`; `W04ProviderConfigurationShapeTest.php` at `499c5bd1fd689f97406b4cc5b57004b4994a0eff`. Corrected P02 browser test at `70ccc51acfdbf6dda4849fbd703992238e7b7ee9` now uses the actual visible Inventory navigation after outlet selection. No authentic provider integration or live credential was introduced.

## Terminal exact-source verification

- Explicit request-only commit `afb012fb20ea369d813f79be9781b24d7d5b5137` pins `source_commit=34bfee0a5dd38b32df342d849877d6ac8c886103`, the approved project ID, `stage_id=MT-7.5`, `gate=full`, `reason=necessary`.
- [Full clean-checkout run 35753196003](https://github.com/lawangin00/mobisttech/actions/runs/35753196003): **terminal SUCCESS**. Request validation and clean checkout acceptance both completed successfully. PHP style, full backend regression (including new W04 HTTP/config tests), MySQL race/reset, backend and Website static/build, public/secret budgets, protected fresh-owner first-outlet browser, all POS/Admin Playwright (including corrected P02), Website checkout/customer/project/public browser, Website performance, final schema/cleanup and tracked-artifact guards each completed successfully. The unrelated P02 `ERR_ABORTED` signature did not recur in this exact candidate. Family-specific W01/P02/W03 isolated browser steps were intentionally skipped by this full-scope request; their separate prior acceptance is not reclassified by those skips.
- [Independent Website-only run 35753196204](https://github.com/lawangin00/mobisttech/actions/runs/35753196204): **terminal SUCCESS** for its request validation and Website TypeScript/lint/production build. This is additional static/build evidence, not a substitute for full browser acceptance.

## First genuinely pending W04 action

Continue source-to-target disposition for all 52 historical W04 files and 16 source routes and remaining independent owner/reference/replay/refund/operating-mode and recovery acceptance. Record precise new evidence rather than repeating these passing gates. Authentic JazzCash/Easypaisa/card merchant contracts, actual signed provider callbacks, real refunds/settlement, and payment-owner enrollment remain H-02 HOLD pending separate owner decisions and official provider inputs. COD alone remains configured; no real external payment activation is claimed.
