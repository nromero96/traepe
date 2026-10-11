<?php

namespace Tests\Feature;

use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogWriter;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogOperation;
use App\Modules\Identity\Infrastructure\IdentityUser;
use Illuminate\Cache\RateLimiter;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LocalDraftCatalogHttpTest extends TestCase
{
    private const URL = '/api/v1/catalog/local-draft-catalogs';

    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn () => 'testing');
        config(['session.driver' => 'array', 'cache.limiter' => 'array']);
        $this->app->forgetInstance(RateLimiter::class);
    }

    private function body(): array
    {
        return ['merchant_public_id' => self::ID, 'catalog' => ['name' => ' Synthetic Catalog Canary '],
            'product' => ['name' => 'Synthetic Product Canary', 'description' => null, 'brand' => 'Synthetic Brand Canary']];
    }

    private function allow(): void
    {
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11, 'public_id' => self::ID, 'status' => 'active']), 'web');
        $this->mock(LocalDraftCatalogAccess::class)->shouldReceive('actor')->andReturn(self::ID);
    }

    private function operation(): DraftCatalogOperation
    {
        return new DraftCatalogOperation(self::ID, self::ID, self::ID, DraftCatalogInput::fromArray($this->body()));
    }

    public function test_session_and_independent_permission_precede_input_and_services(): void
    {
        $this->mock(LocalDraftCatalogAccess::class)->shouldNotReceive('actor');
        $this->mock(LocalDraftCatalogWriter::class)->shouldNotReceive('execute');
        $this->mock(LocalDraftCatalogStore::class)->shouldNotReceive('find');
        $this->postJson(self::URL, [])->assertUnauthorized();
        $this->getJson(self::URL.'/'.self::ID)->assertUnauthorized();
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11]), 'web');
        $this->mock(LocalDraftCatalogAccess::class)->shouldReceive('actor')->withArgs(fn ($id, $capability) => $id === 11 && $capability === LocalDraftCatalogAccess::CREATE)->once()->andReturn(null);
        $this->postJson(self::URL, ['actor' => self::ID])->assertForbidden();
        $this->mock(LocalDraftCatalogAccess::class)->shouldReceive('actor')->withArgs(fn ($id, $capability) => $capability === LocalDraftCatalogAccess::READ)->once()->andReturn(null);
        $this->getJson(self::URL.'/invalid')->assertForbidden();
    }

    public function test_closed_raw_body_and_key_validation_never_reach_writer(): void
    {
        $this->allow();
        $this->mock(LocalDraftCatalogWriter::class)->shouldNotReceive('execute');
        $invalid = ['{', 'null', '[]', '{}', json_encode($this->body() + ['status' => 'active'])];
        foreach ($invalid as $body) {
            $this->call('POST', self::URL, server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => 'key'], content: $body)
                ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
        }
        foreach (['', ' spaced', 'space ', "control\n", str_repeat('k', 256)] as $key) {
            $this->withHeader('Idempotency-Key', $key)->postJson(self::URL, $this->body())->assertUnprocessable();
        }
        $this->getJson(self::URL.'/invalid')->assertUnprocessable();
    }

    public function test_create_read_response_and_conflict_contracts_preserve_exact_names(): void
    {
        $this->allow();
        $operation = $this->operation();
        $this->mock(LocalDraftCatalogWriter::class)->shouldReceive('execute')->once()->andReturn($operation);
        $response = $this->withHeader('Idempotency-Key', 'key')->postJson(self::URL, $this->body())->assertCreated();
        $response->assertJsonPath('data.type', 'local_draft_catalog_operation')->assertJsonPath('data.attributes.catalog.name', $this->body()['catalog']['name']);
        $this->assertSame($operation->snapshot(), $response->json('data.attributes'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->mock(LocalDraftCatalogStore::class)->shouldReceive('find')->with(self::ID, self::ID)->once()->andReturn($operation);
        $this->getJson(self::URL.'/'.self::ID)->assertOk()->assertJsonPath('data', $response->json('data'));
        foreach (['idempotency_mismatch', 'idempotency_expired'] as $code) {
            $this->mock(LocalDraftCatalogWriter::class)->shouldReceive('execute')->once()->andThrow(new DraftCatalogFailure($code));
            $this->postJson(self::URL, $this->body())->assertConflict()->assertJsonPath('error.code', $code);
        }
    }

    public function test_rate_budgets_are_separate_for_read_create_and_actor(): void
    {
        $this->allow();
        $this->mock(LocalDraftCatalogWriter::class)->shouldReceive('execute')->times(30)->andReturn($this->operation());
        $this->withHeader('Idempotency-Key', 'key');
        for ($i = 0; $i < 30; $i++) {
            $this->postJson(self::URL, $this->body())->assertCreated();
        }
        $this->postJson(self::URL, $this->body())->assertStatus(429)->assertHeader('Retry-After');
        $this->mock(LocalDraftCatalogStore::class)->shouldReceive('find')->once()->andReturn($this->operation());
        $this->getJson(self::URL.'/'.self::ID)->assertOk();
        $this->actingAs((new IdentityUser)->forceFill(['id' => 12]), 'web');
        $this->mock(LocalDraftCatalogWriter::class)->shouldReceive('execute')->once()->andReturn($this->operation());
        $this->postJson(self::URL, $this->body())->assertCreated();
    }

    public function test_unexpected_errors_and_logs_never_include_private_input(): void
    {
        $this->allow();
        $path = sys_get_temp_dir().'/traepe_catalog_logs_'.bin2hex(random_bytes(8)).'.jsonl';
        config(['logging.default' => 'safe', 'logging.channels.safe.path' => $path, 'app.debug' => true]);
        Log::forgetChannel('safe');
        $this->mock(LocalDraftCatalogWriter::class)->shouldReceive('execute')->once()->andThrow(new RuntimeException('private-catalog-sql-canary'));
        try {
            $response = $this->withHeader('Idempotency-Key', 'private-catalog-key')->postJson(self::URL, $this->body())->assertStatus(500)->assertJsonPath('error.code', 'internal_error');
            foreach (['private-catalog-key', 'private-catalog-sql-canary', 'Synthetic Catalog Canary', 'Synthetic Product Canary', self::URL] as $canary) {
                $this->assertStringNotContainsString($canary, $response->getContent());
                $this->assertStringNotContainsString($canary, file_get_contents($path));
            }
        } finally {
            Log::forgetChannel('safe');
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_environment_gate_precedes_sessions_and_ports(): void
    {
        $this->mock(StartSession::class)->shouldNotReceive('handle');
        $this->mock(LocalDraftCatalogAccess::class)->shouldNotReceive('actor');
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            $this->postJson(self::URL, [])->assertNotFound();
            $this->getJson(self::URL.'/'.self::ID)->assertNotFound();
        }
    }

    public function test_fresh_local_route_caches_are_denied_outside_local_before_session_or_ports(): void
    {
        $directory = sys_get_temp_dir().'/traepe_catalog_routes_'.bin2hex(random_bytes(8));
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
                    $worker = new Process([PHP_BINARY, base_path('tests/Support/catalog-environment-worker.php')], base_path(), array_replace($environment, ['APP_ENV' => $name]));
                    $this->assertSame(0, $worker->setTimeout(30)->run());
                    $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                    $this->assertSame([false, false, false, false], $result['bindings']);
                    $this->assertSame([false, false, false, false, false, false], $result['touched']);
                    $this->assertSame([404, 404], $result['statuses']);
                    $this->assertSame($cached, $result['registered']);
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
