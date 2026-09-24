# MT-7.5 / W04 owned commerce retry double-click guard

Date: 24 September 2026 (PKT). LOCAL execution; scoped to the existing failed commerce retry on customer owned order detail.

The retry button previously relied on React busy state alone. Two same-tick clicks could submit distinct idempotency keys before disabled state rendered. Added synchronous ref guard for retry and concurrent continuation, resetting the guard after the awaited operation. Existing server-side owner, failed-state, reservation and provider checks remain authoritative.

Local evidence: Microsoft Edge focused retry browser 1/1 PASS; joined checkout browser 7/7 PASS with guarded fixture teardown and exit zero. The retry fixture holds the synthetic request for 350ms and triggers two clicks within one browser task, asserting exactly one retry request and recovery to the existing pending payment after synthetic hosted initiation failure. Website typecheck and git diff --check PASS.

No authentic provider/merchant activation, hosted CI dispatch, production data or protected legacy repository access. MT-7.5/W04 In Progress: 15/27 DONE, 12 OPEN; H-02 HOLD.
