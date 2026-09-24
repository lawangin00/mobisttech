# MT-7.5 / W04 failed commerce retry continuation recovery

Date: 24 September 2026 (PKT). Project 282dba2f-a2d9-47e8-aa8d-e499fbe1706c; repository lawangin00/mobisttech; LOCAL execution.

After a definitively failed commerce order, the authorized retry endpoint creates and persists a new pending payment intent before the separate hosted initiation request. If that initiation fails, previously the order detail retained its old failed snapshot and still displayed Retry with JazzCash; a second click would target an invalid new charge attempt instead of the already-owned pending intent.

The owned order page now refreshes its authoritative order/payment state in the retry error path while preserving the provider failure message. A pending new intent exposes Continue payment and suppresses duplicate retry. The server remains the payment/owner/stock/availability authority; no merchant or mode changed.

Local acceptance: Website typecheck PASS; isolated Microsoft Edge focused checkout test 1/1 PASS; joined checkout browser 7/7 PASS (exit 0) with guarded synthetic fixture teardown; git diff --check PASS. The joined test simulates a persisted pending replacement payment followed by HTTP 502 on provider initiation, checks original error, Continue payment visibility, absence of Retry and exactly one retry request. No hosted CI requested, no real provider activation, no source/production data touched.

MT-7.5/W04 In Progress, 15/27 DONE / 12 OPEN; authentic external providers H-02 HOLD.
