param(
    [switch] $PrepareCleanDatabase,
    [switch] $ConfirmDestructiveRestore
)

$ErrorActionPreference = 'Stop'

if (-not $ConfirmDestructiveRestore) {
    throw 'Pass -ConfirmDestructiveRestore to acknowledge that the dedicated drms_test database will be replaced from its rehearsal dump.'
}

$projectPath = Split-Path -Parent $PSScriptRoot
$environmentPath = Join-Path $projectPath '.env'
$database = 'drms_test'
$mysql = 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe'
$mysqldump = 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqldump.exe'
$markerTable = 'drms_restore_rehearsal_marker'
$markerValue = 'phase-11-restore-rehearsal'

if ($database -notmatch '_test$') {
    throw "Refusing to rehearse against non-test database [$database]."
}

foreach ($requiredPath in @($environmentPath, $mysql, $mysqldump)) {
    if (-not (Test-Path -LiteralPath $requiredPath)) {
        throw "Required path not found: $requiredPath"
    }
}

function Get-EnvironmentValue {
    param([Parameter(Mandatory)][string] $Name)

    $prefix = "$Name="
    $line = Get-Content -LiteralPath $environmentPath |
        Where-Object { $_.StartsWith($prefix, [StringComparison]::Ordinal) } |
        Select-Object -First 1

    if ($null -eq $line) {
        throw "Required environment value [$Name] was not found."
    }

    return $line.Substring($prefix.Length).Trim().Trim('"').Trim("'")
}

function Invoke-MySqlStatement {
    param([Parameter(Mandatory)][string] $Statement)

    & $mysql `
        "--host=$databaseHost" `
        "--port=$databasePort" `
        "--user=$databaseUser" `
        "--database=$database" `
        '--batch' `
        '--skip-column-names' `
        "--execute=$Statement"

    if ($LASTEXITCODE -ne 0) {
        throw "MySQL statement failed with exit code $LASTEXITCODE."
    }
}

$databaseHost = Get-EnvironmentValue -Name 'DB_HOST'
$databasePort = Get-EnvironmentValue -Name 'DB_PORT'
$databaseUser = Get-EnvironmentValue -Name 'DB_USERNAME'
$databasePassword = Get-EnvironmentValue -Name 'DB_PASSWORD'
$temporaryRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$workPath = [IO.Path]::GetFullPath(
    (Join-Path $temporaryRoot ("drms-restore-rehearsal-" + [Guid]::NewGuid().ToString('N')))
)

if (-not $workPath.StartsWith($temporaryRoot, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Resolved rehearsal directory is outside the operating-system temporary directory.'
}

$null = New-Item -ItemType Directory -Path $workPath
$dumpPath = Join-Path $workPath 'drms_test.sql'
$previousMysqlPassword = $env:MYSQL_PWD
$previousDatabase = $env:DB_DATABASE

try {
    $env:MYSQL_PWD = $databasePassword
    $env:DB_DATABASE = $database

    if ($PrepareCleanDatabase) {
        Push-Location $projectPath

        try {
            & php artisan migrate:fresh --seed --force

            if ($LASTEXITCODE -ne 0) {
                throw "Clean migration and seeding failed with exit code $LASTEXITCODE."
            }
        } finally {
            Pop-Location
        }
    }

    Invoke-MySqlStatement -Statement "DROP TABLE IF EXISTS $markerTable; CREATE TABLE $markerTable (marker VARCHAR(64) PRIMARY KEY); INSERT INTO $markerTable (marker) VALUES ('$markerValue');"

    & $mysqldump `
        "--host=$databaseHost" `
        "--port=$databasePort" `
        "--user=$databaseUser" `
        '--single-transaction' `
        '--routines' `
        '--triggers' `
        '--no-tablespaces' `
        '--set-gtid-purged=OFF' `
        "--result-file=$dumpPath" `
        $database

    if ($LASTEXITCODE -ne 0) {
        throw "MySQL backup failed with exit code $LASTEXITCODE."
    }

    $dump = Get-Item -LiteralPath $dumpPath

    if ($dump.Length -le 0) {
        throw 'MySQL backup is empty.'
    }

    $dumpHash = (Get-FileHash -LiteralPath $dumpPath -Algorithm SHA256).Hash

    Invoke-MySqlStatement -Statement "DROP TABLE $markerTable;"

    $mysqlSourcePath = $dumpPath.Replace('\', '/')

    & $mysql `
        "--host=$databaseHost" `
        "--port=$databasePort" `
        "--user=$databaseUser" `
        "--database=$database" `
        "--execute=source $mysqlSourcePath"

    if ($LASTEXITCODE -ne 0) {
        throw "MySQL restore failed with exit code $LASTEXITCODE."
    }

    $restoredMarker = Invoke-MySqlStatement -Statement "SELECT marker FROM $markerTable WHERE marker = '$markerValue';"

    if ($restoredMarker -ne $markerValue) {
        throw 'The restored rehearsal marker did not match the backed-up value.'
    }

    Invoke-MySqlStatement -Statement "DROP TABLE $markerTable;"

    Write-Output "REHEARSAL_DATABASE`t$database"
    Write-Output "BACKUP_BYTES`t$($dump.Length)"
    Write-Output "BACKUP_SHA256`t$dumpHash"
    Write-Output "RESTORE_MARKER`tVERIFIED"
    Write-Output "REHEARSAL_RESULT`tPASSED"
} finally {
    if ($null -eq $previousMysqlPassword) {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    } else {
        $env:MYSQL_PWD = $previousMysqlPassword
    }

    if ($null -eq $previousDatabase) {
        Remove-Item Env:DB_DATABASE -ErrorAction SilentlyContinue
    } else {
        $env:DB_DATABASE = $previousDatabase
    }

    if (Test-Path -LiteralPath $workPath) {
        $resolvedWorkPath = [IO.Path]::GetFullPath($workPath)

        if ($resolvedWorkPath.StartsWith($temporaryRoot, [StringComparison]::OrdinalIgnoreCase)) {
            Remove-Item -LiteralPath $resolvedWorkPath -Recurse -Force
        }
    }
}
