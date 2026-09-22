# MT-7.5 W04 — Payment-status navigation acceptance (22-Sep-2026)

**Status: W04 IN PROGRESS; 15/27 DONE, 12 OPEN.** This checkpoint supplements `MT-7.5_W04_ADMIN_PAYMENT_STATUS_PAGE_ACCEPTANCE_2026-09-22.md`; it neither establishes historical merchant-settings parity nor authorizes external gateway activation.

## Scoped implementation

- `backend/resources/js/pages/platform-admin-payment-navigation.tsx` wraps the unchanged Platform Administration page, requesting the existing protected `GET /internal/admin/website/payment-channels` status API with same-origin credentials and no-store fetch. Only a successful response reveals the read-only `Payment channel status (read-only)` link to `/internal/admin/website/payment-settings`; denial or a request error leaves it hidden. The backend API/page independently enforce `website.payments.manage` authorization.
- `backend/resources/js/app.tsx` routes the existing `platform-admin` Inertia page through this wrapper, preserving other page mappings. No payment settings mutation, merchant credentials, live provider adapters or activation controls were introduced.
- `backend/tests/browser/website-payment-navigation.spec.ts` tests link visibility with *mocked* status responses (200 versus 403) using synthetic Admin browser sessions. Actual HTTP authorization and masked response protections are covered separately by the existing `backend/tests/Feature/W04AdminPaymentHttpTest.php`; mocked browser responses do not constitute authentic gateway verification.
- Source commits: wrapper `cb93205f5cc46b780b0ffb6725a9a05f37d3463a`, app route `8236601f030306be67bb9a475c45650811413b9f`, browser tests `4d769f4b6477ab3b03835518f59463e73819a91b`. Exact CI request `67fbac1229bd7d79b1b99913aac83260a83c14d8` targets test source `4d769f4b6477ab3b03835518f59463e73819a91b`.

## Verified evidence

- Full clean-checkout GitHub Actions CI: https://github.com/lawangin00/mobisttech/actions/runs/35685724561 — request validator and acceptance jobs **SUCCESS**. PHP style, backend TypeScript/production build, full backend regression, MySQL race/reset, Website TypeScript/lint/build, bundle/secret scans, fresh-owner first-outlet browser, POS/Admin browser **including both new navigation tests**, Website checkout/customer-project/public-content browsers, performance, final disposable schema/cleanup and tracked-runtime-artifact checks succeeded. W01/P02/W03 isolated family steps were intentionally skipped in this W04 full request; do not represent them as newly focused acceptances.
- Separate GitHub-only Website clean-checkout verification: https://github.com/lawangin00/mobisttech/actions/runs/35685724570 — request validator and Website build/static gates **SUCCESS**.
- A transient incomplete edit of `backend/app/Http/Controllers/PlatformAdministrationController.php` was fully reversed immediately: restoration commit `c7a072fbec74bd290bb01c1a5a48623d02dfbc11` restores its original blob byte-for-byte; GitHub compare from prior accepted evidence commit `43262c8fc4428eeb79c9e563bdb676da334bf8ea` to the restoration commit showed **zero net changed files**. The final verified navigation source does not modify that controller.

## OPEN / EXTERNAL HOLD

Historical source merchant-settings configuration/edit and protected encrypted credential rotation, publish/rollback, audit and source route/file parity remain OPEN. Authentic Easypaisa, JazzCash and selected hosted-card provider documentation, agreements, credentials, signed callbacks, refunds and bank settlement remain H-02 **EXTERNAL UNVERIFIED / HOLD**. External channels remain default-OFF; synthetic browser/HTTP acceptance must not be presented as real-provider approval. The MT-7.5 milestone count remains 15/27.
