# MT-7.5 W04 — protected Admin COD browser acceptance checkpoint (22-Sep-2026)

**State: In Progress; protected COD browser acceptance NOT YET PASSED.** MT-7.5 remains 15/27 DONE and 12 OPEN. W04 and H-02 remain open. No genuine external merchant adapter or credentials have been activated.

## Actual browser failure and retained positive evidence

- The original synthetic protected Admin COD draft/publish/reload/compensating-rollback browser test was added at source `c547e5684f51358755aef5e47093dbb27994c13c`. Full run `35762276392` concluded **FAILURE**: POS/Admin Playwright reported **36 passed, 1 skipped, 1 failed**. Its COD browser case received HTTP **303** at `POST /internal/admin/website/payment-settings/drafts`, but after five seconds **no `Latest draft v...` rendered**. HTTP 303 alone did not independently prove the policy row had been persisted. The separate permission-denial case passed. Backend regression passed 357 tests / 10,284 assertions; downstream browser/cleanup gates were skipped on that failure.

## Source remediation and exact CI results

- Source `69a82e6f056c6166ed2e2d38d62445a34918986d` replaces the ambiguous Inertia POST/303 mutation with an explicit same-origin authenticated Admin CSRF JSON POST. COD draft must return HTTP 201 and revision data; publish HTTP 200 and revision data. Only confirmed success triggers Inertia props rehydration. Playwright now verifies returned IDs/versions, effective COD, reload persistence, separate publish, a newer compensating rollback revision and unactivated external channels. The synthetic cleanup remains guarded to the disposable CI MySQL database and exact synthetic owner.
- Request-only commit `27bcab132fda14d258525b4758f811f22d36b762` pinned that source and triggered [full CI 35767738976](https://github.com/lawangin00/mobisttech/actions/runs/35767738976) and [Website CI 35767738993](https://github.com/lawangin00/mobisttech/actions/runs/35767738993). Website CI **terminal SUCCESS**: request validation, TypeScript, lint and production build. Full CI **terminal FAILURE before backend regression/browser**: `resources/js/pages/website-payment-settings.tsx(66,13)` reported TS2353 because `preserveState` is not in the typed `router.reload` options. This is a source type error, not proof of any provider, backend or browser failure in that attempt.
- Commit `55fa35c280dfafef38b11bef2ecf231e31e47b86` fixes the proven type error by using typed `router.visit('/internal/admin/website/payment-settings', {method:'get', preserveState:false, preserveScroll:true, replace:true, ...})` to rehydrate the Admin page after confirmed JSON write. It retains independent CSRF, nonsecret COD-only mutation, permission and audit boundaries. **This corrected commit has not yet passed its own pinned CI; do not infer success from the earlier Website build of a different commit.**

## First pending bounded action

Request and review an exact-source necessary full CI on `55fa35c280dfafef38b11bef2ecf231e31e47b86` when the next bounded execution window permits. Inspect terminal TypeScript, protected COD Playwright, final cleanup and all later gates; fix only evidenced defects. If full CI succeeds, record that result without closing W04: real merchant approval, official provider contracts and authentic callback/payment/refund remain H-02 HOLD. Do not touch production, credentials or protected legacy repositories.
