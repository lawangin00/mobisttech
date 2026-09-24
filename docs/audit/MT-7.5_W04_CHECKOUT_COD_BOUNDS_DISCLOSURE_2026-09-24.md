# MT-7.5 W04 customer-visible COD limits (24-Sep-2026 PKT)

- Previously the server enforced published COD minimum/maximum at final discounted checkout, but the public checkout channel and Website did not display them before placement.
- Checkout channel read model now exposes optional COD-only published min/max; customer Website renders those limits with an explicit final-server-eligibility caveat and does not calculate or override the final order price. Other payment channels do not receive COD keys.
- Published bounds (100.00–500.00) and absence from external channels tested directly; synthetic browser channel fixture verifies the visible note. Backend payment/validation 39/39 (342 assertions), API/Admin/validation 31/31 (959 assertions), Microsoft Edge checkout 6/6, Website typecheck and scoped Pint PASS. Synthetic fixture cleanup succeeded.
- Existing four channels, provider availability and merchant settings remain unchanged. Hosted acceptance on this exact change pending; H-02 real providers HOLD; W04/MT-7.5 IN PROGRESS 15/27 DONE / 12 OPEN.
