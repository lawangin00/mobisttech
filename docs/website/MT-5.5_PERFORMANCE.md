# MT-5.5 Performance Evidence

- Point: MT-5.5 - Client project portal and digital conversion journeys.
- Runtime: optimized Next.js production build on the isolated local acceptance stack; Microsoft Edge Playwright channel; authenticated Customer session; 390x844 mobile viewport.
- Published acceptance thresholds remained unchanged: LCP <= 2.5 s, CLS <= 0.1 and separate lab interaction-event evidence <= 200 ms. Lab interaction duration is not represented as field INP.
- Diagnostic attempt 1, hybrid mode: LCP 1,904 ms, CLS 0.205, max interaction 0 ms, TTFB 576 ms, DOMContentLoaded 592 ms, load 597 ms. LCP and interaction passed, but CLS failed and this run was not accepted.
- Root cause: the client project route initially rendered a one-line loading main, allowing the footer into the mobile viewport; project hydration then expanded the page and shifted the footer. Product remediation reserves stable viewport geometry with the same min-height shell for loading and loaded portal states.
- Attempt 2 ended before metrics because the temporary probe used an unsynchronized 5 s post-login heading assertion. The accepted retry reused the canonical Customer account/projects response synchronization; no product timeout or performance threshold was changed.
- Accepted settled production sample:
  - hybrid: LCP 1,920 ms; CLS 0; max interaction 0 ms; TTFB 573 ms; DOMContentLoaded 588 ms; load 596 ms.
  - digital_only: LCP 1,872 ms; CLS 0; max interaction 0 ms; TTFB 561 ms; DOMContentLoaded 576 ms; load 576 ms.
  - commerce_only: LCP 1,908 ms; CLS 0; max interaction 0 ms; TTFB 579 ms; DOMContentLoaded 594 ms; load 595 ms.
- All three modes meet the unchanged LCP, CLS and interaction budgets. External payment providers remained truthfully unavailable; no provider was activated to obtain acceptance.
- No new Lighthouse score is claimed for this authenticated private route. Existing all-mode public Website Lighthouse evidence remains unchanged; this point directly measures the private authenticated route's LCP/CLS/interaction behavior under the production build.
- The temporary performance probe was removed after acceptance. Deterministic teardown left zero MT-5.5 synthetic service, project and MT-5.2 Customer fixture rows.
