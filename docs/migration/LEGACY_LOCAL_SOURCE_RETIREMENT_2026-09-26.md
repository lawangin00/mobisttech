# Legacy local source retirement - 26-Sep-2026

## Decision

The owner confirmed that the former local `mobiST-POS` and `mobiST-Website` projects and their legacy daily-backup task are no longer required. Current Mobisttech is the canonical project at `C:\mobisttech`.

## Dependency result

Before deletion, the current repository was audited for runtime, build, test, service, startup, scheduled-task, configuration, symlink/junction and direct code dependencies on the two legacy working trees. No current Mobisttech runtime/build/test dependency was found. The only live Windows dependency was the legacy `mobiST Daily Backup` scheduled task. The remaining direct source-path references were historical provenance or retired migration/source-characterization utilities.

The owner-approved fresh-business scope already makes real legacy business-row migration and D04 source-copy/cutover RETIRED / NOT APPLICABLE. Required historical evidence is committed in this repository, including `docs/SOURCE_SNAPSHOT.json`, `docs/migration/SOURCE_FILE_INVENTORY.json`, `docs/migration/SOURCE_SYMBOL_INVENTORY.json`, `docs/migration/CHARACTERIZATION.md`, `docs/migration/FEATURE_PARITY_REGISTER.md`, and the audit records.

## Retirement actions

- Removed the Windows scheduled task `mobiST Daily Backup`.
- Deleted local legacy checkout `C:\mobiST\mobiST-POS`.
- Deleted local legacy checkout `C:\mobiST\mobiST-Website`.
- Pre-delete POS checkout: HEAD `6dc645f60dd9dfd7296a2a77640a6078f3274706`, origin `lawangin00/mobiST-POS`, approximately 0.23 GB / 11,632 files, with 8 pre-existing dirty/untracked status entries.
- Pre-delete Website checkout: HEAD `5d7177d457a448c392b0cf3d8c244ee33a715220`, origin `lawangin00/mobiST-Website`, approximately 0.15 GB / 13,491 files, with 8 pre-existing dirty/untracked status entries.
- Historical initialization snapshot commits remain unchanged in committed evidence; they are provenance records, not claims about the final pre-delete working-tree heads.

## Self-contained control changes

- Project control now states that retired local source paths may be absent and cannot be required by commands.
- `tools/migration/source_inventory.py` validates committed source evidence only.
- `tools/docs/snapshot_sources.py` validates the committed historical snapshot only.
- `tools/migration/stage_d04_source_copies.py` is an explicit D04 RETIRED / NOT APPLICABLE no-op guard.

## Post-delete verification

With both legacy folders absent and the legacy backup task removed:

- committed source-evidence validator: PASS;
- committed source-snapshot validator: PASS;
- D04 retirement guard: PASS;
- current Laravel bootstrap under `C:\mobisttech`: PASS on PHP 8.5.9;
- `LocalEnvironmentGuardTest`: 9/9 PASS;
- legacy-path services: 0;
- legacy-path scheduled tasks: 0;
- legacy-path processes: 0 after the inspection process exited.

This maintenance does not advance MT-7.6 and does not alter provider, production or owner/legal PRE-LAUNCH holds.
