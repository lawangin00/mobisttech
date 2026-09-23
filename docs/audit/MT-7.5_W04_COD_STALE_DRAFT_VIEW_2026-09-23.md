# W04 COD stale draft view (23-Sep-2026)

- Root cause: Admin settings exposed the latest row in draft state even if a newer COD policy had already been published. This displayed a draft that the publish endpoint rejects as stale.
- Admin settings now show a COD draft only when its version is newer than the currently published policy. A newer genuine draft remains visible and does not affect the live COD setting until authorized publication.
- Test-first: 1/1 FAIL at old code; corrected focused 1/1 PASS (6 assertions). Joined W04/API/OrderPayment suite 77/77 PASS (1546 assertions) before the final added positive draft assertions; scoped Pint PASS. External gateways remain OFF and H-02 HOLD.
