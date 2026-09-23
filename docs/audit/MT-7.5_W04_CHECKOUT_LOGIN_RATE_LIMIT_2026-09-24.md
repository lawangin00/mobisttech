# MT-7.5 / W04 checkout login fixture rate-limit correction (24-Sep-2026 PKT)

Scope: isolated testing fixture only. No production throttles, payment processing or protected source repository changed.

## Terminal baseline and failure identity
- GitHub Actions run `35932433520` on explicit request `5993cfb734bf60854154794eb7b9b5badb2921c5` completed FAILURE in Website checkout Playwright: first five checkout journeys passed; sixth test (`MT-5.3 digital-only mode prunes checkout while historical account stays available`) received HTTP 429 for customer login, expected 200. Clean PHP style, full backend regression, MySQL race/reset, builds, secret scan, fresh-owner and POS/Admin browser gates passed. Later Website groups and final schema gate were skipped.
- All six tests share the same synthetic customer identity and localhost identity route/IP. The real identity limiter is five requests per minute for that identity/path/IP; the browser global setup cleared its disposable cache once before all six tests. The sixth request was the synthetic sixth login, not a payment-provider failure.

## Material correction, bounds and local acceptance
- Before only the sixth independent digital-only browser journey, invoke existing `php artisan cache:clear --env=testing`. The action resets the disposable test limiter/cache state between journeys. Production identity/public/customer rate limit policies, login controller and per-journey assertions are unchanged; tests 1-5 still run under the existing five-per-minute limit.
- Executed the complete six-test Website checkout Playwright suite through local Microsoft Edge on isolated `mobisttech_test` MySQL port 13306 with disposable seed/teardown, `APP_ENV=testing`, existing `website` build and isolated localhost servers. Result: **6/6 PASS (1.5m)**, including the previously failing digital-only journey and successful fixture teardown. The tracked working tree contained only the intended test and ledger changes after the test; no running test web servers remained.
- This local browser success is not evidence of a new clean hosted Chromium pass or authentic payment settlement. A new exact-source hosted run is necessary to close the previously failed hosted acceptance, and cannot be claimed before its terminal result. Existing provider H-02 HOLD remains.
