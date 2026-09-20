param([Parameter(Mandatory)][ValidateSet('Start','Stop','Status')][string]$Action)
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
if ($projectRoot -ne 'C:\mobisttech') { throw 'This helper is limited to C:\mobisttech.' }

$backendRoot = Join-Path $projectRoot 'backend'
$phpExe = (Get-Command php -CommandType Application).Source
$recordPath = Join-Path $projectRoot '.local/backend-web-server.json'
$logPath = Join-Path $backendRoot 'storage/framework/testing/external-server.log'
$errorPath = Join-Path $backendRoot 'storage/framework/testing/external-server.err.log'
$record = if (Test-Path $recordPath) { Get-Content $recordPath -Raw | ConvertFrom-Json } else { $null }
$owned = $null
if ($record) {
    $candidate = Get-Process -Id $record.processId -ErrorAction SilentlyContinue
    if ($candidate -and $candidate.Path -eq $phpExe -and $candidate.StartTime.ToUniversalTime().Ticks.ToString() -eq $record.startTicks) {
        $owned = $candidate
    }
}

if ($Action -eq 'Status') {
    if ($owned) { 'Running: isolated backend test server' } else { 'Stopped: isolated backend test server' }
    exit 0
}
if ($Action -eq 'Stop') {
    if (-not $owned) { 'No owned backend test server to stop.'; exit 0 }
    Stop-Process -Id $owned.Id -Force
    $owned.WaitForExit(5000) | Out-Null
    if (-not $owned.HasExited) { throw 'Backend test server has not stopped.' }
    Remove-Item -LiteralPath $recordPath
    'Stopped: isolated backend test server'
    exit 0
}
if ($owned) { 'Already running: isolated backend test server'; exit 0 }

$listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 18080)
try { $listener.Start() } finally { $listener.Stop() }
Remove-Item -LiteralPath $logPath,$errorPath -Force -ErrorAction SilentlyContinue
$server = Start-Process -FilePath $phpExe -ArgumentList @(
    '-S', '127.0.0.1:18080',
    (Join-Path $backendRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')
) -WorkingDirectory (Join-Path $backendRoot 'public') -WindowStyle Hidden -Environment @{ APP_ENV='testing' } -RedirectStandardOutput $logPath -RedirectStandardError $errorPath -PassThru
@{ processId=$server.Id; startTicks=$server.StartTime.ToUniversalTime().Ticks.ToString() } | ConvertTo-Json | Set-Content -LiteralPath $recordPath
for ($attempt=0; $attempt -lt 30; $attempt++) {
    try {
        $response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:18080/internal/admin/pos/login' -TimeoutSec 2
        if ($response.StatusCode -eq 200) { 'Running: isolated backend test server'; exit 0 }
    } catch {}
    if ($server.HasExited) { throw 'Backend test server exited; inspect the target-only server log.' }
    Start-Sleep -Milliseconds 250
}
throw 'Backend test server readiness timed out.'
