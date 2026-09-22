# MT-7.5 W04 — Published COD-off customer checkout HTTP gate (23-Sep-2026 PKT)

Scope: one isolated synthetic feature acceptance within active W04; no external gateway activation, production customer data or merchant credential use.

`ApiContractTest::test_w04_published_cod_off_blocks_authenticated_http_checkout_without_mutation` publishes a synthetic COD-off policy in the test transaction while commerce mode remains hybrid. The authenticated customer channel API reports COD unavailable; POST checkout with COD returns HTTP 409, with no order, payment, reservation or idempotency record created. Removing only the synthetic policy restores COD availability and the same customer's ordinary COD checkout succeeds with HTTP 201.

Verification: focused 1/1 PASS (11 assertions); full `ApiContractTest.php` 21/21 PASS (809 assertions); W04 filter 26/26 PASS (456 assertions). Scoped Pint PASS, git diff --check PASS. Historical 52-file / 16-route dispositions unchanged. W04 still IN PROGRESS; H-02 genuine external providers, refunds and settlements remain HOLD.
