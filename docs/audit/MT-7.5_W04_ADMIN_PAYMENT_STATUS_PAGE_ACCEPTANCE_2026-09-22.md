# MT-7.5 W04 — Admin payment status page acceptance (22-Sep-2026)

**Status: W04 IN PROGRESS; 15/27 milestones DONE, 12 OPEN.** This checkpoint supplements `MT-7.5_W04_ADMIN_PAYMENT_HTTP_ACCEPTANCE_2026-09-22.md` and `MT-7.5_W04_ROUTE_CROSSWALK.md`; it does not close source payment configuration parity or authorize any live payment provider.

## Implemented scope

- Protected read-only Inertia page: `GET /internal/admin/website/payment-settings`, rendered as `website-payment-settings` with an explicit Admin realm, authenticated identity middleware and throttling. `WebsitePaymentAdministration::overview()` independently requires `website.payments.manage` permission.
- The server supplies only the four-channel masked status projection (`code`, `label`, `enabled`, `merchant_configured`, `available`). Merchant identifiers, credentials, and rotation/activation controls are never supplied to this page. Enabled configuration and provider availability are separate states.
- Page response carries `Cache-Control: private, no-store`. The page has an explicit direct route and a Back to Platform Administration link; **no forward navigation link from the Platform Administration page was added** in this checkpoint.
- The existing `GET /internal/admin/website/payment-channels` JSON endpoint remains read-only and unchanged in contract. No external gateway or live credentials were configured.
- Source changes were committed sequentially: controller `2d53d229e7c7bdf39d6ea9432d147ab06fe8c52a`, route `7a8f2e18ad75efd62fb6efe978fc61d992386b62`, React page `790d4c8bfdf48d1996e90704568876f0a837a22c`, Inertia registration `0199f03fb56af11eca890e977ee98c95b6ad6083`, and HTTP regression `bd4f8fbfb7c820dc294802992892259f1fa668ae`.

## Verified evidence

- Exact full-CI request commit: `2630ba60c67fc6f8566853a609d1bae3e2b6e080`, with source commit `bd4f8fbfb7c820dc294802992892259f1fa668ae`. GitHub Actions full run: https://github.com/lawangin00/mobisttech/actions/runs/35682363890 . **Request validator and clean-checkout acceptance both completed SUCCESS**.
- Full backend regression includes HTTP denial of unauthenticated and unauthorized Admin requests to both payment-status API and new page; authorized Admin page returns 200, masked synthetic merchant/credential markers are absent from response, and cache directives are verified. PHP style, backend TS/production build, explicit MySQL race/reset, Website TS/lint/production build, public-bundle/secret checks, first-outlet browser fixture, POS/Admin browser, Website checkout/customer-project/public-content browser suites, performance, final disposable schema/cleanup and tracked-runtime-artifact gates all succeeded.
- Separately scoped W01/P02/W03 isolated browser-family steps were intentionally SKIPPED in this W04 full request; do not cite this run as fresh focused acceptance of those families. Separate GitHub-only Website verification run https://github.com/lawangin00/mobisttech/actions/runs/35682363874 completed SUCCESS.

## Remaining work and external hold

- The historical `GET admin/payments` source now has a partial target Admin read-only status page, **not** equivalent merchant settings, editing, credential lifecycle, revision/publish/rollback, delegated permission/audit surfaces or fully verified source route/file parity. Platform navigation and the 52 W04-tagged source-file dispositions also remain open.
- Real Easypaisa, JazzCash and hosted-card provider agreements, authorized credentials and signed callback/webhook, refund and bank-settlement evidence remain EXTERNAL UNVERIFIED / H-02 HOLD. Do not invent live verification or activate a fake gateway. W04 remains IN PROGRESS and the milestone count remains 15/27.
