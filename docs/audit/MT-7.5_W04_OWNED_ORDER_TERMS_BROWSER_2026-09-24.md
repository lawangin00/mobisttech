# MT-7.5 W04 owned-order saved payment terms browser acceptance (24-Sep-2026 PKT)

- Extended the existing real authenticated Website COD checkout browser test to assert the newly created order-bound `payment_terms` from the owned-order HTTP API and the rendered customer order-detail UI. This is not a browser-only mocked response; preexisting other hosted-payment browser mocks remain separate.
- Disposable MySQL `mobisttech_test:13306`, real Edge: focused checkout 1/1 PASS; complete checkout suite 6/6 PASS; guarded fixture teardown `CI_DISPOSABLE_RESIDUAL_TABLES=none`; git diff --check PASS. This verifies the default-term rendering at order creation; a post-publication immutable-terms **browser** sequence has not been claimed (covered separately by backend MySQL regression).
- No provider credentials, external activation, production database, customer records or new order payment terms mutation. W04/MT-7.5 remain IN PROGRESS 15/27 DONE / 12 OPEN; genuine H-02 HOLD.
