# MT-7.5 W04 browser recovery — 21 September 2026

Status: IN PROGRESS; this checkpoint does not close W04 or its 52 historical files / 16 source routes.

## Previous incomplete work — verified on GitHub

- Source commits `e21fbe90ad8d6f006a593bf617b2ab938d95d390` and `b0b3e86` already corrected all four outdated fresh-product Qty assertions in `backend/tests/browser/mt75-fresh-next-smoke.spec.ts`, added a direct owned-order recovery link after checkout creates an order but provider initiation subsequently fails, and extended Website checkout browser coverage. These changes preceded this checkpoint; do not duplicate or overwrite them.
- Full hosted run `35634752024` on `f70e938cbf2159cc7dff2d6f93d6d41f9f5ccef1` still failed POS/Admin browser acceptance. The formerly first stale-Qty failure was no longer first: the fresh Next browser journey advanced to a legacy comparison URL and returned HTTP 500. Hosted logs also recorded `GET /api/v1/website-profile` HTTP 429, followed by browser failures and fixture/login issues. Backend/Pint/MySQL/build gates independently passed. This is not a full acceptance pass.

## Scoped source repair in GitHub mode

- Source commits `497aa1cf3998aa4660baa1ad2e9ab8906c342bd7` and `eda285c645021e4df1f004bfd57a09cd2b2b7ff8` change only `backend/app/Providers/AppServiceProvider.php` and `backend/playwright.config.ts` (verified two-file diff). Public API requests retain the existing aggregate 120/minute per-IP limit in all normal environments. Only the explicit `MT75_BROWSER_E2E=1` flag on the single isolated Laravel Playwright server, when Laravel environment is `testing` and remote IP is `127.0.0.1`, enables a 1200/minute synthetic fixture traffic limit. Production requests, external IPs and all non-browser PHP tests keep the original 120/minute limit. This does not assert authentic gateway acceptance.
- Exact-source `full` hosted CI request `.github/ci-requests/MT-7.5-W04-browser-isolated-public-throttle-eda285c.json` uses source `eda285c645021e4df1f004bfd57a09cd2b2b7ff8`, request commit `dd3c6064c9205f18b70bcada594225969a42009b`, run `35638577839`. Request validation and hosted Pint passed when recorded; other test gates were still running and must be checked before any pass claim.

## Independently open browser fixture conflict

`backend/tests/browser/mt75-fresh-next-smoke.spec.ts` and `backend/tests/browser/pos-first-outlet-fresh-owner.spec.ts` both log in using `mt75-fresh-owner@example.invalid` and expect to create outlet `001 · MT75 Fresh First Outlet` from an empty first-outlet baseline. `global-setup.ts` seeds the fresh owner only once. Running both journeys on the same mutable database cannot satisfy both empty-baseline assertions. Isolate their fixture ownership or database lifecycle before calling the monolithic POS/Admin browser suite accepted. Do not delete/skip either test or treat an HTTP-rate fix as this conflict's resolution.

## Remaining W04 gates

Verify current run terminal status and first remaining browser failure, isolate duplicate first-outlet fixtures without touching historical source/business data, then finish exact-count 52-file/16-route parity disposition and negative payment owner/reference/replay/refund/mode coverage. JazzCash, Easypaisa and hosted card stay default OFF; authentic provider sandbox, callbacks and settlement remain external HOLD.
