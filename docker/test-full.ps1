[CmdletBinding()]
param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $TestArguments = @()
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$composeFile = Join-Path $PSScriptRoot 'compose.yml'
$dockerEnvFile = Join-Path $PSScriptRoot '.env'

Set-Location $projectRoot

$dockerArguments = @(
    'compose',
    '--env-file', $dockerEnvFile,
    '-f', $composeFile,
    'exec', '-T',
    '-e', 'APP_ENV=testing',
    '-e', 'APP_DEBUG=false',
    '-e', 'DB_CONNECTION=sqlite',
    '-e', 'DB_DATABASE=:memory:',
    '-e', 'CACHE_STORE=array',
    '-e', 'QUEUE_CONNECTION=sync',
    '-e', 'SESSION_DRIVER=array',
    '-e', 'MAIL_MAILER=array',
    '-e', 'BROADCAST_CONNECTION=null',
    'app', 'php', 'artisan', 'test', '--compact'
)

$dockerArguments += $TestArguments

& docker @dockerArguments

if ($LASTEXITCODE -ne 0) {
    throw "Test aplikasi gagal dengan exit code $LASTEXITCODE."
}

