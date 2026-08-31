param([switch]$DownloadMySql)
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
if ($projectRoot -ne 'C:\mobisttech') { throw 'This setup is limited to C:\mobisttech.' }
$localRoot = Join-Path $projectRoot '.local'
$mysqlRoot = Join-Path $localRoot 'runtime/mysql-8.4.11-winx64'
$mysqlExe = Join-Path $mysqlRoot 'bin/mysqld.exe'
$stateRoot = Join-Path $localRoot 'mysql'
$envPath = Join-Path $projectRoot 'backend/.env'
if ((Test-Path (Join-Path $stateRoot 'data')) -or (Test-Path $envPath)) {
    throw 'Existing target state found. Setup never overwrites credentials or initializes an existing database.'
}
if ((& php -r 'echo PHP_VERSION;') -ne '8.3.33') { throw 'Use the pinned PHP 8.3.33 runtime.' }
if ((& node --version) -ne 'v24.19.0') { throw 'Use the pinned Node.js 24.19.0 runtime.' }
if ((& npm.cmd --version) -ne '11.17.0') { throw 'Use the pinned npm 11.17.0 runtime.' }
foreach ($directory in @('backend/bootstrap/cache','backend/storage/app/private','backend/storage/app/public',
    'backend/storage/logs','backend/storage/framework/cache/data','backend/storage/framework/sessions',
    'backend/storage/framework/views','backend/storage/framework/testing')) {
    New-Item -ItemType Directory -Force -Path (Join-Path $projectRoot $directory) | Out-Null
}
foreach ($port in @(13306, 18080, 13000, 15173)) {
    $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, $port)
    try { $listener.Start() } finally { $listener.Stop() }
}
if (-not (Test-Path $mysqlExe)) {
    if (-not $DownloadMySql) { throw 'Portable MySQL is missing. Use -DownloadMySql to obtain the official pinned ZIP.' }
    $archive = Join-Path $localRoot 'downloads/mysql-8.4.11-winx64.zip'
    New-Item -ItemType Directory -Force -Path (Split-Path $archive) | Out-Null
    Invoke-WebRequest 'https://cdn.mysql.com/Downloads/MySQL-8.4/mysql-8.4.11-winx64.zip' -OutFile $archive
    if ((Get-FileHash $archive -Algorithm SHA256).Hash -ne 'A492371D687D2BAB088B0062581144A0044B8964BAEFDF4FAA579292B423D25C') {
        throw 'MySQL archive checksum mismatch.'
    }
    Expand-Archive -LiteralPath $archive -DestinationPath (Join-Path $localRoot 'runtime')
}
New-Item -ItemType Directory -Force -Path $stateRoot,(Join-Path $stateRoot 'files') | Out-Null
function New-LocalPassword {
    $bytes = [byte[]]::new(32)
    [Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    return [Convert]::ToHexString($bytes).ToLowerInvariant()
}
$adminPassword = New-LocalPassword
$appPassword = New-LocalPassword
$unixBase = $mysqlRoot.Replace('\','/')
$unixState = $stateRoot.Replace('\','/')
@"
[mysqld]
basedir=$unixBase
datadir=$unixState/data
port=13306
bind-address=127.0.0.1
mysqlx=0
local-infile=0
secure-file-priv=$unixState/files
max-connections=30
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
default-time-zone=+00:00
log-error=$unixState/mysql-error.log
pid-file=$unixState/mysql.pid
"@ | Set-Content -LiteralPath (Join-Path $stateRoot 'my.ini') -Encoding utf8NoBOM
@"
[client]
host=127.0.0.1
port=13306
protocol=TCP
user=root
password=$adminPassword
"@ | Set-Content -LiteralPath (Join-Path $stateRoot 'admin.cnf') -Encoding utf8NoBOM
@"
ALTER USER 'root'@'localhost' IDENTIFIED BY '$adminPassword';
CREATE DATABASE mobisttech_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE mobisttech_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mobisttech'@'localhost' IDENTIFIED BY '$appPassword';
GRANT ALL PRIVILEGES ON mobisttech_local.* TO 'mobisttech'@'localhost';
GRANT ALL PRIVILEGES ON mobisttech_test.* TO 'mobisttech'@'localhost';
"@ | Set-Content -LiteralPath (Join-Path $stateRoot 'initialize.sql') -Encoding utf8NoBOM
& $mysqlExe "--defaults-file=$(Join-Path $stateRoot 'my.ini')" --initialize-insecure
if ($LASTEXITCODE -ne 0) { throw 'Isolated MySQL initialization failed; inspect target-only log.' }
$envText = [IO.File]::ReadAllText((Join-Path $projectRoot 'backend/.env.example'))
$keyBytes = [byte[]]::new(32)
[Security.Cryptography.RandomNumberGenerator]::Fill($keyBytes)
$envText = $envText.Replace('APP_KEY=', 'APP_KEY=base64:' + [Convert]::ToBase64String($keyBytes))
$envText = $envText.Replace('DB_PASSWORD=', 'DB_PASSWORD=' + $appPassword)
[IO.File]::WriteAllText($envPath, $envText)
$testEnv = $envText.Replace('APP_ENV=local','APP_ENV=testing').Replace('DB_DATABASE=mobisttech_local','DB_DATABASE=mobisttech_test')
[IO.File]::WriteAllText((Join-Path $projectRoot 'backend/.env.testing'), $testEnv)
if (-not (Test-Path (Join-Path $projectRoot 'website/.env.local'))) {
    Copy-Item -LiteralPath (Join-Path $projectRoot 'website/.env.example') -Destination (Join-Path $projectRoot 'website/.env.local')
}
Write-Output 'Isolated target initialized. Credentials are local/ignored and were not printed. Run Database.ps1 Start next.'
