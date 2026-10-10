<?php

namespace Tests\Feature;

use App\Modules\Marketplace\Application\Coverage\LocalZoneSource;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\ZoneCandidate;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LocalCoverageHttpTest extends TestCase
{
    private const URL = '/api/v1/marketplace/local-coverage-probe';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.limiter' => 'array']);
        $this->app->forgetInstance(RateLimiter::class);
    }

    public function test_public_read_validates_explicit_axes_and_limits_without_a_session(): void
    {
        $source = $this->mock(LocalZoneSource::class);
        foreach ([[-180, -90], [180, 90], [0, 0], [6.5, 2.5]] as [$longitude, $latitude]) {
            $source->shouldReceive('matches')->once()->withArgs(fn (GeographicPoint $point) => $point->longitude === (float) $longitude && $point->latitude === (float) $latitude)->andReturn([]);
            $response = $this->getJson(self::URL.'?'.http_build_query(compact('longitude', 'latitude')))->assertOk();
            $this->assertSame(['data', 'meta'], array_keys($response->json()));
            $response->assertJsonPath('data.type', 'local_coverage_probe')->assertJsonPath('data.id', 'local-coverage-v1');
            $response->assertJsonPath('data.attributes', ['status' => 'outside', 'zone_id' => null]);
            $this->assertTrue(Str::isUlid($response->json('meta.correlation_id')));
            $response->assertHeader('X-Correlation-ID', $response->json('meta.correlation_id'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertFalse($response->headers->has('Set-Cookie'));
        }
    }

    public static function invalidQueries(): array
    {
        return [
            [[], 'longitude'],
            [['longitude' => '0'], 'latitude'],
            [['longitude' => 'fake-coordinate-canary', 'latitude' => '0'], 'longitude'],
            [['longitude' => '0', 'latitude' => 'NaN'], 'latitude'],
            [['longitude' => 'INF', 'latitude' => '0'], 'longitude'],
            [['longitude' => '1e999', 'latitude' => '0'], 'longitude'],
            [['longitude' => '-180.0001', 'latitude' => '0'], 'longitude'],
            [['longitude' => '0', 'latitude' => '90.0001'], 'latitude'],
            [['longitude' => ['0'], 'latitude' => '0'], 'longitude'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_is_rejected_before_the_port_and_does_not_echo_values(array $query, string $field): void
    {
        $this->mock(LocalZoneSource::class)->shouldNotReceive('matches');
        $response = $this->getJson(self::URL.'?'.http_build_query($query))->assertUnprocessable();
        $response->assertJsonPath('error.code', 'validation_failed')->assertJsonPath('error.details.0.field', $field);
        $this->assertStringNotContainsString('fake-coordinate-canary', $response->getContent());
        $response->assertHeader('X-Correlation-ID', $response->json('error.correlation_id'));
    }

    public function test_non_local_routes_deny_before_validation_and_service_resolution(): void
    {
        unset($this->app[LocalZoneSource::class]);
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            foreach (['', '?longitude=0.5&latitude=0.5', '?longitude=private-canary&latitude=0'] as $query) {
                $this->getJson(self::URL.$query)->assertNotFound()->assertJsonPath('error.code', 'not_found');
            }
        }
    }

    public function test_other_methods_are_rejected_without_evaluating_coverage(): void
    {
        $this->mock(LocalZoneSource::class)->shouldNotReceive('matches');
        $this->postJson(self::URL, ['longitude' => 0.5, 'latitude' => 0.5])
            ->assertStatus(405)->assertJsonPath('error.code', 'method_not_allowed')->assertHeader('Allow', 'GET, HEAD');
        $this->getJson('/api/v1/markets/resolve?longitude=0.5&latitude=0.5')->assertNotFound();
    }

    public function test_rate_limit_rejects_before_the_source_and_preserves_retry_after(): void
    {
        $this->mock(LocalZoneSource::class)->shouldReceive('matches')->times(30)->andReturn([]);
        for ($i = 0; $i < 30; $i++) {
            $this->getJson(self::URL.'?longitude=0.5&latitude=0.5')->assertOk();
        }
        $this->getJson(self::URL.'?longitude=0.5&latitude=0.5')->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limited')->assertHeader('Retry-After');
        // A public diagnostic must not consume the existing Identity OTP budget.
        // Explicit testing enables Laravel's usual CSRF bypass only in this test.
        $this->app->detectEnvironment(fn () => 'testing');
        $this->postJson('/api/v1/auth/otp/request', [])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_response_and_logs_never_include_points_queries_or_internal_errors(): void
    {
        $path = sys_get_temp_dir().'/traepe-coverage-logs-'.bin2hex(random_bytes(8)).'.jsonl';
        config(['logging.default' => 'safe', 'logging.channels.safe.path' => $path, 'app.debug' => true]);
        Log::forgetChannel('safe');
        $source = $this->mock(LocalZoneSource::class);
        $source->shouldReceive('matches')->once()->andReturn([new ZoneCandidate('01ARZ3NDEKTSV4RRFFQ69G5FAZ', 10)]);
        $source->shouldReceive('matches')->once()->andThrow(new RuntimeException('private-postgis-coordinate-debug'));
        try {
            $success = $this->getJson(self::URL.'?longitude=3.1415926535&latitude=1.234567891')->assertOk();
            $failure = $this->getJson(self::URL.'?longitude=0.5&latitude=0.5')->assertStatus(500)->assertJsonPath('error.code', 'internal_error');
            $this->assertFileExists($path);
            $contents = file_get_contents($path);
            foreach (['3.1415926535', '1.234567891', 'longitude', 'latitude', 'private-postgis-coordinate-debug', self::URL] as $canary) {
                $this->assertStringNotContainsString($canary, $contents);
                $this->assertStringNotContainsString($canary, $success->getContent());
                $this->assertStringNotContainsString($canary, $failure->getContent());
            }
            foreach (array_filter(explode("\n", $contents)) as $line) {
                $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $this->assertContains($record['message'], ['http.completed', 'http.failed']);
                $this->assertSame([], array_diff(array_keys($record['context']), ['correlation_id', 'status_code', 'duration_ms', 'exception_type']));
                $this->assertSame([], $record['extra']);
            }
        } finally {
            Log::forgetChannel('safe');
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_fresh_processes_deny_a_real_local_route_cache_outside_local(): void
    {
        $directory = sys_get_temp_dir().'/traepe_coverage_routes_'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($directory, 0700));
        $cache = $directory.'/routes.php';
        $environment = ['APP_ENV' => 'local', 'APP_ROUTES_CACHE' => $cache, 'APP_CONFIG_CACHE' => $directory.'/config.php', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        try {
            $build = new Process([PHP_BINARY, 'artisan', 'route:cache', '--no-interaction'], base_path(), $environment);
            $this->assertSame(0, $build->setTimeout(30)->run());
            $this->assertFileExists($cache);
            foreach (['production', 'staging'] as $name) {
                $result = $this->probe($environment, $name);
                $this->assertSame($name, $result['environment']);
                $this->assertTrue($result['cached']);
                $this->assertTrue($result['registered']);
                $this->assertFalse($result['source_bound']);
                $this->assertSame([404, 404], $result['statuses']);
                $this->assertSame(['not_found', 'not_found'], $result['errors']);
            }
            $this->assertTrue(unlink($cache));
            $fresh = $this->probe($environment, 'production');
            $this->assertFalse($fresh['cached']);
            $this->assertFalse($fresh['registered']);
            $this->assertSame([404, 404], $fresh['statuses']);
        } finally {
            if (is_file($cache)) {
                unlink($cache);
            }
            rmdir($directory);
        }
    }

    private function probe(array $environment, string $name): array
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/coverage-environment-worker.php')], base_path(), array_replace($environment, ['APP_ENV' => $name]));
        $this->assertSame(0, $process->setTimeout(30)->run());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
