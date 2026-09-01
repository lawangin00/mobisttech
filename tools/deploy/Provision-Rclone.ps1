param([switch]$Install)

$ErrorActionPreference = 'Stop'
$command = Get-Command rclone -ErrorAction SilentlyContinue
if (-not $command) {
    if (-not $Install) {
        throw 'rclone is required. Re-run with -Install on a Windows host with winget.'
    }
    winget install --id Rclone.Rclone --exact --accept-package-agreements --accept-source-agreements
    $command = Get-Command rclone -ErrorAction Stop
}

$privateDirectory = if ($env:RCLONE_PRIVATE_DIR) { $env:RCLONE_PRIVATE_DIR } else { Join-Path (Get-Location) 'backend\storage\app\private\integrations' }
New-Item -ItemType Directory -Force -Path $privateDirectory | Out-Null
$configPath = if ($env:RCLONE_CONFIG_PATH) { $env:RCLONE_CONFIG_PATH } else { Join-Path $privateDirectory 'rclone.conf' }
if (-not (Test-Path -LiteralPath $configPath)) { New-Item -ItemType File -Path $configPath | Out-Null }
icacls $privateDirectory /inheritance:r /grant:r "${env:USERNAME}:(OI)(CI)F" | Out-Null
& $command.Source version | Select-Object -First 1
Write-Output "Private rclone configuration ready at $configPath"
