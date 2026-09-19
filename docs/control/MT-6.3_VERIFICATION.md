# MT-6.3 Verification

MT-6.3 - Windows operator and local integration acceptance is complete.

## Windows host and clean local baseline

- Repository checkpoint was clean/synced before execution.
- Current host shell is Windows PowerShell 5.1.26100.9472; `pwsh.exe` is not present on PATH.
- The existing target-only MySQL instance was already running under `tools/dev/Database.ps1`; duplicate Start correctly returned `Already running` and ownership remained unchanged.
- `php artisan foundation:check` passed before and after the lifecycle rehearsal, proving the isolated target MySQL, file cache and private local storage path with synthetic self-cleaning markers.
- Redis remains reserved/not running and S3 remains disabled/private by configuration, matching the approved local-development boundary. No external Redis/S3 service was activated.

## Queue/cache/storage integration

- A synthetic local database-queue job using the existing `Tests\Fixtures\InfrastructureJob` was pushed to a unique `mt63-*` queue.
- `php artisan queue:work database --once --sleep=0 --tries=1` processed the real database job.
- The expected publication-version marker was written with version 1, then removed by the harness.
- Final residue: `MT63_JOBS=0`, `MT63_FAILED=0`, `MT63_VERSIONS=0`.
- Cache/private local storage were rechecked through `foundation:check` and left no synthetic acceptance residue.

## Control lifecycle and status accuracy

- Start All from Offline started Backend HTTP, Backend Vite and Website Next.js as owned target trees.
- A second Start All reported all three already Online and skipped duplicate launches.
- Live HTTP readiness was verified for Vite and Website, and Control reported Backend/POS Online plus Website Online.
- Stop All stopped Website, Vite and Laravel in owned order; a second Stop All was idempotent and reported all already Offline.
- Final Control state is Backend/POS Offline and Website Offline, with no target listener on 18080, 15173 or 13000.

## Reboot-equivalent recovery

A physical Windows reboot was intentionally not performed because it would disrupt the user's workstation. Instead, the exact Control-owned application trees were terminated externally with `taskkill /T /F` while their ownership records were deliberately left stale, reproducing the process/state condition Control must handle after an abrupt host stop.

- Fresh Control status removed all three stale ownership records and reported both operator targets Offline.
- A fresh Start All then returned Backend HTTP, Backend Vite and Website Next.js to Online.
- No stale ownership file survived the recovery.

## Runtime failure, partial-start and unrelated-process safety

- An unrelated synthetic listener was placed on Website port 13000.
- Start All started the owned backend pair but reported Website Blocked, producing a truthful partial-start state.
- Stop All stopped only the owned backend pair and refused to terminate the unrelated listener.
- The unrelated listener remained alive until the acceptance harness explicitly removed it.
- After removal, Control returned to both targets Offline with no target listeners.

## Browser deduplication

- Backend first Open launched Edge; immediate repeated Open returned `recent Edge target focused; duplicate open skipped`.
- Website repeated Open focused the existing Edge target.
- No browser process was force-terminated by Control.

## Protected source isolation

Pre-existing source development servers remained alive throughout acceptance:
- POS source remained on port 8000 with its existing source process tree.
- Website source remained on port 8001 with its existing source process tree.
- Protected POS and Website Git working trees remained clean at their recorded heads.
- No source process, source file, source database or source repository was mutated by target Control acceptance.

## Final state and boundaries

- Target MySQL is still Running, matching its initial state before MT-6.3.
- Target applications are Offline; target listeners 18080/15173/13000 are absent.
- Control-owned state directory contains no remaining acceptance state file.
- Queue/cache/storage synthetic residue is zero.
- Redis/S3 live activation was not required and was not attempted.
- No MT-7.1 migration/cutover work was started.
