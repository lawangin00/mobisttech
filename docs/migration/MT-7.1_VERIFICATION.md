# MT-7.1 Verification

MT-7.1 - Data migration and rollback rehearsal is complete.

## Boundary

- Rehearsal used only the target disposable `mobisttech_test` schema on `127.0.0.1:13306` plus synthetic fixtures.
- No protected source database, source environment, private export, source upload/media, live provider, production data or cutover path was read or written.
- Real export/cutover and destructive production restore remain separately authorized HOLD actions.

## Controlled migration rehearsal

Fresh focused importer/recovery gate: PASS 29 tests / 254 assertions.

Covered migration families:
- identity mapping and source-qualified IDs;
- products/master data and usage history;
- stock/acquisitions/movements/IMEI claims;
- sales/invoices and exact historical snapshots;
- warranty/claim history;
- guarded reset backup/object reconciliation;
- configuration recovery and encrypted backup rehearsal.

Verified behavior includes:
- unchanged replay is idempotent;
- changed replay, unknown fields, bad arithmetic and unresolved parents quarantine instead of guessing;
- outer transaction rollback leaves no partial product import;
- source-private paths are not imported as target object identities;
- historical identifiers/snapshots/timezones remain explicit;
- current IMEI claims rebuild from mapped history without erasing historical occurrences;
- sale/claim relations and immutable snapshots survive replay;
- encrypted recovery is target-only and key-dependent; rotated secrets are not restored.

## Addendum/reset preservation rehearsal

Fresh addendum/schema/reset gate passed for the visible focused suites, including:
- every reset table classified with dependency barriers;
- transactional/business/factory reset policy;
- factory full-scope enforcement;
- verified backup required before reset;
- failed backup blocks deletion;
- cleanup-pending reset resumes from verified private-object backup;
- minimum bootstrap/business profile survives factory reset;
- retained financial dependencies block unsafe deletion;
- procurement/custody/milestone references roll back atomically with their owners.

## Current MySQL reconciliation

`verify_mysql_schema.php api` PASS:
- database: `mobisttech_test`;
- MySQL: 8.4.11;
- timezone: UTC;
- strict SQL: yes;
- tables: 170;
- columns: 2,104;
- foreign keys: 364;
- indexes: 817;
- schema SHA-256: `e092af61d6a36c10df55e04782bf59c101f0beae11029d5842c531f6adee70a3`;
- unexpected business rows: 0;
- canonical seed rows: 316.

`verify_shared_schema.py` PASS:
- 79 pinned source migration hashes;
- 58 source tables / 735 source columns;
- 62 planned shared tables;
- all planned new tables;
- 116 invalid row-shape cases rejected.

Final testing-schema residue:
- migration_runs = 0;
- migration_identity_map = 0;
- migration_quarantine = 0;
- migration_reconciliation = 0;
- migration_source_history = 0;
- reset_operations = 0;
- backup_restore_rehearsals = 0.

These zero final counts are post-rehearsal cleanup evidence; migration correctness/control totals are asserted inside the focused synthetic tests before their transaction/fixture cleanup.

## Rollback and restore evidence

- Product importer outer rollback is freshly verified.
- Shared schema tests freshly verify publication/event rollback together and financial/history FK protection.
- Shared infrastructure freshly verifies after-commit queue rollback, version rollback and private-object integrity.
- Guarded reset freshly verifies backup-first execution, private-object reconciliation, bootstrap preservation and resumable cleanup.
- Operational recovery freshly verifies encrypted target-only backup rehearsal and key-dependent restore checks.

A historical MT-2.8-only DDL helper (`verify_addendum_lifecycle.php`) was not forced over the current 170-table schema after it correctly exposed its old 69/80-table checkpoint assumption. Later migrations depend on that foundation, so running the historical down migration against the current schema would be unsafe and is not valid MT-7.1 evidence. The current full-schema verifier is `verify_mysql_schema.php api`.

## Result

Record/relationship/schema reconciliation, replay/idempotency, rollback, target-only restore/recovery, reset preservation and private-object semantics pass on approved disposable fixtures. No authentic source-data import or cutover was performed.
