# MT-1.1 characterization procedure and limitations

Date: 2026-08-31. Pinned commits and immutable original working-tree fingerprints are in `../SOURCE_SNAPSHOT.json`. Full path/hash inventory is in `SOURCE_FILE_INVENTORY.json`; capability decisions and retirement conditions are in `FEATURE_PARITY_REGISTER.md`.

## What actually ran

Only `C:\mobisttech\.local\mt11\sources\pos` and `website` copies were executed. Source Git was read with `git --no-optional-locks -c safe.directory=<exact protected source>`; no global Git configuration, source index, source working file, remote, service or database was changed. `source_inventory.py` read pinned blobs and exported the approved selection. No source `.git`, dependency installation, secrets, live database or generated executable was copied.

Fresh dependency installs used PHP 8.5.9 and Composer 2.10.2 with `install --no-scripts --no-plugins --prefer-dist --no-interaction --no-progress`, each isolated copy's original lockfile, and an ignored project-local Composer home/cache. Both installed 110 locked packages, including Laravel 13.26.1 and PHPUnit 12.5.33. Nothing was installed in target `backend/` or `website/`. No source or target npm install/build occurred. Source package manifests require Node >=24.19 <25 and npm >=11.17 <12; exact target toolchain selection remains MT-1.3.

`prepare_characterization.py` creates only synthetic local environment/storage and modifies the isolated `tests/TestCase.php`: it verifies testing environment, SQLite in-memory DB and copy-local base path, prevents stray Laravel HTTP requests, and bypasses Vite tags. Source application and test-case assertions otherwise remain unchanged. The initial synthetic key was one byte too long and caused harness errors; it was corrected to 32 bytes and both complete suites rerun successfully. No source fix was needed or made.

Final execution was `php vendor/bin/phpunit --log-junit <ignored output>` in each isolated copy with ordinary restricted tool permissions. The suites exercise in-memory migrations/rollback, fake storage/backup verification, array mail, fake provider HTTP and fake hosted-card contracts. Existing source assertions and negative cases ran without skips. Unit smoke tests that do not boot Laravel remain as supplied. Commands which configure mail/accounts, mark orders paid, create live quotes, reset data, run schedulers, restore business data or launch/stop servers were not invoked.

The separate `php artisan route:list --json --except-vendor` runs booted only the same isolated testing configurations and listed 167 POS plus 149 Website application routes. Middleware, methods, URIs, names and actions are persisted in `SOURCE_SYMBOL_INVENTORY.json`. No request was dispatched to an original service. Laravel/vendor convenience routes are outside this application route total.

## Fresh results

| Source copy | Tests | Assertions | Errors | Failures | Skips |
|---|---:|---:|---:|---:|---:|
| POS | 202 | 2695 | 0 | 0 | 0 |
| Website | 236 | 2806 | 0 | 0 | 0 |

`MT_1_1_VERIFICATION.json` records individual test names/assertion counts, raw ignored JUnit/log hashes, harness hashes and inventory hashes. These source results are fresh and happen to match historical reported totals; they are not claims that target code or application migration is complete.

## Reproduction and change control

From the new repository, use `tools/migration/source_inventory.py --export` into a fresh ignored destination. It refuses to overwrite an existing altered source export. After dependencies are installed as above, use `prepare_characterization.py`, run the two isolated test suites and route listings, then `build_parity_inventory.py` and `write_parity_register.py`. Review family assignments, generated differences and findings rather than assuming filename classification alone proves behavior. The generator reads pinned source blobs, so isolated harness modifications cannot contaminate the static source index.

`verify_inventory.py` checks zero omitted tracked paths, blob-derived hash consistency, valid roadmap destinations, route/source linkage, complete JUnit cases, unchanged selected source application bytes, only the declared harness modification, absence of nested Git and unchanged target placeholders. `tools/docs/snapshot_sources.py --check` independently rechecks original HEADs/remotes/branches/status and all tracked-file bytes against the initialization snapshot.

The approved Goal and Preferences remain byte-identical. Only inventory/control documents and their helpers are introduced in this point. No schema/API design decision in MT-1.2, application migration, real provider request, external message, source-data export, live cutover or production operation was performed.

## Limits that later gates must cover

- SQLite source tests do not establish MySQL locking/concurrency, schema/data import or production recovery acceptance.
- Vite bypass validates server-side behavior/views and source assertions, not compiled frontend bundles, React/Next rendering or browser interaction. Fresh builds and Playwright remain required.
- Fake hosted-card/wallet calls do not establish authentic sandbox acceptance; H-02 remains active.
- Static route/method/schema/file coverage prevents omission but is not branch coverage or proof of every business behavior. Per-family source assertions and explicit risks remain part of later acceptance.
- Source Control was inspected, never compiled/launched; unsafe PID ownership and stale path/logo handling are recorded for MT-6.2. Brand masters are referenced by hashes, not migrated here.
- Full sale-return/refund behavior was not found as a routed source workflow; the Goal requirement remains open under MT-2.5.
