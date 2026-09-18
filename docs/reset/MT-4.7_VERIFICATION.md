# MT-4.7 Verification

- Point: MT-4.7 - Data reset administration interface
- Reset Administration is a thin Admin-realm adapter over existing GuardedResetService, ResetPlanner, ResetDomains, reset backup/object backup and recovery authorities; destructive logic is not duplicated in the UI/controller.
- Exactly three reset levels are exposed: transactional, business and factory. Factory scope is locked to the complete approved factory domain set; transactional/business use only their approved domains.
- Recent re-authentication, exact level permissions, stale-preview detection, dependency barriers, exact typed confirmation, verified backup/restore-rehearsal evidence, private-object backup integrity, cleanup recovery and environment execution restrictions remain enforced by existing backend authorities.
- Production destructive reset remains on HOLD because production is not in reset.execution_environments. Destructive acceptance is verified only on disposable testing fixtures.
- UI exposes recent-auth status, dry-run domain counts, preservation matrix, barriers, typed confirmation, verified-backup evidence, operation outcomes, cleanup_pending recovery, minimum-bootstrap preservation and surviving reset audit history.
- UI cancellation discards only the pending preview locally and sends no execute/status mutation.
- Invalid domain/factory-scope input returns a 422 validation response; the reset service remains the authoritative validator/executor.
- Focused HTTP acceptance: 3 tests / 56 assertions PASS.
- Targeted Reset Administration Playwright: 1/1 PASS, including strict 390px no-horizontal-overflow containment.
- Affected reset/recovery regression: 11 tests / 123 assertions PASS.
- Full backend regression: 244 tests / 7457 assertions PASS.
- Full Playwright suite: 9/9 PASS.
- Production Vite build: PASS, 578 modules transformed with ResetAdministration in the client resolver graph.
- Final short gates: Composer validate --strict PASS; Composer platform requirements PASS; scoped Pint PASS; backend TypeScript typecheck PASS; git diff check PASS.
- Acceptance recovery details: a stale production bundle initially omitted ResetAdministration; fresh build fixed page hydration. Browser testing then found preview state and cleanup-recovery state being cleared by effect/reload coupling; both were fixed in product state handling. Final 390px overflow was traced to intrinsic min-width in Reset outcomes cards caused by long failure/checksum content; product layout now uses min-w-0/safe wrapping and the original strict containment assertion passes.
- No unresolved MT-4.7 defect remains.
