# MT-7.5 / W04 digital order cancel UI boundary (24 September 2026)

Authenticated order detail previously offered Cancel order for pending digital milestone orders although server cancellation requires a physical commerce outlet and rejects digital orders. UI now hides that invalid action only for digital orders, preserving pending payment continuation and physical commerce cancellation.

LOCAL evidence: Website typecheck PASS; local Microsoft Edge synthetic pending and failed digital order browser 2/2 PASS with guarded global teardown. No provider activation or hosted CI. MT-7.5/W04 In Progress 15/27 DONE / 12 OPEN; H-02 HOLD.
