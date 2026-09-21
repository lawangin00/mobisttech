$ErrorActionPreference = 'Stop'
$inventoryPath = 'docs\migration\SOURCE_SYMBOL_INVENTORY.json'
$outputPath = 'docs\audit\MT-7.5_P07_SOURCE_CROSSWALK.md'
$inventory = Get-Content $inventoryPath -Raw | ConvertFrom-Json
$files = @($inventory.files | Where-Object { $_.families -contains 'P07' } | Sort-Object path)
$routes = @($inventory.routes | Where-Object { $_.families -contains 'P07' } | Sort-Object uri, method)

function Get-FileDisposition([string] $path) {
    if ($path -match 'app/Http/Controllers/(Admin|SuperAdmin)/ControlCenterController') { return @('ADAPT-P01-P07', 'Unified Admin platform, outlet profile and portal-preferences controllers; realm duplication retired.') }
    if ($path -match 'app/Http/Controllers/Pos(Branding|Theme)Controller') { return @('ADAPT-CONFIG', 'PlatformAdministrationController plus App\Pos\PosConfiguration revision/media authority.') }
    if ($path -match 'app/Http/Controllers/PosPortal(Navigation|TablePresentation)Controller') { return @('ADAPT-PORTAL', 'PosPortalPreferencesController plus App\Pos\PortalPreferences and protected React settings.') }
    if ($path -like 'app/Http/Middleware/*') { return @('ADAPT-AUTH', 'Unified Admin permissions and service-level authorization; legacy guard middleware retired.') }
    if ($path -like 'app/Models/Pos*') { return @('REUSE-SCHEMA', 'Shared-schema POS setting/revision/media models retained or represented by current target tables and services.') }
    if ($path -match 'app/Services/(PosBranding|PosConfigurationRevisions|PosTheme|SafePosMedia)') { return @('ADAPT-CONFIG', 'App\Pos\PosConfiguration and Platform Admin preserve revision, safe-media, publish and rollback rules.') }
    if ($path -match 'app/(Services/PosPortal|Services/PosSettings|Support/PosSettingRegistry)') { return @('ADAPT-PORTAL', 'App\Pos\PortalPreferences, PosShell and protected React workspaces own presentation defaults.') }
    if ($path -like 'database/migrations/*') { return @('MIGRATED-SCHEMA', 'Equivalent shared-schema settings, revisions and media foundations exist in target migrations.') }
    if ($path -like 'tests/Feature/*') { return @('ADAPT-TEST', 'Behavior retained in current PlatformAdministrationInterfaceTest, PosShellTest, PosTransactionInterfaceTest and PosCustomerReportingInterfaceTest gates.') }
    if ($path -like 'mobiST Control Center/*') { return @('RETIRE-CLIENT', 'Legacy desktop control-center transport is retired; protected unified Admin is authoritative.') }
    if ($path -like 'public/assets/js/*' -or $path -like 'resources/js/*' -or $path -like 'resources/css/*') { return @('REPLACE-REACT', 'Legacy global Blade/JavaScript/CSS presentation replaced by scoped React/Inertia/Tailwind surfaces.') }
    if ($path -match '(backups|pos-backup-operations|pos-integration-status|audit-log)') { return @('OWNED-P08', 'Operational/backup behavior is disposed under completed P08; shared presentation shell remains P07.') }
    if ($path -match '(login|forgot-password|reset-password|manage-account|admins|shops|business-profile|control-center-shop|portal-login|unified-login)') { return @('OWNED-P01', 'Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07.') }
    if ($path -match '(inventory|product_imeis|pos-master-data)') { return @('OWNED-P02-P03', 'Catalogue/master-data/stock behavior is disposed under P02/P03; React shell presentation remains P07.') }
    if ($path -match '(invoices|pos\.blade)') { return @('OWNED-P04', 'Sales/invoice behavior is disposed under P04; React shell presentation remains P07.') }
    if ($path -match '(claims|warranty)') { return @('OWNED-P05', 'Warranty/claim behavior is disposed under P05; React shell presentation remains P07.') }
    if ($path -match '(dashboard|reports|pos-documents)') { return @('OWNED-P06-P07', 'Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate.') }
    if ($path -match '(pos-branding|pos-theme|portal-navigation|portal-preferences|portal-table-presentation|pos-document-management|pos-branding-management|pos-theme-management)') { return @('REPLACE-P07', 'Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface.') }
    if ($path -match 'resources/views/.*/(layouts|top_bar)' -or $path -match 'resources/views/welcome') { return @('REPLACE-SHELL', 'Legacy realm layouts replaced by the unified Admin and POS React shell.') }
    return @('RETIRE-FRAGMENT', 'Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell.')
}

