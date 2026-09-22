# MT-7.5 / W04 — read-only payment-status HTTP mutation gate (22-Sep-2026)

**Status: IN PROGRESS; acceptance not yet established.** The parent MT-7.5 point and W04 remain IN PROGRESS at 15/27 milestones DONE and 12 OPEN. No live provider was activated and no merchant credential was configured.

## Durable scoped change

- Added `backend/tests/Feature/W04PaymentSettingsReadOnlyHttpTest.php` in commit `ae993962e3e764d17197c0aeaa9bb09e90b3d8bc`. For the existing `GET`-only Admin payment-status HTML and JSON routes, the test sends POST, PUT, PATCH and DELETE with synthetic forbidden merchant/credential payloads. Each request must return HTTP 405 and leave the in-process provider configuration unchanged. It does not test a genuine provider, a credential store, or an authenticated editable merchant-settings workflow.
- Requested one exact-source full clean-checkout CI via `.github/ci-requests/MT-7.5-W04-readonly-payment-mutation-ae99396.json` at commit `c7c26ca21e05a0bd95aae71e01d0c99894976a21`; GitHub run `35738247964`. The explicit request validation job passed; the clean-checkout job was IN PROGRESS at initial verification. Do not claim a test pass until its terminal result is checked.

## First genuinely pending action

Verify the terminal outcome of run `35738247964` and this exact test's assertions. On failure, record the exact signature and attempt number; do not repeat the same run unchanged. Only after successful acceptance, record the result here and continue the W04 owner-bound, vendor-contract-gated payment settings / credential lifecycle and remaining independent negative cases. Preserve H-02 EXTERNAL UNVERIFIED/HOLD and the existing stage count throughout.
