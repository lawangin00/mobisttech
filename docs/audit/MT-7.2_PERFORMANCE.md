# MT-7.2 Performance Evidence

Point: MT-7.2 - Security, performance and resilience audit.

## Environment

- Next.js 16.3.3 optimized production build on the isolated local acceptance stack.
- Laravel testing API on 127.0.0.1:18080 and Website production server on 127.0.0.1:13000.
- Microsoft Edge 154.0.4258.12.
- Playwright mobile viewport: 390x844.
- Lighthouse CLI 13.5.0, mobile Performance category.
- Published budgets were not changed: LCP <= 2.5 s, CLS <= 0.1, separate interaction evidence <= 200 ms, representative mobile Lighthouse Performance >= 90.

## Initial diagnostic miss

The first MT-7.2 all-mode probe stopped on hybrid mode at LCP 2,524 ms, 24 ms above the fixed 2,500 ms target. The result was retained as diagnostic evidence and was not accepted as PASS.

## Cause and remediation

The homepage loaded the canonical Website profile through the shared root layout/storefront context, but `website/src/app/page.tsx` independently fetched `/api/v1/business-profile` again. The Website profile already contains the canonical business projection, so the extra server-side request was unnecessary request fan-out on the LCP path.

Remediation: Home now consumes `readStorefrontContext()` and reuses its cached `{ website, business }` values instead of calling `readWebsiteProfile()` plus `readBusinessProfile()` independently. No API timeout, performance threshold, cache-consistency rule or data freshness contract was loosened.

## Post-remediation production Playwright evidence

Dedicated all-mode production test PASS 1/1:

- hybrid: LCP 1,952 ms; CLS 0; qualifying interaction event duration 0 ms; TTFB 1,881 ms; DOMContentLoaded 1,928 ms; load 1,932 ms; 10 browser requests.
- digital_only: LCP 1,272 ms; CLS 0; qualifying interaction event duration 0 ms; TTFB 1,228 ms; DOMContentLoaded 1,242 ms; load 1,243 ms; 10 browser requests.
- commerce_only: LCP 1,944 ms; CLS 0; qualifying interaction event duration 0 ms; TTFB 1,873 ms; DOMContentLoaded 1,887 ms; load 1,888 ms; 10 browser requests.

The interaction probe performs a real click with navigation prevented only for measurement isolation. Its PerformanceEventTiming observer threshold is 16 ms; a recorded maximum of 0 means no qualifying event reached 16 ms, not field INP=0. This is separate lab interaction evidence and Lighthouse is not used as an INP substitute.

## Mobile Lighthouse

Complete JSON reports are retained only in ignored `.local/mt72` audit workspace:

- hybrid: Performance 94; LCP 1,515 ms; CLS 0; TBT 262 ms.
- digital_only: Performance 95; LCP 1,508 ms; CLS 0; TBT 266 ms.
- commerce_only: Performance 94; LCP 1,507 ms; CLS 0; TBT 258 ms.

Additional connected-browser reports produced hybrid 93 and digital_only 95, supporting repeatability above the 90 target.

Lighthouse itself completed each page audit and wrote parseable reports. On this Windows host the direct Chrome/Edge launcher then sometimes exited nonzero because `chrome-launcher` could not remove its temporary profile immediately (`EPERM`). That teardown-only harness condition occurs after report generation; it does not change measured page scores. A remote-debugging Edge profile was tried as a cleanup workaround, but connection reuse could hang between audits, so direct-launch reports plus explicit report-integrity parsing are the accepted evidence. All audit Edge processes/profiles were then explicitly stopped/cleaned.

## Result

All three Website modes meet the fixed LCP, CLS, interaction and mobile Lighthouse targets after the request-fan-out remediation. No performance exception or threshold waiver is required.