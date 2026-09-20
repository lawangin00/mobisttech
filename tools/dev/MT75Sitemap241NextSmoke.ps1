# W02 test-only full Next.js XML sitemap; protected owner first publishes three products.
$ErrorActionPreference = 'Stop'
Set-Location 'C:\mobisttech'
$env:MT75_FIRST_OUTLET_E2E_ENABLED = '1'
$env:MT75_SITEMAP241_ENABLED = '1'
$env:MT75_SITEMAP241_ACTION = 'cleanup'
$failed = $false
try {
    & .\tools\dev\Database.ps1 Start
    if ($LASTEXITCODE -ne 0) { throw 'Isolated DB start failed.' }
    Push-Location .\backend
    try {
        php artisan db:seed --class='Database\Seeders\Sitemap241E2eSeeder' --env=testing --force
        if ($LASTEXITCODE -ne 0) { throw 'Initial 241-fixture cleanup failed.' }
        php artisan db:seed --class='Database\Seeders\FirstOutletE2eCleanupSeeder' --env=testing --force
        if ($LASTEXITCODE -ne 0) { throw 'Initial owner cleanup failed.' }
        php artisan db:seed --class='Database\Seeders\FirstOutletE2eSeeder' --env=testing --force
        if ($LASTEXITCODE -ne 0) { throw 'Synthetic owner seed failed.' }
    } finally { Pop-Location }
    & .\tools\dev\BackendWebServer.ps1 Start
    if ($LASTEXITCODE -ne 0) { throw 'Isolated backend start failed.' }
    Push-Location .\backend
    try {
        npx playwright test --config=playwright.mt75-next.config.ts --workers=1 --reporter=line -g 'MT75 fresh publication|MT75 public sitemap 241'
        if ($LASTEXITCODE -ne 0) { $failed = $true }
    } finally { Pop-Location }
} finally {
    $env:MT75_SITEMAP241_ACTION = 'cleanup'
    Push-Location .\backend
    try {
        php artisan db:seed --class='Database\Seeders\Sitemap241E2eSeeder' --env=testing --force
        if ($LASTEXITCODE -ne 0) { $failed = $true }
        php artisan db:seed --class='Database\Seeders\FirstOutletE2eCleanupSeeder' --env=testing --force
        if ($LASTEXITCODE -ne 0) { $failed = $true }
    } finally { Pop-Location }
    & .\tools\dev\BackendWebServer.ps1 Stop
    if ($LASTEXITCODE -ne 0) { $failed = $true }
    & .\tools\dev\Database.ps1 Stop
    if ($LASTEXITCODE -ne 0) { $failed = $true }
}
if ($failed) { throw 'MT75 241-sitemap real Next acceptance or guarded cleanup failed.' }
'MT75_241_REAL_NEXT_SITEMAP_PASS_AND_OWNED_SERVICES_STOPPED'
Get-Date -Format 'yyyy-MM-ddTHH:mm:ss.fffffffzzz'
