param(
    [ValidateSet('Prepare', 'Build', 'Check', 'Restart', 'Cleanup', 'All')]
    [string]$Stage = 'All',
    [ValidatePattern('^traepe-ci-[a-z0-9-]+$')]
    [string]$Project = 'traepe-ci-local'
)

$ErrorActionPreference = 'Stop'
$env:COMPOSE_PROJECT_NAME = $Project
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
Set-Location -LiteralPath $root
$envPath = Join-Path $root '.ci-cache/runtime.env'
$compose = @('compose', '--project-name', $Project, '--env-file', $envPath, '-f', 'compose.yaml', '-f', 'compose.ci.yaml')

function Invoke-Compose {
    param([string[]]$Arguments)
    & docker @compose @Arguments
    if ($LASTEXITCODE -ne 0) { throw "CI command failed: $($Arguments[0]) (exit $LASTEXITCODE)." }
}

function New-Secret {
    [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()
}

function Prepare-CI {
    if (Test-Path -LiteralPath $envPath) { throw 'CI runtime.env already exists; use a fresh disposable checkout or clean its CI resources first.' }
    foreach ($kind in @('container', 'network', 'volume')) {
        $resources = & docker $kind ls -q --filter "label=com.docker.compose.project=$Project"
        if ($LASTEXITCODE -ne 0) { throw 'Could not inspect existing CI resources.' }
        if ($resources) { throw 'CI project already owns resources; choose a fresh project identity instead of reusing them.' }
    }
    New-Item -ItemType Directory -Path '.ci-cache/composer' -Force | Out-Null
    $appKey = 'base64:' + [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    $values = @(
        "COMPOSE_PROJECT_NAME=$Project", "TRAEPE_APP_KEY=$appKey",
        "TRAEPE_POSTGRES_PASSWORD=$(New-Secret)", "TRAEPE_REDIS_PASSWORD=$(New-Secret)",
        "TRAEPE_TECHNICAL_PASSWORD=$(New-Secret)", "TRAEPE_REVERB_APP_KEY=$(New-Secret)",
        "TRAEPE_REVERB_APP_SECRET=$(New-Secret)", "TRAEPE_MINIO_ROOT_PASSWORD=$(New-Secret)",
        "TRAEPE_MINIO_APP_PASSWORD=$(New-Secret)"
    )
    [IO.File]::WriteAllLines($envPath, $values, [Text.UTF8Encoding]::new($false))
    if (-not (Test-Path -LiteralPath 'apps/api/.env')) { Copy-Item -LiteralPath 'apps/api/.env.example' -Destination 'apps/api/.env' }
    Invoke-Compose -Arguments @('config', '--quiet')
}

function Build-CI {
    Invoke-Compose -Arguments @('build', 'api', 'minio')
    Invoke-Compose -Arguments @('up', '-d', '--wait', '--wait-timeout', '180', 'postgres', 'redis', 'api')
    # The API network is deliberately internal. Download only during installation.
    $container = & docker @compose ps -q api
    & docker network connect "${Project}_host" $container
    if ($LASTEXITCODE -ne 0) { throw 'Could not enable temporary Composer network.' }
    try {
        Invoke-Compose -Arguments @('exec', '-T', 'api', 'composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress')
        Invoke-Compose -Arguments @('exec', '-T', 'api', 'composer', 'validate', '--strict', '--no-check-publish')
        Invoke-Compose -Arguments @('exec', '-T', 'api', 'composer', 'audit', '--locked', '--no-interaction')
    } finally {
        & docker network disconnect "${Project}_host" $container
        if ($LASTEXITCODE -ne 0) { throw 'Could not restore isolated API network.' }
    }
    Invoke-Compose -Arguments @('exec', '-T', 'api', 'php', 'artisan', 'migrate', '--force', '--no-interaction')
    Invoke-Compose -Arguments @('up', '-d', '--wait', '--wait-timeout', '180')
}

function Check-CI {
    Invoke-Compose -Arguments @('exec', '-T', 'api', 'composer', 'quality')
    Invoke-Compose -Arguments @('exec', '-T', 'api', 'composer', 'test:integration')
    Invoke-Compose -Arguments @('exec', '-T', 'api', 'php', 'tests/Support/verify-quality-gates.php')
    foreach ($mode in @('smoke', 'migrations', 'queue', 'failed-job', 'websocket')) {
        Invoke-Compose -Arguments @('exec', '-T', 'api', 'php', 'tests/Support/verify-infrastructure.php', $mode)
    }
    Invoke-Compose -Arguments @('exec', '-T', 'api', 'php', 'tests/Support/verify-delivery.php')
    Invoke-Compose -Arguments @('exec', '-T', 'api', 'php', 'tests/Support/lint-technical-event.php')
    Invoke-Compose -Arguments @('exec', '-T', 'api', 'php', 'tests/Support/verify-foundation.php')
    Invoke-Compose -Arguments @('config', '--quiet')
}

function Restart-CI {
    Invoke-Compose -Arguments @('exec', '-T', 'api', 'php', 'tests/Support/verify-infrastructure.php', 'persist-write')
    Invoke-Compose -Arguments @('restart')
    Invoke-Compose -Arguments @('up', '-d', '--wait', '--wait-timeout', '180')
    Invoke-Compose -Arguments @('exec', '-T', 'api', 'php', 'tests/Support/verify-infrastructure.php', 'persist-read')
    Invoke-Compose -Arguments @('exec', '-T', 'api', 'php', 'tests/Support/verify-foundation.php')
}

function Cleanup-CI {
    # Destruction is limited to this disposable CI project's labelled resources.
    if (-not (Test-Path -LiteralPath $envPath)) { return }
    $recorded = Get-Content -LiteralPath $envPath | Where-Object { $_ -like 'COMPOSE_PROJECT_NAME=*' }
    if ($recorded -cne "COMPOSE_PROJECT_NAME=$Project") { throw 'CI cleanup project does not match its runtime.env.' }
    $volumeNames = & docker volume ls --filter "label=com.docker.compose.project=$Project" --format '{{.Name}}'
    if ($LASTEXITCODE -ne 0) { throw 'Could not inspect CI volumes for cleanup.' }
    foreach ($volumeName in $volumeNames) {
        if (-not $volumeName.StartsWith("${Project}_", [StringComparison]::Ordinal)) { throw 'CI volume is outside the allowed project prefix.' }
    }
    Invoke-Compose -Arguments @('down', '--remove-orphans')
    foreach ($volumeName in $volumeNames) {
        & docker volume rm $volumeName
        if ($LASTEXITCODE -ne 0) { throw 'Could not remove an isolated CI volume.' }
    }
    Remove-Item -LiteralPath $envPath
}

switch ($Stage) {
    'Prepare' { Prepare-CI }
    'Build' { Build-CI }
    'Check' { Check-CI }
    'Restart' { Restart-CI }
    'Cleanup' { Cleanup-CI }
    'All' {
        Prepare-CI
        try { Build-CI; Check-CI; Restart-CI } finally { Cleanup-CI }
    }
}
