# MT-7.5 W04 — owned-order Continue payment double-click acceptance (24-Sep-2026)

Status: scoped W04 Website UI regression corrected; MT-7.5/W04 remains In Progress, 15/27 done and 12 open. H-02 external merchant/callback/refund/settlement HOLD unchanged.

## Reproduced failure

The existing synthetic hosted-initiation failure-to-owned-order browser journey was extended to issue two immediate Continue payment DOM clicks while a stubbed initiation HTTP response was deliberately pending for 350 ms. Before the correction the expected initial checkout initiation plus one continuation became **three** total payment-initiation requests, not two. The request routing and owned-order payload were browser fixtures; no real merchant, provider or money was involved.

## Scoped correction

`website/src/components/customer-order-detail.tsx` now uses a synchronous `useRef` in-flight guard plus its existing busy/disabled UI state for Continue payment. A duplicate click while the first initiation is pending is ignored; an initiation failure restores the button and retains the existing visible error/recovery path. Retry, cancellation, actual payment authorization, backend idempotency, external gateway registration and configuration were not changed.

## Verification

- Red browser regression before correction: 1 failure, expected 2 total initiation requests, received 3; isolated teardown completed.
- Corrected focused checkout recovery browser test: 1/1 PASS, isolated setup/teardown complete.
- Complete adjacent Website checkout browser suite: 6/6 PASS, isolated setup/teardown complete; includes COD, recovery, unsafe hosted redirect, all-unavailable channels, cancelled external order and digital-only mode.
- Backend `npm run typecheck`: PASS; Website `npm run typecheck`: PASS; `git diff --check`: PASS.
- These browser-only gateway stubs do not establish authentic provider, credential, refund, settlement or full project acceptance. Existing H-02 HOLD and 15/27 W04-stage progress remain unchanged.
