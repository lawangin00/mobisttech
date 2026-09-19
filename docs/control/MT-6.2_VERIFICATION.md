# MT-6.2 Verification

MT-6.2 - Canonical mobiST Control migration is complete.

## Canonical target

- `C:\mobisttech\tools\mobist-control` is the single target Control application.
- The protected POS and Website legacy Control sources were verified byte-identical and used read-only as one migration/reference source.
- No legacy Control executable, logo binary, launcher backup or protected-source path was copied into the target.
- The GUI executable uses the MT-6.1 canonical favicon as its Windows icon and the canonical generated wordmark PNG as its visible header branding.
- Managed target paths are exactly `C:\mobisttech\backend` and `C:\mobisttech\website`.

## Managed lifecycle

Backend / POS is one operator target composed of Laravel HTTP on `127.0.0.1:18080` and Vite development assets on `127.0.0.1:15173`. Website is the Next.js development server on `127.0.0.1:13000`.

The GUI and shared CLI acceptance surface provide Start, Stop, Restart, Open, Status, Start All and Stop All.

## Process-ownership safety

The legacy port-authorized `taskkill` model was not carried forward. Every Control-started service records, under ignored per-user state, the launcher PID, process creation time and exact target runner path. Before stop/restart, Control revalidates PID existence, creation time, exact runner command line and listener ancestry. A port number by itself never authorizes termination.

Verified safety cases:
- an unowned synthetic listener on Website port 13000 caused Website Start to report Blocked;
- Start All under that condition started only the owned backend services and reported the Website blockage truthfully;
- Stop All stopped the owned backend services but refused to terminate the unrelated 13000 listener;
- the unrelated listener remained alive until explicitly removed by the acceptance harness;
- a state record pointing at a real unrelated PID with a mismatched creation time was discarded as stale/reused; the unrelated PID remained alive;
- after deliberately terminating an owned Website listener child, the launcher tree exited, stale ownership cleared, Website reported Offline, and no orphan listener/root remained;
- repeated Start All skipped all already-online services instead of launching duplicates.

## Open/browser behavior

Open Backend and Open Website require the corresponding target to be Online. The legacy Edge title-scan/focus behavior is retained. New target pages are opened explicitly in Edge when installed; a short per-target recent-open guard prevents repeated/double-click Open actions from creating an unnecessary second tab when title enumeration is not reliable on the current desktop.

Acceptance recorded:
- Backend first open launched Edge; immediate second open returned `recent Edge target focused; duplicate open skipped`.
- Website repeated opens focused the existing Edge target.
- If Edge is unavailable, Control falls back to the default browser.

## Source/unrelated-process isolation

During final target Start/Open/Stop verification, pre-existing protected-source servers remained alive:
- source Website command tree PIDs 27028 / 9376 on port 8001;
- source POS command tree PIDs 15880 / 12412 on port 8000.

All four were alive before and after the target lifecycle. Protected source Git trees remained clean. Target Control has no reference to the old source paths, old Control logo filenames, legacy PHP runtime or ports 8000/8001.

## Exclusions and ownership boundaries

The following legacy features were not carried forward into the canonical target and are intentionally inapplicable to MT-6.2:
- LAN/QR: approved target development servers bind to loopback only; publishing a LAN URL would be false/unsafe.
- Desktop Commander controls: unrelated external tooling, not a mobiST application lifecycle dependency.
- Windows auto-start links: not part of the approved Control acceptance and would create persistent host mutation.
- Website build button: build/verification remains the normal repository toolchain, not service lifecycle ownership.
- MySQL lifecycle: the existing `tools/dev/Database.ps1` already owns target MySQL safely; local integration/rehearsal remains MT-6.3.

## Final gates

- C# console acceptance build: PASS.
- WinForms `bin/mobiST Control.exe` build: PASS.
- GUI startup smoke: process remained alive after 2 seconds, then the exact test-owned GUI PID was stopped.
- Final Control status: Backend / POS Offline; Website Offline.
- Target ports 18080/15173/13000: no LISTENING process.
- Legacy path/logo/runtime reference scan: NONE.
- Git diff check: PASS.
- Protected source repositories: clean.
- No MT-6.3 work was started.

## MT-7.2 resilience follow-up

MT-7.2 later exposed one lifecycle residue not covered by the original MT-6.2 process-tree assertions: a force-stopped Vite process can leave Laravel's `public/hot` HMR marker behind even though port 15173 is offline. That stale marker makes subsequent built-asset Laravel runs reference a dead Vite server.

Control now removes `backend/public/hot` only when the canonical Backend Vite listener is confirmed offline, both before a fresh Vite start and after/offline Stop. Follow-up acceptance proved: a stale offline marker is removed; a normal Start Backend recreates the marker while Vite is Online; Stop Backend removes it and leaves 18080/15173 free. Default Playwright also clears the marker because that suite intentionally runs against built assets, never HMR.
