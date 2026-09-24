# <Stage/point ID> — finite acceptance checklist

Authority: approved roadmap clause(s) <exact file/heading>; ledger link <current entry>. Stage/family: <ID>. Established before implementing new scope on <date>. This is a TEMPLATE, not a PASS or permission to add requirements.

| Stable gate ID | Approved acceptance contract / minimum observable proof | Status (OPEN / DONE / HOLD) | Gap (I / E / H) | Evidence ref and relevant source/config/environment fingerprint | First genuinely pending action / owner for HOLD |
|---|---|---|---|---|---|
| <existing approved ID> | <exact existing requirement; no speculative subgate> | OPEN | E | <current valid proof or missing proof> | <one independently executable next step> |

- Enumerate the finite approved gates once, reconcile earlier accepted family work, and link this checklist from `docs/PROJECT_IMPLEMENTATION_STATUS.md`. Do not count overlapping feature and cross-group implementation twice; preserve immutable DONE records. Reopen only with demonstrated changed contract/regression and linked evidence.
- Per change: <impacted code/config/contract>, focused tests <...>, affected neighbor tests <...>, reused PASS <source SHA/date plus unchanged-code/config/fixtures/environment rationale>, fixture ownership/isolation <...>; record terminal status and failure classification/signature before any material retry.
- Separate authentic external/provider, production/destructive and owner/legal HOLDs, with explicit missing authorization; they are never PASS. At closure check *all* applicable mandatory product, security, money, privacy, data-integrity, end-to-end, cleanup and release criteria; link proof, mark DONE only after it passes and update the implementation ledger. Full joined regression only at the applicable closure/release/material cross-system gate.