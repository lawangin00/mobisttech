# MT-5.3 Performance Evidence

- Point: MT-5.3 - Checkout and customer payment flows.
- Build/runtime: Next.js optimized production build on local isolated testing stack; Microsoft Edge 154.0.4258.12; mobile viewport 390x844.
- Authentication/state: real Customer session, real durable cart, fixed four-channel Checkout projection, COD create, and owned order-detail route. External providers remained truthfully disabled under H-02.
- Initial diagnostic sample intentionally retained: immediate navigation from a still-busy account page on single-threaded `php artisan serve` produced Checkout LCP 3,256 ms / CLS 0.205 and order-detail LCP 3,832 ms / CLS 0.1001. Trace/timing showed TTFB 3.2-3.8 s because outstanding parallel Customer API requests were queued ahead of SSR profile requests in the single-thread test server. This was not accepted as performance evidence.
- Product remediation: Checkout and order-detail loading states now reserve stable layout height; CLS fell to 0. Root storefront context is React-memoized so metadata/layout consumers share the same request-local profile work.
- Representative settled authenticated lab sample after prior-page network idle:
  - Checkout: LCP 1,172 ms; CLS 0; max recorded interaction-event duration 32 ms; TTFB 1,141 ms; DOMContentLoaded 1,154 ms; load 1,161 ms; 8 JS requests / 144,208 encoded bytes; 2 Customer API requests.
  - Owned order detail: LCP 1,176 ms; CLS 0; max recorded interaction-event duration 24 ms; TTFB 1,146 ms; DOMContentLoaded 1,161 ms; load 1,167 ms; 8 JS requests / 143,287 encoded bytes; 2 Customer API requests.
- Representative authenticated mobile Lighthouse on the fixed Edge profile and production `/checkout` route: Performance 95; LCP 1,510 ms; CLS 0; TBT 233 ms; FCP 760 ms; Speed Index 2,955 ms.
- The recorded interaction-event durations are lab evidence and are not mislabeled as field INP. They are below the <=200 ms interaction target.
- Published performance targets are met in this environment: LCP <=2.5 s, CLS <=0.1, representative mobile Lighthouse >=90, and separate interaction evidence <=200 ms.
- Production API timeout defaults were not loosened to obtain PASS. The existing test-harness-only `WEBSITE_API_TIMEOUT_MS=12000` remains limited to the single-threaded local Laravel acceptance server.
