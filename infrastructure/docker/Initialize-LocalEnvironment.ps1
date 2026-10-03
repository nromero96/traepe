$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$dockerPath = Join-Path $root '.env.docker'
$apiPath = Join-Path $root 'apps/api/.env'
if ((Test-Path -LiteralPath $dockerPath) -or (Test-Path -LiteralPath $apiPath)) {
    throw 'An environment file already exists. Preserve it and configure missing values manually; this initializer never overwrites it.'
}

$docker = [IO.File]::ReadAllText((Join-Path $root '.env.docker.example'))
$api = [IO.File]::ReadAllText((Join-Path $root 'apps/api/.env.example'))
$appKey = 'base64:' + [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$docker = [regex]::Replace($docker, '(?m)^TRAEPE_APP_KEY=.*$', "TRAEPE_APP_KEY=$appKey")
$api = [regex]::Replace($api, '(?m)^APP_KEY=.*$', "APP_KEY=$appKey")
foreach ($name in @('TRAEPE_POSTGRES_PASSWORD', 'TRAEPE_REDIS_PASSWORD', 'TRAEPE_TECHNICAL_PASSWORD', 'TRAEPE_REVERB_APP_KEY', 'TRAEPE_REVERB_APP_SECRET', 'TRAEPE_MINIO_ROOT_PASSWORD', 'TRAEPE_MINIO_APP_PASSWORD')) {
    $secret = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()
    $docker = [regex]::Replace($docker, "(?m)^$name=.*$", "$name=$secret")
}
[IO.File]::WriteAllText($dockerPath, $docker.Replace("`r`n", "`n"), [Text.UTF8Encoding]::new($false))
[IO.File]::WriteAllText($apiPath, $api.Replace("`r`n", "`n"), [Text.UTF8Encoding]::new($false))
Write-Output 'Created ignored local environment files with independent random credentials. Values were not printed.'
