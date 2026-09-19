# mobiST Control

Canonical Windows local-development Control application for the mobiST Tech monorepo.

## Managed targets

- Backend / POS: `C:\mobisttech\backend`
  - Laravel HTTP: `127.0.0.1:18080`
  - Vite assets: `127.0.0.1:15173`
- Website: `C:\mobisttech\website`
  - Next.js development server: `127.0.0.1:13000`

The isolated MySQL process is intentionally not owned by Control. Database lifecycle remains delegated to the already ownership-safe `tools/dev/Database.ps1` helper. Queue/cache/storage integration belongs to MT-6.3. Legacy LAN/QR controls are also intentionally excluded here because the approved target app servers bind to loopback only; advertising a LAN URL would be false/unsafe.

## Safety model

Control never treats a listening port as permission to terminate a process. Each launched service writes an ignored per-user ownership record under `%LOCALAPPDATA%\mobiST Control\owned` containing the launcher PID, process creation time and exact runner path. Before stop/restart, Control revalidates all three and verifies that the listening PID is a descendant of the tracked launcher. A stale/reused PID is discarded. An occupied port owned by another process is reported as blocked and is never killed.

The three tracked runner scripts are target-only and contain explicit `C:\mobisttech` paths. This prevents source-project or unrelated process control. Backend Vite lifecycle also removes `backend/public/hot` whenever the canonical Vite listener is confirmed offline, preventing Laravel from pointing built pages at a dead HMR port after a force-stopped development process.

## Commands

The GUI provides Start, Stop, Restart, Open and Status for Backend/POS and Website, plus Start All and Stop All.

The same source exposes CLI switches for verification/automation:

- `--start-backend`, `--stop-backend`, `--restart-backend`, `--status-backend`, `--open-backend`
- `--start-website`, `--stop-website`, `--restart-website`, `--status-website`, `--open-website`
- `--start-all`, `--stop-all`, `--status-all`
- legacy-compatible aliases `--start-pos`, `--stop-pos`, `--restart-pos`, `--status-pos`, `--open-pos`

Open behavior adapts the legacy Edge title-scanning/focus logic. When Edge is installed, new target pages are opened explicitly in Edge. Control first tries to focus a matching target tab; it also keeps a short per-target recent-open marker and focuses the existing Edge window instead of opening again during repeated/double-click Open actions. Only when Edge is unavailable does Control fall back to the default browser. The recent-open guard is deliberately short so a genuinely closed target can be reopened normally.

## Build

Run `Build.bat`. The generated GUI executable is written to ignored `bin\mobiST Control.exe`. The executable icon and visible header logo use MT-6.1 canonical runtime assets; no legacy Control logo is copied.

`Build-Test.bat` builds an ignored console-mode acceptance binary under `.local\mt62` from the same source.
