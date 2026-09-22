# MT-7.5 W04 — COD revision lock and independent-session contention (23-Sep-2026)

Status: BOUNDED LOCAL ACCEPTANCE; W04 remains In Progress (15/27 DONE, 12 OPEN). This checkpoint does not establish a simultaneous two-application-worker race test or external-provider approval.

## Change

- `WebsitePaymentAdministration` now takes one COD-domain-scoped MySQL advisory lock before entering either draft-allocation or draft-publication transaction and releases it in `finally` after commit/rollback.
- Both operations share the exact lock name; the 5-second bounded acquisition returns HTTP 409 if another database connection holds it. This protects the initially empty revision domain and serializes draft/publish without relying on an aggregate `MAX(version)` row lock.
- The existing per-domain/version unique constraint, latest-draft check, state checks, authorization, revision audit and immutable external-provider boundary remain unchanged.

## Evidence and limits

- A separate MySQL connection acquires the named lock while the authenticated application connection attempts a COD draft; that request returns 409 without inserting a revision. On release, draft creation succeeds.
- A distinct-connection lock acquired after draft creation similarly causes publish to return 409 without changing draft state; release permits publication.
- Focused isolated MySQL contention method: 1/1 PASS (14 assertions). Complete COD HTTP class: 3/3 PASS (62 assertions); two modified PHP files syntax PASS and scoped Pint `--test` PASS.
- This is real cross-connection lock contention, but the holding connection does not itself call the application service concurrently; an independently synchronized two-worker draft/publish race remains pending before claiming full concurrent application acceptance.
- Only synthetic transactional test records in `mobisttech_test` were used. No production/customer data, authentic provider, protected source repository or real payment was touched.

## First pending continuation

Run an isolated two-application-worker race on the same disposable MySQL database, verify one serialized revision order with no duplicate versions or partially published snapshots, then advance remaining W04 payment parity negatives. Keep H-02 external merchant/credential/callback/refund/settlement HOLD until separately authorized.
