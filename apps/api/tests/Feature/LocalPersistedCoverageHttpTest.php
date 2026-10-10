<?php

namespace Tests\Feature;

use App\Modules\Identity\Application\Authorization\AuthenticatedActorDirectory;
use App\Modules\Identity\Infrastructure\IdentityUser;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageAccess;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedZoneSource;
use App\Modules\Marketplace\Application\Coverage\LocalZoneSource;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use App\Modules\Marketplace\Domain\Coverage\ZoneCandidate;
use DateTimeImmutable;
use Illuminate\Cache\RateLimiter;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LocalPersistedCoverageHttpTest extends TestCase
{
    private const URL = '/api/v1/marketplace/local-persisted-coverage-probe';

    private const MARKET = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'array', 'cache.limiter' => 'array']);
        $this->app->forgetInstance(RateLimiter::class);
    }

    private function login(): void
    {
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11, 'public_id' => self::MARKET, 'status' => 'active']), 'web');
    }

    private function allow(): void
    {
        $this->login();
        $this->mock(LocalPersistedCoverageAccess::class)->shouldReceive('allows')->withArgs(fn (int $id, DateTimeImmutable $now): bool => $id === 11 && $now->getTimezone()->getName() === 'UTC')->andReturn(true);
    }

    private function url(array $query = []): string
    {
        return self::URL.'?'.http_build_query(array_replace(['market_public_id' => self::MARKET, 'longitude' => '0.5', 'latitude' => '0.5'], $query));
    }

    public function test_session_is_required_even_with_authorization_headers(): void
    {
        $this->mock(LocalPersistedCoverageAccess::class)->shouldNotReceive('allows');
        $this->mock(LocalPersistedZoneSource::class)->shouldNotReceive('matches');
        foreach (['', 'Bearer private-header-canary', 'Basic '.base64_encode('technical:private-header-canary')] as $header) {
            $response = $this->withHeader('Authorization', $header)->getJson(self::URL)->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringNotContainsString('private-header-canary', $response->getContent());
        }
    }

    public function test_permission_is_checked_before_input_and_coverage_resolution(): void
    {
        $this->login();
        $this->mock(LocalPersistedCoverageAccess::class)->shouldReceive('allows')->once()->andReturn(false);
        unset($this->app[LocalPersistedZoneSource::class]);
        $this->getJson(self::URL.'?actor_id=other&scope_type=platform&capability=*&longitude=invalid')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
    }

    public function test_all_technical_results_have_closed_correlated_responses(): void
    {
        $this->allow();
        $source = $this->mock(LocalPersistedZoneSource::class);
        $a = new ZoneCandidate(LocalPersistedCoverageAccess::SCOPE_PUBLIC_ID, 10);
        $b = new ZoneCandidate(LocalPersistedCoverageAccess::RESOURCE_PUBLIC_ID, 10);
        foreach ([[null, 'market_not_found', null], [[], 'outside', null], [[$a], 'selected', $a->publicId], [[$a, $b], 'ambiguous', null]] as [$candidates, $status, $zone]) {
            $source->shouldReceive('matches')->once()->withArgs(fn (MarketPublicId $market, GeographicPoint $point): bool => $market->value === self::MARKET && $point->longitude === 0.5 && $point->latitude === 0.5)->andReturn($candidates);
            $response = $this->getJson($this->url())->assertOk();
            $this->assertSame(['data', 'meta'], array_keys($response->json()));
            $this->assertSame(['type', 'id', 'attributes'], array_keys($response->json('data')));
            $response->assertJsonPath('data.type', 'local_persisted_coverage_probe')->assertJsonPath('data.id', 'local-persisted-coverage-v1');
            $response->assertJsonPath('data.attributes', ['status' => $status, 'zone_id' => $zone]);
            $response->assertHeader('X-Correlation-ID', $response->json('meta.correlation_id'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public static function invalidQueries(): array
    {
        return [
            [['market_public_id' => null], 'market_public_id'],
            [['market_public_id' => 'private-market-canary'], 'market_public_id'],
            [['market_public_id' => '81ARZ3NDEKTSV4RRFFQ69G5FAZ'], 'market_public_id'],
            [['market_public_id' => ['01ARZ3NDEKTSV4RRFFQ69G5FAZ']], 'market_public_id'],
            [['longitude' => null], 'longitude'],
            [['latitude' => null], 'latitude'],
            [['longitude' => 'NaN'], 'longitude'],
            [['longitude' => 'INF'], 'longitude'],
            [['latitude' => '1e999'], 'latitude'],
            [['longitude' => '180.00001'], 'longitude'],
            [['latitude' => '-90.00001'], 'latitude'],
            [['longitude' => ['0']], 'longitude'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_input_never_reaches_coverage(array $query, string $field): void
    {
        $this->allow();
        $this->mock(LocalPersistedZoneSource::class)->shouldNotReceive('matches');
        $response = $this->getJson($this->url($query))->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed')->assertJsonPath('error.details.0.field', $field);
        $this->assertStringNotContainsString('private-market-canary', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_extreme_valid_points_reach_the_source_without_normalization(): void
    {
        $this->allow();
        $source = $this->mock(LocalPersistedZoneSource::class);
        foreach ([[-180, -90], [180, 90]] as [$longitude, $latitude]) {
            $source->shouldReceive('matches')->once()->withArgs(fn (MarketPublicId $market, GeographicPoint $point): bool => $market->value === self::MARKET && $point->longitude === (float) $longitude && $point->latitude === (float) $latitude)->andReturn([]);
            $this->getJson($this->url(compact('longitude', 'latitude')))->assertOk();
        }
    }

    public function test_environment_guard_precedes_session_authentication_and_unbound_ports(): void
    {
        unset($this->app[LocalPersistedCoverageAccess::class], $this->app[LocalPersistedZoneSource::class]);
        // Kernel termination constructs middleware even after a 404; no session may start.
        $this->mock(StartSession::class)->shouldNotReceive('handle');
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            $this->getJson(self::URL)->assertNotFound();
            $this->getJson($this->url(['longitude' => 'invalid']))->assertNotFound();
        }
    }

    public function test_limiter_has_its_own_budget_per_authenticated_actor(): void
    {
        $this->allow();
        $this->mock(LocalPersistedZoneSource::class)->shouldReceive('matches')->times(31)->andReturn([]);
        for ($i = 0; $i < 30; $i++) {
            $this->getJson($this->url())->assertOk();
        }
        $this->getJson($this->url())->assertStatus(429)->assertJsonPath('error.code', 'rate_limited')->assertHeader('Retry-After');
        $this->actingAs((new IdentityUser)->forceFill(['id' => 12, 'public_id' => LocalPersistedCoverageAccess::SCOPE_PUBLIC_ID]), 'web');
        $this->mock(LocalPersistedCoverageAccess::class)->shouldReceive('allows')->withArgs(fn (int $id, DateTimeImmutable $now): bool => $id === 12)->once()->andReturn(true);
        $this->getJson($this->url())->assertOk();
        $this->mock(LocalZoneSource::class)->shouldReceive('matches')->once()->andReturn([]);
        $this->getJson('/api/v1/marketplace/local-coverage-probe?longitude=10&latitude=10')->assertOk();
        $this->mock(AuthenticatedActorDirectory::class)->shouldReceive('publicId')->once()->andReturn(null);
        $this->getJson('/api/v1/identity/local-authorization-probe')->assertForbidden();
        $this->app->detectEnvironment(fn () => 'testing');
        $this->postJson('/api/v1/auth/otp/request', [])->assertUnprocessable();
    }

    public function test_failures_and_logs_exclude_private_input_and_headers(): void
    {
        $this->allow();
        $path = sys_get_temp_dir().'/traepe-persisted-logs-'.bin2hex(random_bytes(8)).'.jsonl';
        config(['logging.default' => 'safe', 'logging.channels.safe.path' => $path, 'app.debug' => true]);
        Log::forgetChannel('safe');
        $this->mock(LocalPersistedZoneSource::class)->shouldReceive('matches')->once()->andThrow(new RuntimeException('private-sql-coordinate-canary'));
        try {
            $response = $this->withHeader('Authorization', 'Bearer private-header-canary')->getJson($this->url(['longitude' => '3.1415926535', 'latitude' => '1.234567891']))->assertStatus(500)->assertJsonPath('error.code', 'internal_error');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $contents = file_get_contents($path);
            foreach (['private-sql-coordinate-canary', 'private-header-canary', '3.1415926535', '1.234567891', 'market_public_id', 'longitude', self::URL] as $canary) {
                $this->assertStringNotContainsString($canary, $contents);
                $this->assertStringNotContainsString($canary, $response->getContent());
            }
        } finally {
            Log::forgetChannel('safe');
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_real_route_cache_remains_closed_in_fresh_non_local_processes(): void
    {
        $directory = sys_get_temp_dir().'/traepe_persisted_routes_'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($directory, 0700));
        $cache = $directory.'/routes.php';
        $environment = ['APP_ENV' => 'local', 'APP_ROUTES_CACHE' => $cache, 'APP_CONFIG_CACHE' => $directory.'/config.php', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        try {
            $build = new Process([PHP_BINARY, 'artisan', 'route:cache', '--no-interaction'], base_path(), $environment);
            $this->assertSame(0, $build->setTimeout(30)->run());
            foreach (['production', 'staging'] as $name) {
                foreach ([true, false] as $cached) {
                    if (! $cached && is_file($cache)) {
                        $this->assertTrue(unlink($cache));
                    }
                    $process = new Process([PHP_BINARY, base_path('tests/Support/persisted-coverage-environment-worker.php')], base_path(), array_replace($environment, ['APP_ENV' => $name]));
                    $this->assertSame(0, $process->setTimeout(30)->run());
                    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                    $this->assertSame($name, $result['environment']);
                    $this->assertSame($cached, $result['registered']);
                    $this->assertFalse($result['access_bound']);
                    $this->assertFalse($result['source_bound']);
                    $this->assertSame([404, 404, 404], $result['statuses']);
                    $this->assertSame([false, false, false], $result['touched']);
                    if (! $cached) {
                        $this->assertSame(0, $build->run());
                    }
                }
            }
        } finally {
            if (is_file($cache)) {
                unlink($cache);
            }
            rmdir($directory);
        }
    }
}
