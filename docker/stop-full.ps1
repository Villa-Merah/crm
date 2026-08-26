[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$composeFile = Join-Path $PSScriptRoot 'compose.yml'
$dockerEnvFile = Join-Path $PSScriptRoot '.env'

Set-Location $projectRoot
docker compose --env-file $dockerEnvFile -f $composeFile --profile workers down

if ($LASTEXITCODE -ne 0) {
    throw "Docker Compose gagal dihentikan dengan exit code $LASTEXITCODE."
}

Write-Host 'Seluruh container SIVMI CRM dihentikan. Volume database dan session WAHA tetap disimpan.' -ForegroundColor Green

