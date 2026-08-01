param(
    [string] $PhpExecutable = 'php',
    [ValidateRange(1, 60)]
    [int] $RestartDelaySeconds = 2,
    [ValidateRange(5, 120)]
    [int] $StaleRefreshMinutes = 15
)

$ErrorActionPreference = 'Stop'
$projectPath = Split-Path -Parent $PSScriptRoot
$refreshSentinel = Join-Path $projectPath 'storage\framework\drms-refreshing'
$phpCommand = Get-Command -Name $PhpExecutable -ErrorAction Stop
$phpPath = $phpCommand.Source
$phpVersionLine = & $phpPath --version | Select-Object -First 1

if ($phpVersionLine -notmatch '^PHP\s+(\d+\.\d+\.\d+)') {
    throw "Unable to determine the PHP version from [$phpVersionLine]."
}

$phpVersion = $Matches[1]

if ([version] $phpVersion -lt [version] '8.4') {
    throw "DRMS requires PHP 8.4 or later; resolved PHP $phpVersion at $phpPath."
}

function Test-RefreshInProgress {
    if (-not (Test-Path -LiteralPath $refreshSentinel)) {
        return $false
    }

    $sentinel = Get-Item -LiteralPath $refreshSentinel
    $age = (Get-Date) - $sentinel.LastWriteTime

    if ($age.TotalMinutes -gt $StaleRefreshMinutes) {
        Write-Warning "Removing stale DRMS refresh sentinel created at $($sentinel.LastWriteTime)."
        Remove-Item -LiteralPath $refreshSentinel -Force

        return $false
    }

    return $true
}

Push-Location $projectPath

try {
    while ($true) {
        while (Test-RefreshInProgress) {
            Write-Host 'Database refresh in progress; notification worker is paused.'
            Start-Sleep -Seconds $RestartDelaySeconds
        }

        & $phpPath artisan queue:work `
            --queue=notifications,default `
            --tries=3 `
            --timeout=90 `
            --sleep=1 `
            --max-time=3600

        $workerExit = $LASTEXITCODE

        if (Test-RefreshInProgress) {
            Write-Host 'Notification worker stopped for database refresh.'
        } elseif ($workerExit -eq 0) {
            Write-Host 'Notification worker stopped normally; restarting.'
        } else {
            Write-Warning "Notification worker exited with code $workerExit; restarting."
        }

        Start-Sleep -Seconds $RestartDelaySeconds
    }
} finally {
    Pop-Location
}
