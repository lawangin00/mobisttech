# Windows application foundations

This runbook covers the isolated target foundation, not source-data or business-feature migration. The [implementation ledger](../PROJECT_IMPLEMENTATION_STATUS.md) owns live point/stage status. The structural roadmap and its Word mirror are unchanged by routine progress.

## Pinned toolchain

| Tool | Verified version |
|---|---|
| PHP | 8.3.33, with PDO MySQL, BCMath, curl, mbstring, OpenSSL, XML and ZIP |
| Composer | 2.10.2 |
| Node.js / npm | 24.19.0 / 11.17.0 |
| MySQL Community Server | 8.4.11 LTS, official portable Windows ZIP |
| Laravel framework / Inertia PHP adapter | 13.29.0 / 3.3.1 |
| React / Inertia React adapter | 19.2.8 / 3.7.0 |
| Next.js | 16.3.3 |
| TypeScript / Tailwind CSS | 5.9.3 / 4.3.3 |
| Vite / Laravel Vite plugin / React plugin | 8.2.2 / 3.2.0 / 6.1.1 |

Use PowerShell 7 when it is available. The current Windows host does not expose `pwsh.exe` on PATH; the target lifecycle helpers used by this runbook have also been verified under Windows PowerShell 5.1, so `powershell -NoProfile -ExecutionPolicy Bypass -File <script>` is the supported local fallback. The existing verified PHP executable is `C:\php\php.exe`; do not edit its configuration or the source project's separate PHP runtime. Composer's platform lock uses PHP 8.3.33. Both npm lockfiles record exact transitive versions, and the package manifests constrain Node/npm to the verified versions. Run `npm.cmd` on Windows to avoid execution-policy interception of `npm.ps1`.

