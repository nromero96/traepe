param(
    [string]$BaseUrl = 'http://127.0.0.1:8000',
    [string[]]$Services = @('postgres', 'redis', 'minio', 'horizon', 'reverb', 'mailpit')
)

$ErrorActionPreference = 'Stop'
$dependencyCases = @(
    @{ Service = 'postgres'; Check = 'database'; Status = 'unavailable' },
    @{ Service = 'redis'; Check = 'cache'; Status = 'unavailable' },
    @{ Service = 'minio'; Check = 'storage'; Status = 'degraded' },
    @{ Service = 'horizon'; Check = 'queue'; Status = 'degraded' },
    @{ Service = 'reverb'; Check = 'realtime'; Status = 'degraded' },
    @{ Service = 'mailpit'; Check = 'mail'; Status = 'degraded' }
)

foreach ($dependencyCase in $dependencyCases) {
    if ($dependencyCase.Service -notin $Services) { continue }
    try {
        docker compose --env-file .env.docker stop $dependencyCase.Service
        if ($LASTEXITCODE -ne 0) { throw 'Cannot stop dependency for controlled probe.' }
        # Horizon's upstream repository considers heartbeats fresh for 14 seconds.
        $detectionDeadline = [DateTime]::UtcNow.AddSeconds(20)
        do {
            $readiness = Invoke-WebRequest "$BaseUrl/api/v1/health/ready" -SkipHttpErrorCheck -TimeoutSec 20
            $payload = $readiness.Content | ConvertFrom-Json
            if ($payload.data.attributes.checks.($dependencyCase.Check) -eq 'down') { break }
            Start-Sleep -Seconds 1
        } while ([DateTime]::UtcNow -lt $detectionDeadline)
        if ($readiness.StatusCode -ne 503 -or $payload.data.attributes.status -ne $dependencyCase.Status -or $payload.data.attributes.checks.($dependencyCase.Check) -ne 'down') {
            throw "Readiness failed to detect $($dependencyCase.Check)."
        }
        if ((Invoke-WebRequest "$BaseUrl/up" -TimeoutSec 10).StatusCode -ne 200) {
            throw 'Liveness depends on external dependency.'
        }
        Write-Output "PASS: $($dependencyCase.Check) failure detected; liveness remains available."
        if ($dependencyCase.Service -eq 'redis') {
            docker compose --env-file .env.docker exec -T api php tests/Support/verify-infrastructure.php queue-unavailable
            if ($LASTEXITCODE -ne 0) { throw 'Queue producer did not detect Redis failure.' }
        }
    } finally {
        docker compose --env-file .env.docker up -d --wait $dependencyCase.Service
        if ($LASTEXITCODE -ne 0) { throw 'Dependency recovery failed.' }
    }
}
docker compose --env-file .env.docker up -d --wait
if ($LASTEXITCODE -ne 0) { throw 'Stack recovery failed.' }
$recovered = Invoke-WebRequest "$BaseUrl/api/v1/health/ready" -TimeoutSec 20
if ($recovered.StatusCode -ne 200) { throw 'Readiness did not recover.' }
Write-Output 'PASS: complete readiness recovered after controlled failures.'
