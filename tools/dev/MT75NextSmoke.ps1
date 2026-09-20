$ErrorActionPreference = 'Stop'
Set-Location 'C:\mobisttech'
$env:MT75_FIRST_OUTLET_E2E_ENABLED = '1'
$failed = $false
try {
    & .\tools\dev\Database.ps1 Start
    if ($LASTEXITCODE -ne 0) { throw 'Isolated MySQL failed.' }
    Push-Location .\backend
    try {
        php artisan db:seed --class='Database\Seeders\FirstOutletE2eCleanupSeeder' --env=testing --force
        if ($LASTEXITCODE -ne 0) { throw 'Initial fixture cleanup failed.' }
        php artisan db:seed --class='Database\Seeders\FirstOutletE2eSeeder' --env=testing --force
        if ($LASTEXITCODE -ne 0) { throw 'First owner fixture failed.' }
    } finally { Pop-Location }
    & .\tools\dev\BackendWebServer.ps1 Start
    if ($LASTEXITCODE -ne 0) { throw 'Isolated backend start failed.' }
    Push-Location .\backend
    try {
        npx playwright test --config=playwright.mt75-next.config.ts --workers=1 --reporter=line
        if ($LASTEXITCODE -ne 0) { $failed = $true }
    } finally { Pop-Location }
} finally {
    Push-Location .\backend
    try {
        php artisan db:seed --class='Database\Seeders\FirstOutletE2eCleanupSeeder' --env=testing --force
        if ($LASTEXITCODE -ne 0) { $failed = $true }
    } finally { Pop-Location }
    & .\tools\dev\BackendWebServer.ps1 Stop
    if ($LASTEXITCODE -ne 0) { $failed = $true }
    & .\tools\dev\Database.ps1 Stop
    if ($LASTEXITCODE -ne 0) { $failed = $true }
}
if ($failed) { throw 'MT75 Next.js checkout acceptance or cleanup failed.' }
'NEXT_CHECKOUT_FIXTURE_PASS_AND_OWNED_SERVICES_STOPPED'
Get-Date -Format 'HH:mm:ss'
