# MT-7.5 / W04 missing hosted continuation guard (23-Sep-2026)

- Test-first synthetic provider returned a valid unique reference but no hosted URL; initiation previously returned a reference-only response, which cannot open the intended hosted checkout. The original provider reference is durably retained for later verified reconciliation.
- `OrderTransactions::safeContinuation()` now rejects absent hosted URLs on both fresh and cached initiation, without changing the existing HTTPS, host and credential URL checks. Synthetic focused 3/3 PASS (14 assertions); joined W04 / OrderPayment / ApiContract 83/83 PASS (1588 assertions); scoped Pint PASS. No genuine payment provider enabled or authenticated.
- W04 remains IN PROGRESS; MT-7.5 15/27 DONE, 12 OPEN. Authentic provider callback, configuration, refunds and settlement remain H-02 HOLD.