Official references: [Laravel installation](https://laravel.com/framework/docs/13.x/installation), [Laravel starter stack](https://laravel.com/starter-kits), [Next.js installation](https://nextjs.org/docs/app/getting-started/installation), [MySQL Windows download](https://dev.mysql.com/downloads/mysql/8.4.html). This foundation uses the official Laravel 13.10.1 skeleton selectively, rather than importing an unrelated authentication starter kit over the approved source identity design.

## Target-only ports and resources

| Component | Binding / resource | Authority |
|---|---|---|
| Laravel/POS | `127.0.0.1:18080` | Shared business backend; only foundation routes exposed |
| Next.js | `127.0.0.1:13000` | Website rendering and fixed Laravel API proxy only |
| Vite development assets | `127.0.0.1:15173`, strict port | POS assets only |
| MySQL | `127.0.0.1:13306`, X protocol disabled | Separate `.local/mysql/data`; `mobisttech_local` and `mobisttech_test` only |
| Redis | Reserved `127.0.0.1:16379`, not running | Future derived public cache/throttling; never stock/payment authority |
| Private local storage | `backend/storage/app/private` | No public serving route; synthetic probes only |

`LocalEnvironmentGuard` rejects local/test databases outside the fixed host/port/schema/user allowlist, SQLite, URL/socket overrides and enabled external integrations before connecting. Laravel HTTP client stray requests are disabled in local/testing. The Website has no database or provider credentials and only forwards `/api/v1/*` to the fixed target backend. Its server-side health read rejects another origin and redirects. This is local configuration, not production provisioning or the final browser authentication proxy.

MySQL is a separate process, not an installed Windows service. New random target credentials are created locally; no source key, environment, database, uploads, dependencies or Git metadata are copied. The app account has privileges only on the two target schemas, not global MySQL administration. The startup helper checks its owned PID, executable and creation time; it refuses occupied ports and does not kill processes by port.

## First setup from a clean checkout

Run from `C:\mobisttech` after selecting the pinned toolchain:

```powershell
pwsh -NoProfile -File tools/dev/Initialize-Local.ps1 -DownloadMySql
pwsh -NoProfile -File tools/dev/Database.ps1 Start
Set-Location backend
composer install --no-interaction --prefer-dist
php artisan migrate --no-interaction
php artisan migrate --env=testing --no-interaction
npm.cmd ci --ignore-scripts
npm.cmd run build
Set-Location ../website
npm.cmd ci --ignore-scripts
npm.cmd run lint
npm.cmd run typecheck
npm.cmd run build
```

Setup downloads only when the pinned MySQL executable is absent. Archive SHA-256 is `a492371d687d2bab088b0062581144a0044b8964baefdf4faa579292b423d25c`; the official published MD5 was also verified at this checkpoint. Downloaded binaries and database files stay ignored under `.local`. Setup never overwrites existing target data or `.env`; it creates ignored runtime directories needed after a clean checkout, new app/test keys and credentials, and a first-start SQL file that is removed after successful initialization. Preserve the ignored credentials for subsequent starts. Do not delete or reinitialize an existing database to resolve a startup error.

Only framework cache, queue and session migrations exist. There are no customer/product/order/payment business tables, migrated user records or registered authentication routes. The skeleton User class is an unused framework placeholder, not migrated identity acceptance. No source seeder or user/data migration runs. The business schema and column-level migration manifest belong to their later structural roadmap points.

## Run and verify

Start the database through the helper. In separate target application terminals:

```powershell
# In C:\mobisttech\backend
php artisan serve --host=127.0.0.1 --port=18080 --tries=1
# Optional separate terminal for live POS asset development:
npm.cmd run dev

# In C:\mobisttech\website (built preview)
npm.cmd run start
# Or development mode instead of the built preview:
npm.cmd run dev
```

Do not run development and built-preview servers on the same port. A port conflict is an error to inspect, not permission to stop an unrelated/source process. The framework's development server and the Next.js preview are not production deployment servers. No browser is opened automatically.

With the isolated database running, from `backend`:

```powershell
php artisan foundation:check
php artisan test
php vendor/bin/pint --test
composer validate --strict
composer check-platform-reqs
```

The tests use `mobisttech_test` and its runtime migrations. They check isolation rejection, MySQL identity/schema, database-backed sessions, the Inertia shell, the minimal public health contract, absent business/legacy routes and disabled external HTTP. `foundation:check` uses a unique synthetic cache/storage marker and removes only its own marker. Fresh source-to-target feature, payment, data, security and browser acceptance is not implied.

Use `Database.ps1 Status`, `Start` or `Stop` from the project root for the owned target MySQL. Stop the application terminals normally before database shutdown. The foundation helper is not the canonical Control application; Control migration remains separately scoped.

## Redis, storage and maintenance boundaries

Foundation cache uses the local file store; sessions and queued jobs use MySQL. Redis has a concrete reserved role for derived public catalogue/content cache and throttling, with a Predis client, isolated endpoint and project key prefix configured. There is no current derived catalogue feature requiring a Redis process. This host has no Docker/WSL/Redis service; none was installed or impersonated. Activate a supported isolated Redis runtime and prove failure/fallback behavior when the consuming infrastructure is implemented. Do not replace MySQL correctness with Redis locks or claim Redis runtime acceptance here.

The S3-compatible Flysystem adapter is installed and configured with blank credentials; local private storage is the selected disk. No bucket, cloud credential or real object is accessed. Inertia server rendering and request-recording DevTools are disabled; no extra Node POS rendering service is needed. The UI deliberately has no migrated branding or business journeys yet.

ESLint 9.39.5 is pinned to the official Next.js 16.3.3 plugin compatibility range. It carries an upstream deprecation notice. Attempting ESLint 10.9.1 produced invalid plugin peer dependencies and a `react/display-name` rule failure (`getFilename is not a function`), so no force override was retained. Lint passes with the compatible lock. Re-evaluate the supported linter/plugin combination before release; do not suppress rules or treat a failed lint as success. Package audits at this checkpoint reported no known vulnerabilities, which does not guarantee future security.

Visual/browser smoke was attempted but blocked by Browser Use URL policy after an initial target server working-directory error. The target directory error was corrected; no alternate browser or policy bypass was used. Build, framework tests and the loopback API proxy were verified independently. No browser visual/interaction acceptance is claimed; later UI roadmap gates remain mandatory.
