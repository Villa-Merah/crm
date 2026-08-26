[CmdletBinding()]
param(
    [switch] $NoBuild
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

$projectRoot = Split-Path -Parent $PSScriptRoot
$composeFile = Join-Path $PSScriptRoot 'compose.yml'
$dockerEnvFile = Join-Path $PSScriptRoot '.env'
$dockerEnvExample = Join-Path $PSScriptRoot '.env.example'

Set-Location $projectRoot

function New-RandomHex {
    param([int] $ByteCount = 32)

    $bytes = New-Object byte[] $ByteCount
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()

    try {
        $generator.GetBytes($bytes)
    } finally {
        $generator.Dispose()
    }

    return ([BitConverter]::ToString($bytes) -replace '-', '').ToLowerInvariant()
}

function New-LaravelAppKey {
    $bytes = New-Object byte[] 32
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()

    try {
        $generator.GetBytes($bytes)
    } finally {
        $generator.Dispose()
    }

    return 'base64:' + [Convert]::ToBase64String($bytes)
}

function Set-DotEnvValue {
    param(
        [string] $Content,
        [string] $Name,
        [string] $Value
    )

    $pattern = '(?m)^' + [Regex]::Escape($Name) + '=.*$'

    if ([Regex]::IsMatch($Content, $pattern)) {
        return [Regex]::Replace($Content, $pattern, "$Name=$Value")
    }

    return $Content.TrimEnd() + [Environment]::NewLine + "$Name=$Value" + [Environment]::NewLine
}

if (-not (Test-Path -LiteralPath $dockerEnvFile)) {
    Copy-Item -LiteralPath $dockerEnvExample -Destination $dockerEnvFile
    Write-Host 'docker/.env dibuat dari docker/.env.example.' -ForegroundColor Green
}

$envContent = Get-Content -LiteralPath $dockerEnvFile -Raw

if ($envContent -match '(?m)^APP_KEY=(?:base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=)?$') {
    $envContent = Set-DotEnvValue $envContent 'APP_KEY' (New-LaravelAppKey)
    Write-Host 'APP_KEY lokal dibuat otomatis.' -ForegroundColor Green
}

if ($envContent -match '(?m)^WAHA_API_KEY=(?:sivmi-waha-local-change-me|GANTI_.*)?$') {
    $envContent = Set-DotEnvValue $envContent 'WAHA_API_KEY' (New-RandomHex)
    Write-Host 'WAHA_API_KEY lokal dibuat otomatis.' -ForegroundColor Green
}

if ($envContent -match '(?m)^WAHA_WEBHOOK_HMAC_SECRET=(?:sivmi-webhook-local-change-me|GANTI_.*)?$') {
    $envContent = Set-DotEnvValue $envContent 'WAHA_WEBHOOK_HMAC_SECRET' (New-RandomHex)
    Write-Host 'WAHA_WEBHOOK_HMAC_SECRET lokal dibuat otomatis.' -ForegroundColor Green
}

if ($envContent -match '(?m)^WAHA_DASHBOARD_PASSWORD=(?:sivmi-waha-dashboard-local-change-me|GANTI_.*)?$') {
    $envContent = Set-DotEnvValue $envContent 'WAHA_DASHBOARD_PASSWORD' (New-RandomHex 18)
    Write-Host 'Password dashboard WAHA lokal dibuat otomatis.' -ForegroundColor Green
}

$utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($dockerEnvFile, $envContent, $utf8WithoutBom)

docker info *> $null

if ($LASTEXITCODE -ne 0) {
    throw 'Docker Engine belum aktif. Buka Docker Desktop, tunggu sampai Engine running, lalu jalankan kembali .\docker\start-full.ps1.'
}

docker compose --env-file $dockerEnvFile -f $composeFile config --quiet

$composeArguments = @(
    'compose',
    '--env-file', $dockerEnvFile,
    '-f', $composeFile,
    '--profile', 'workers',
    'up', '-d'
)

if (-not $NoBuild) {
    $composeArguments += '--build'
}

& docker @composeArguments

if ($LASTEXITCODE -ne 0) {
    throw "Docker Compose gagal dijalankan dengan exit code $LASTEXITCODE."
}

# Nginx may retain the previous PHP-FPM container IP when app is recreated.
# Recreate web so it also picks up configuration changes immediately.
docker compose --env-file $dockerEnvFile -f $composeFile up -d --force-recreate web

if ($LASTEXITCODE -ne 0) {
    throw "Container web gagal dibuat ulang dengan exit code $LASTEXITCODE."
}

Write-Host 'Menunggu aplikasi siap...' -ForegroundColor Cyan
$deadline = (Get-Date).AddMinutes(5)
$applicationReady = $false
$wahaReady = $false

do {
    Start-Sleep -Seconds 5

    try {
        $applicationResponse = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8000/up' -TimeoutSec 5
        $applicationReady = $applicationResponse.StatusCode -eq 200
    } catch {
        $applicationReady = $false
    }

    try {
        $wahaResponse = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:3000/health' -TimeoutSec 5
        $wahaReady = $wahaResponse.StatusCode -eq 200
    } catch {
        $wahaReady = $false
    }
} while ((-not $applicationReady -or -not $wahaReady) -and (Get-Date) -lt $deadline)

docker compose --env-file $dockerEnvFile -f $composeFile --profile workers ps

if (-not $applicationReady) {
    Write-Warning 'Aplikasi belum menjawab health check. Periksa: .\docker\logs-full.ps1 app'
}

if (-not $wahaReady) {
    Write-Warning 'WAHA belum menjawab health check. Periksa: .\docker\logs-full.ps1 waha'
}

Write-Host ''
Write-Host 'SIVMI CRM : http://localhost:8000/admin/login' -ForegroundColor Green
Write-Host 'WAHA      : http://localhost:3000/dashboard' -ForegroundColor Green
Write-Host 'Status    : .\docker\status-full.ps1' -ForegroundColor Cyan
Write-Host 'Stop      : .\docker\stop-full.ps1' -ForegroundColor Cyan
