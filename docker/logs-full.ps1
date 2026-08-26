[CmdletBinding()]
param(
    [ValidateSet('app', 'web', 'database', 'redis', 'node', 'queue', 'scheduler', 'waha')]
    [string] $Service = 'app',
    [int] $Tail = 200
)

$projectRoot = Split-Path -Parent $PSScriptRoot
$composeFile = Join-Path $PSScriptRoot 'compose.yml'
$dockerEnvFile = Join-Path $PSScriptRoot '.env'

Set-Location $projectRoot
docker compose --env-file $dockerEnvFile -f $composeFile --profile workers logs --tail $Tail $Service

