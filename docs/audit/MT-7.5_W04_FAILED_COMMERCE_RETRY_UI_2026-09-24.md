# MT-7.5 / W04 failed-commerce retry UI reconciliation

Date: 24 September 2026 (PKT). Scope: owned Website order detail, synthetic local verification only.

The server-side `OrderTransactions::applyReceipt()` records a definitively failed hosted commerce payment as order `status=cancelled`, `payment_status=failed` and payment `status=failed`. `OrderTransactions::retry()` expressly permits that failed commerce order to create a new attempt, subject to current provider, stock, outlet, ownership and reservation controls. The customer UI previously suppressed all retries whenever order `status=cancelled`, hiding this approved server capability.

The UI now offers available non-COD retry channels only when `order.type=commerce`, order `payment_status=failed`, and the latest payment `status=failed`, regardless of its cancelled fulfillment marker. Pending, unknown, customer-cancelled-unpaid and digital milestone orders do not gain a retry action. The server remains the authoritative gate for outlet, mode and provider availability.

Verification: Website typecheck PASS. Isolated local Microsoft Edge focused test 1/1 PASS. Joined checkout browser 7/7 PASS with guarded global synthetic fixture teardown; an initial joined run failed the sixth synthetic login HTTP 429, corrected by clearing *testing-only* disposable cache immediately before that independent fixture (production throttles remain enabled); corrected 7/7 exit zero. No genuine external provider transaction, hosted CI request, workflow dispatch or source data modification.

MT-7.5/W04 remains In Progress (15/27 DONE, 12 OPEN); H-02 authentic provider integration remains HOLD.
