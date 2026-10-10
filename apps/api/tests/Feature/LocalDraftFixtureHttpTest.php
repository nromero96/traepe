<?php

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\IdentityUser;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageAccess;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedZoneSource;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureAccess;
use App\Modules\Marketplace\Application\Fixtures\LocalFixtureWriter;
use App\Modules\Marketplace\Domain\Fixtures\FixtureFailure;
use App\Modules\Marketplace\Domain\Fixtures\FixtureOperation;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
use Illuminate\Cache\RateLimiter;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LocalDraftFixtureHttpTest extends TestCase
{
    private const URL = '/api/v1/marketplace/local-draft-fixtures';

    private const ACTOR = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn () => 'testing');
        config(['session.driver' => 'array', 'cache.limiter' => 'array']);
        $this->app->forgetInstance(RateLimiter::class);
    }

    private function allow(): void
    {
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11, 'public_id' => self::ACTOR, 'status' => 'active']), 'web');
        $this->mock(LocalDraftFixtureAccess::class)->shouldReceive('actor')->andReturn(self::ACTOR);
    }

    private function operation(): FixtureOperation
    {
        return new FixtureOperation(self::ACTOR, FixtureProfile::A, self::ACTOR, self::ACTOR, [self::ACTOR, '01ARZ3NDEKTSV4RRFFQ69G5FB0', '01ARZ3NDEKTSV4RRFFQ69G5FB1']);
    }

    public function test_cookie_session_and_permission_precede_validation_and_claims(): void
    {
        $this->mock(LocalFixtureWriter::class)->shouldNotReceive('execute');
        $this->mock(LocalDraftFixtureAccess::class)->shouldNotReceive('actor');
        foreach (['Bearer private-auth', 'Basic '.base64_encode('technical:private-auth')] as $header) {
            $this->withHeader('Authorization', $header)->postJson(self::URL, [])->assertUnauthorized();
        }
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11]), 'web');
        $this->mock(LocalDraftFixtureAccess::class)->shouldReceive('actor')->once()->andReturn(null);
        $this->postJson(self::URL, ['actor' => self::ACTOR, 'scope' => '*', 'now' => '1999'])->assertForbidden();
    }

    #[DataProvider('invalidRequests')]
    public function test_closed_raw_json_and_unmodified_key_are_required(string $body, ?string $key): void
    {
        $this->allow();
        $this->mock(LocalFixtureWriter::class)->shouldNotReceive('execute');
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }
        $response = $this->call('POST', self::URL, server: $this->transformHeadersToServerVars($headers), content: $body)->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
        $this->assertSame([], $response->json('error.details'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($response->headers->get('X-Correlation-ID'), $response->json('error.correlation_id'));
    }

    public static function invalidRequests(): array
    {
        $valid = '{"fixture_profile":"synthetic-origin-a-v1"}';

        return [
            ['{', 'key'], ['[]', 'key'], ['null', 'key'], ['{}', 'key'],
            ['{"fixture_profile":null}', 'key'], ['{"fixture_profile":1}', 'key'],
            ['{"fixture_profile":"synthetic-origin-a-v1 "}', 'key'],
            ['{"fixture_profile":"other"}', 'key'],
            ['{"fixture_profile":"synthetic-origin-a-v1","actor_id":"private-canary"}', 'key'],
            [$valid, null], [$valid, ''], [$valid, ' key'], [$valid, 'key '],
            [$valid, "key\tvalue"], [$valid, "key\nvalue"], [$valid, 'café'], [$valid, str_repeat('x', 256)],
        ];
    }

    public function test_media_type_and_success_contract_and_conflicts(): void
    {
        $this->allow();
        $this->call('POST', self::URL, server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'text/plain'], content: '{}')->assertStatus(415)->assertJsonPath('error.code', 'unsupported_media_type');
        $operation = $this->operation();
        $this->mock(LocalFixtureWriter::class)->shouldReceive('execute')->once()->withArgs(function ($profile, $actor, $key, $correlation, $authorize): bool {
            $authorize();

            return $profile === FixtureProfile::A && $actor === self::ACTOR && strlen($key) === 255 && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $correlation) === 1;
        })->andReturn($operation);
        $response = $this->withHeader('Idempotency-Key', str_repeat('x', 255))->postJson(self::URL, ['fixture_profile' => FixtureProfile::A->value])->assertCreated()->assertJsonPath('data', ['type' => 'local_draft_fixture_operation', 'id' => $operation->publicId, 'attributes' => $operation->snapshot()]);
        $this->assertSame($response->headers->get('X-Correlation-ID'), $response->json('meta.correlation_id'));
        foreach (['idempotency_mismatch', 'idempotency_expired', 'fixture_country_conflict', 'forbidden'] as $code) {
            $this->mock(LocalFixtureWriter::class)->shouldReceive('execute')->once()->andThrow(new FixtureFailure($code));
            $this->withHeader('Idempotency-Key', 'key')->postJson(self::URL, ['fixture_profile' => FixtureProfile::A->value])->assertStatus($code === 'forbidden' ? 403 : 409)->assertJsonPath('error.code', $code);
        }
    }

    public function test_limiter_is_independent_and_per_actor(): void
    {
        $this->allow();
        for ($i = 0; $i < 30; $i++) {
            $this->postJson(self::URL, [])->assertUnprocessable();
        }
        $this->postJson(self::URL, [])->assertStatus(429)->assertHeader('Retry-After');
        $this->actingAs((new IdentityUser)->forceFill(['id' => 12]), 'web');
        $this->postJson(self::URL, [])->assertUnprocessable();
        $this->mock(LocalPersistedCoverageAccess::class)->shouldReceive('allows')->once()->andReturn(true);
        $this->mock(LocalPersistedZoneSource::class)->shouldReceive('matches')->once()->andReturn([]);
        $this->getJson('/api/v1/marketplace/local-persisted-coverage-probe?market_public_id='.self::ACTOR.'&longitude=0&latitude=0')->assertOk();
        $this->postJson('/api/v1/auth/otp/request', [])->assertUnprocessable();
    }

    public function test_errors_and_logs_exclude_key_body_and_exception_details(): void
    {
        $this->allow();
        $path = sys_get_temp_dir().'/traepe-fixture-logs-'.bin2hex(random_bytes(8)).'.jsonl';
        config(['logging.default' => 'safe', 'logging.channels.safe.path' => $path, 'app.debug' => true]);
        Log::forgetChannel('safe');
        $this->mock(LocalFixtureWriter::class)->shouldReceive('execute')->once()->andThrow(new RuntimeException('private-sql-canary'));
        try {
            $response = $this->withHeader('Idempotency-Key', 'private-key-canary')->postJson(self::URL, ['fixture_profile' => FixtureProfile::A->value])->assertStatus(500)->assertJsonPath('error.code', 'internal_error');
            $contents = file_get_contents($path);
            foreach (['private-key-canary', 'private-sql-canary', 'fixture_profile', FixtureProfile::A->value, self::URL] as $canary) {
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

    public function test_environment_gate_precedes_session_and_dependencies(): void
    {
        $this->mock(StartSession::class)->shouldNotReceive('handle');
        $this->mock(LocalDraftFixtureAccess::class)->shouldNotReceive('actor');
        $this->mock(LocalFixtureWriter::class)->shouldNotReceive('execute');
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            $this->postJson(self::URL, [])->assertNotFound();
        }
    }

    public function test_fresh_cached_and_uncached_environments_never_touch_ports_or_session_or_csrf(): void
    {
        $directory = sys_get_temp_dir().'/traepe_fixture_routes_'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($directory, 0700));
        $cache = $directory.'/routes.php';
        $environment = ['APP_ENV' => 'local', 'APP_ROUTES_CACHE' => $cache, 'APP_CONFIG_CACHE' => $directory.'/config.php', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        try {
            foreach (['production', 'staging'] as $name) {
                $build = new Process([PHP_BINARY, 'artisan', 'route:cache', '--no-interaction'], base_path(), $environment);
                $this->assertSame(0, $build->setTimeout(30)->run());
                foreach ([true, false] as $cached) {
                    if (! $cached) {
                        unlink($cache);
                    }
                    $process = new Process([PHP_BINARY, base_path('tests/Support/fixture-environment-worker.php')], base_path(), array_replace($environment, ['APP_ENV' => $name]));
                    $this->assertSame(0, $process->setTimeout(30)->run());
                    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                    $this->assertSame($name, $result['environment']);
                    $this->assertSame($cached, $result['registered']);
                    $this->assertSame([false, false, false], $result['bindings']);
                    $this->assertSame([false, false, false, false, false], $result['touched']);
                    $this->assertSame([404, 404], $result['statuses']);
                }
            }
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
