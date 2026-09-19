# MT-7.5 - Full functional parity and acceptance

Status: In Progress. No final parity, policy approval, production provider or product release claim.

## Scope and evidence boundary

- Canonical project: mobiST Tech (`282dba2f-a2d9-47e8-aa8d-e499fbe1706c`), private `lawangin00/mobisttech/main` only. Protected POS and Website sources remain read-only.
- This point must reconcile `docs/migration/FEATURE_PARITY_REGISTER.md`, addendum v1.1, consolidated requirements and software publishing requirements against actual target backend/UI/public/API tests, security, recovery, CI and operational journeys. Existing individually complete roadmap points are not reopened solely to restate their status.
- Platform foundation/CI and MT-7.4 Linux rehearsal are prerequisite evidence, not proof that every source-to-target feature has full parity. Current register has many still-Pending feature families; they must be accepted individually on fresh evidence, never mass-marked complete based on roadmap point status.

## Initial authenticated Software Product vertical acceptance

- Added `PlatformAdministrationInterfaceTest::test_software_admin_publication_and_rollback_preserve_public_draft_isolation` to join real session+CSRF Admin API and public versioned Software API with one synthetic test-only product. It checks unpublished 404, editor/publisher permission separation, public published revision, private draft isolation, reviewed update, rollback and revision retention.
- This is synthetic test-data acceptance only. It does not publish real mobiST POS, approve reference Privacy/Terms, activate live providers or verify the Next.js public renderer/browser; existing CMS service tests, API contract tests and browser tests are supporting but separate evidence.
- Initial Windows host has no disposable MySQL listener on 127.0.0.1:13306. PHP syntax and diff checks may be run locally; a new clean-checkout isolated MySQL CI run must prove the integrated test before it counts as PASS.

## Release decisions and unclosed acceptance

- `docs/reference/mobiST POS-IMS/` is factual reference material, not a production-approved or automatically published Software Product. Its offline local-Windows/database/installer/license descriptions are product-specific; they must be checked against the actual separately delivered mobiST POS product, not substituted with the monorepo's web POS capabilities. Reconcile each Overview, Privacy, Terms and FAQ statement through the reusable Software Product review workflow before public publication.
- Root application license/ownership terms and final legal-policy approvals/effective dates remain explicit owner/legal decisions per `docs/audit/MT-7.2_LEGAL_PRIVACY_AUDIT.md`. Do not fabricate approval or publish placeholder reference text. Provider sandbox/live Gmail or payments and real-data migration retain their independently authorized HOLD boundaries.
- Remaining work: independently complete all parity-register families and cross-requirement traceability, actual end-to-end POS/Website/Control journeys, public Software Product route family and reference reconciliation, fixed Website four-channel/independent POS split/closing/reporting matrix, invoice/warranty document actions, approved legal/footer links and negative security/restore gates. Keep MT-7.5 In Progress until those are evidenced or an explicit unresolved gap is recorded; MT-7.6 is not started.