function Get-RouteTarget([string] $uri) {
    if ($uri -match '/branding') { return 'Unified Admin /platform POS branding data, preview, draft, media, publish or rollback endpoint.' }
    if ($uri -match '/theme') { return 'Unified Admin /platform POS theme data, preview, draft, publish or rollback endpoint.' }
    if ($uri -match '/navigation|/table-presentation|/preferences') { return 'Unified Admin /pos/portal-preferences GET/PUT contract.' }
    if ($uri -match '/shops/') { return 'Unified Admin outlet-management/profile and POS outlet-profile contract (P01/P07 overlap).' }
    return 'Unified Admin /platform and /pos/portal-preferences entry surfaces; legacy Admin/Super Admin realm duplication retired.'
}

$builder = [Text.StringBuilder]::new()
[void] $builder.AppendLine('# MT-7.5 P07 Source File and Route Disposition Crosswalk')
[void] $builder.AppendLine()
[void] $builder.AppendLine('Status: **COMPLETE INVENTORY DISPOSITION; P07 RUNTIME ACCEPTANCE REMAINS OPEN**.')
[void] $builder.AppendLine()
[void] $builder.AppendLine('Authority: `docs/migration/SOURCE_SYMBOL_INVENTORY.json` (pinned MT-1.1 source inventory). This crosswalk disposes every item assigned to P07 without claiming that the remaining rendered dashboard/report, column-presentation, theme/branding propagation or browser gates have passed.')
[void] $builder.AppendLine()
[void] $builder.AppendLine("- Source files: **$($files.Count)/148 disposed**.")
[void] $builder.AppendLine("- Resolved routes: **$($routes.Count)/32 disposed**.")
$hash = (Get-FileHash $inventoryPath -Algorithm SHA256).Hash.ToLowerInvariant()
[void] $builder.AppendLine("- Inventory SHA-256: ``$hash``.")
[void] $builder.AppendLine()
[void] $builder.AppendLine('## Route dispositions')
[void] $builder.AppendLine()
[void] $builder.AppendLine('| # | Source route | Source name | Target disposition |')
[void] $builder.AppendLine('|---:|---|---|---|')
$index = 0
foreach ($route in $routes) {
    $index++
    [void] $builder.AppendLine("| $index | ``$($route.method) $($route.uri)`` | ``$($route.name)`` | $(Get-RouteTarget $route.uri) |")
}
[void] $builder.AppendLine()
[void] $builder.AppendLine('## File dispositions')
[void] $builder.AppendLine()
[void] $builder.AppendLine('| # | Source file | Disposition | Target/evidence |')
[void] $builder.AppendLine('|---:|---|---|---|')
$index = 0
foreach ($file in $files) {
    $index++
    $disposition = Get-FileDisposition $file.path
    [void] $builder.AppendLine("| $index | ``$($file.path)`` | **$($disposition[0])** | $($disposition[1]) |")
}
[void] $builder.AppendLine()
[void] $builder.AppendLine('## Remaining P07 gates')
[void] $builder.AppendLine()
[void] $builder.AppendLine('The inventory and route disposition is complete. P07 remains open until safe column presentation, dashboard/report presentation, full published theme/branding propagation, and final rendered role/outlet browser acceptance are independently verified. Overlapping business behavior remains owned by P01-P06 and P08 and is not reopened by this presentation crosswalk.')
[IO.File]::WriteAllText((Join-Path (Get-Location) $outputPath), $builder.ToString(), [Text.UTF8Encoding]::new($false))

$written = Get-Content $outputPath
$routeRows = @($written | Where-Object { $_ -match '^\| \d+ \| `(GET|PUT|POST|PATCH|DELETE)' }).Count
$allRows = @($written | Where-Object { $_ -match '^\| \d+ \| `' }).Count
$fileRows = $allRows - $routeRows
if ($files.Count -ne 148 -or $routes.Count -ne 32 -or $fileRows -ne 148 -or $routeRows -ne 32) {
    throw "P07 crosswalk count mismatch: source files=$($files.Count), source routes=$($routes.Count), written files=$fileRows, written routes=$routeRows."
}
Write-Output "P07 crosswalk verified: files=$fileRows routes=$routeRows hash=$hash"
