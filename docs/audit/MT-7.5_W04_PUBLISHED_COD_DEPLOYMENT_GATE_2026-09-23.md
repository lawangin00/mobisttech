# MT-7.5 W04 — published COD policy cannot override invalid deployment flag (23-Sep-2026)

Status: bounded synthetic negative acceptance, W04 IN PROGRESS (15/27 DONE, 12 OPEN), genuine payment H-02 HOLD.

Expanded `W04AdminPaymentOverviewTest`: with a malformed truthy string `"false"` in the COD deployment flag, COD remains unavailable when no policy exists and **also** when a synthetically published COD policy requests `cod_enabled: true`. The nonsecret Admin overview agrees with the effective provider gate. Only replacing the malformed flag with actual boolean `true` restores COD availability. All synthetic revisions are inside `DatabaseTransactions`; no real Admin or shop setting is changed.

Focused Admin overview 3/3 PASS (20 assertions), full focused W04 24/24 PASS (407 assertions), neighboring OrderPaymentTransactions 17/17 PASS (130 assertions); scoped Pint and diff whitespace PASS. External provider adapters remain default OFF and no owner/merchant authorization, actual provider callback or refund/settlement acceptance is inferred.