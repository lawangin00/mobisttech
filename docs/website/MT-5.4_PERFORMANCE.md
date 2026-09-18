# MT-5.4 Performance Evidence

- Point: MT-5.4 - Dynamic public content and digital solutions.
- Build/runtime: Next.js 16.3.3 optimized production build on the local isolated acceptance stack; Microsoft Edge Playwright channel; mobile viewport 390x844.
- Published targets are unchanged from MT-5.1/MT-5.3: LCP <= 2.5 s, CLS <= 0.1 and separate lab interaction-event evidence <= 200 ms. Lab interaction duration is not represented as field INP.
- Initial cold diagnostic: direct managed-page navigation recorded LCP 2,508 ms, CLS 0, TTFB 2,452 ms and DOMContentLoaded/load 3,063 ms. Because LCP exceeded the target by 8 ms, this was retained as diagnostic evidence only and was not accepted as a PASS.
- A settled rerun before remediation recorded managed-page LCP 3,540 ms, CLS 0, TTFB 3,471 ms and DOMContentLoaded/load 4,083 ms. This disproved a cold-start-only explanation and exposed a real server-render request fan-out problem on the single-threaded Laravel acceptance server.
- Root cause: the dynamic `[slug]` route independently resolved metadata and page content, and each resolution fetched the full policy collection before the managed page. Digital Service and Software overview routes likewise duplicated their data loader between metadata and page rendering. These duplicate serial API calls inflated TTFB.
- Product remediation: request-scoped React `cache()` now deduplicates dynamic managed-page, service-detail and software-overview loaders. Dynamic `[slug]` resolution reuses the already-shared Website profile content index to distinguish policy slugs from managed pages, so ordinary managed pages no longer fetch the policy collection first; policy content is fetched only for a known policy slug.
- Post-remediation production acceptance, after a prior-page network-idle settle in an isolated browser context:
  - Managed case study `/mt54-case-study`: LCP 1,360 ms; CLS 0; max recorded interaction-event duration 16 ms; TTFB 1,313 ms; DOMContentLoaded/load 1,331 ms.
  - Digital Service detail `/services/mt54-web-development`: LCP 1,240 ms; CLS 0; max recorded interaction-event duration 0 ms; TTFB 1,177 ms; DOMContentLoaded 1,196 ms; load 1,197 ms.
  - Software overview `/software/mt54-software`: LCP 1,240 ms; CLS 0; max recorded interaction-event duration 0 ms; TTFB 1,189 ms; DOMContentLoaded 1,208 ms; load 1,209 ms.
- All three accepted representative routes meet the unchanged LCP, CLS and interaction budgets. No production API timeout or threshold was loosened to obtain PASS.
- The performance probe was acceptance-only; its temporary spec was removed after recording durable evidence. Standard deterministic Website acceptance fixtures performed global cleanup.
