param([Parameter(Mandatory)][ValidateSet('Start','Stop','Status')][string]$Action)
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
if ($projectRoot -ne 'C:\mobisttech') { throw 'This helper is limited to C:\mobisttech.' }
$stateRoot = Join-Path $projectRoot '.local/mysql'
$mysqlBin = Join-Path $projectRoot '.local/runtime/mysql-8.4.11-winx64/bin'
$serverExe = Join-Path $mysqlBin 'mysqld.exe'
$recordPath = Join-Path $stateRoot 'owned-process.json'
$record = if (Test-Path $recordPath) { Get-Content $recordPath -Raw | ConvertFrom-Json } else { $null }
$owned = $null
if ($record) {
    $candidate = Get-Process -Id $record.processId -ErrorAction SilentlyContinue
    if ($candidate -and $candidate.Path -eq $serverExe -and $candidate.StartTime.ToUniversalTime().Ticks.ToString() -eq $record.startTicks) { $owned = $candidate }
}
if ($Action -eq 'Status') { if ($owned) { 'Running: isolated target MySQL' } else { 'Stopped: isolated target MySQL' }; exit 0 }
if ($Action -eq 'Stop') {
    if (-not $owned) { 'No owned MySQL process to stop.'; exit 0 }
    & (Join-Path $mysqlBin 'mysqladmin.exe') "--defaults-extra-file=$(Join-Path $stateRoot 'admin.cnf')" shutdown
    if ($LASTEXITCODE -ne 0) { throw 'Target shutdown failed; no process was force-killed.' }
    $owned.WaitForExit(15000) | Out-Null
    if (-not $owned.HasExited) { throw 'Target MySQL has not stopped.' }
    Remove-Item -LiteralPath $recordPath
    'Stopped: isolated target MySQL'; exit 0
}
if ($owned) { 'Already running: isolated target MySQL'; exit 0 }
$listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,13306)
try { $listener.Start() } finally { $listener.Stop() }
$arguments = @("--defaults-file=$($stateRoot.Replace('\','/'))/my.ini")
$initPath = Join-Path $stateRoot 'initialize.sql'
if (Test-Path $initPath) { $arguments += "--init-file=$($initPath.Replace('\','/'))" }
$server = Start-Process -FilePath $serverExe -ArgumentList $arguments -WindowStyle Hidden -PassThru
@{ processId=$server.Id; startTicks=$server.StartTime.ToUniversalTime().Ticks.ToString() } | ConvertTo-Json | Set-Content -LiteralPath $recordPath
for ($attempt=0; $attempt -lt 30; $attempt++) {
    & (Join-Path $mysqlBin 'mysqladmin.exe') "--defaults-extra-file=$(Join-Path $stateRoot 'admin.cnf')" ping --silent 2>$null
    if ($LASTEXITCODE -eq 0) {
        if (Test-Path $initPath) { Remove-Item -LiteralPath $initPath }
        'Running: isolated target MySQL'; exit 0
    }
    if ($server.HasExited) { throw 'Target MySQL exited; inspect .local/mysql/mysql-error.log.' }
    Start-Sleep -Milliseconds 500
}
throw 'Target MySQL readiness timed out; inspect the owned process and target-only log.'
