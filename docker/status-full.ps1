[CmdletBinding()]
param()

$projectRoot = Split-Path -Parent $PSScriptRoot
$composeFile = Join-Path $PSScriptRoot 'compose.yml'
$dockerEnvFile = Join-Path $PSScriptRoot '.env'

Set-Location $projectRoot
docker compose --env-file $dockerEnvFile -f $composeFile --profile workers ps

