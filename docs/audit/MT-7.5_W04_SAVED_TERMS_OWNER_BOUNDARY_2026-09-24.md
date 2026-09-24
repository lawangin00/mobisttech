# MT-7.5 W04 saved payment terms: owner-vs-signed status boundary (24-Sep-2026 PKT)

- Existing authenticated owned-order GET provides the order-bound, nonsecret saved payment label/instructions/COD limits. The distinct, short-lived signed status GET intentionally supplies only the read-only order summary and does not include `payment_terms` or payment records.
- Extended the existing historical signed-order HTTP test with an isolated synthetic saved-terms child. Owner-only GET exposes its saved label; signed status response does not expose `payment_terms` or `payments`; existing invalid/expired signature and cross-owner checks remain intact.
- Focused test 1/1 PASS (17 assertions); joined IdentitySecurityTest and OrderPaymentTransactionsTest 45/45 PASS (390 assertions); scoped Pint PASS. Synthetic only, no production authorization or payment-provider change, no new hosted CI on this commit.
- W04 / MT-7.5 IN PROGRESS, 15/27 DONE, 12 OPEN; genuine provider H-02 HOLD.
