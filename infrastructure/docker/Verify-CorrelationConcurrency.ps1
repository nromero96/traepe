param(
    [string]$BaseUrl = 'http://127.0.0.1:8000',
    [ValidateRange(2, 32)][int]$Count = 16
)

$ErrorActionPreference = 'Stop'
$alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'
$cases = @(1..$Count | ForEach-Object {
    $id = [string]$alphabet[(Get-Random -Minimum 0 -Maximum 8)]
    1..25 | ForEach-Object { $id += $alphabet[(Get-Random -Minimum 0 -Maximum 32)] }
    $id
})
$url = "$BaseUrl/api/v1/health/ready"
$results = @($cases | ForEach-Object -Parallel {
    $id = $_
    $response = Invoke-WebRequest -Uri $using:url -Headers @{ 'X-Correlation-ID' = $id } -TimeoutSec 30
    $body = $response.Content | ConvertFrom-Json
    if ($body.meta.correlation_id -cne $id -or ($response.Headers['X-Correlation-ID'] -join '') -cne $id) {
        throw 'Concurrent response correlation mismatch.'
    }
    $id
} -ThrottleLimit 8)
if ($results.Count -ne $Count) { throw 'Some concurrent requests did not complete.' }
$records = @(Get-Content -LiteralPath apps/api/storage/logs/technical.jsonl | ForEach-Object {
    $record = $_ | ConvertFrom-Json
    if ($record.context.correlation_id -in $cases) { $record }
})
foreach ($id in $cases) {
    $matching = @($records | Where-Object { $_.context.correlation_id -ceq $id -and $_.message -eq 'http.completed' })
    if ($matching.Count -ne 1 -or $matching[0].context.status_code -ne 200) {
        throw 'Concurrent log correlation mismatch.'
    }
}
Write-Output "PASS: $Count concurrent real HTTP requests retain independent response/log correlation IDs."

$canary = 'fake-log-canary-' + [Guid]::NewGuid().ToString('N')
$response = Invoke-WebRequest "$BaseUrl/api/v1/nonexistent?token=$canary" -Headers @{ Authorization = "Bearer $canary"; Cookie = "session=$canary" } -SkipHttpErrorCheck
$id = ($response.Content | ConvertFrom-Json).error.correlation_id
$lines = @(docker logs --tail 500 traepe-nginx-1 2>&1)
$nginxRecords = @($lines | Where-Object { $_.ToString().StartsWith('{') } | ForEach-Object { $_.ToString() | ConvertFrom-Json })
$matching = @($nginxRecords | Where-Object { $_.context.correlation_id -ceq $id })
if (($lines -join "`n").Contains($canary)) { throw 'Nginx query/header/cookie canary leaked.' }
if ($matching.Count -ne 1 -or $matching[0].context.status_code -ne 404) { throw 'Sanitized Nginx failure is not observable.' }
Write-Output 'PASS: Nginx excludes query/header/cookie canaries and retains correlated JSON failure status.'
