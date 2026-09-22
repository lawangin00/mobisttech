# MT-7.5 W04 — foreign-customer COD cancellation HTTP boundary (23-Sep-2026)

Status: bounded synthetic authenticated HTTP negative acceptance PASS. W04 remains IN PROGRESS, 15/27 DONE and 12 OPEN. H-02 provider/merchant/refund/settlement HOLD unchanged.

- Existing `ApiContractTest::test_mt_5_3_checkout_http_contract_is_fixed_owned_idempotent_and_provider_verified` now authenticates an independent second Customer with a separate CSRF-protected session. Their POST to cancel the first Customer's live COD order returns HTTP 404.
- After denied cancellation, the original order remains pending and its original reservation remains `held_cod`; the authorized original Customer can still cancel successfully. Existing initiation/retry ownership and CSRF checks remain in the same HTTP journey.
- Focused checkout HTTP test 1/1 PASS (52 assertions); complete ApiContractTest 19/19 PASS (766 assertions); W04-focused regressions 24/24 PASS (407 assertions); scoped Pint PASS.
- No live payment or external merchant operations. This test does not close W04 or replace authentic provider/refund acceptance.
