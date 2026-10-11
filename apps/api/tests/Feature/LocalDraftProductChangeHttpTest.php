<?php

namespace Tests\Feature;

use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeWriter;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeOperation;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductProfile;
use App\Modules\Identity\Infrastructure\IdentityUser;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LocalDraftProductChangeHttpTest extends TestCase
{
    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private const URL = '/api/v1/catalog/local-draft-profiles/'.self::ID.'/products/'.self::ID;

    private const HISTORY = '/api/v1/catalog/local-draft-product-change-operations/'.self::ID;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn () => 'testing');
        config(['session.driver' => 'array', 'cache.limiter' => 'array']);
        $this->app->forgetInstance(RateLimiter::class);
    }

    private function allow(): void
    {
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11, 'public_id' => self::ID, 'status' => 'active']), 'web');
        $this->mock(LocalDraftProductChangeAccess::class)->shouldReceive('actor')->andReturn(self::ID);
        $this->withHeaders(['Idempotency-Key' => 'synthetic-change-key', 'If-Match' => '"catalog:'.self::ID.':v1"']);
    }

    private function body(): array
    {
        return ['product' => ['name' => ' Synthetic Change Canary ', 'description' => '', 'brand' => null]];
    }

    private function operation(): DraftProductChangeOperation
    {
        return new DraftProductChangeOperation(self::ID, new DraftCatalogProfile(self::ID, self::ID, 'Synthetic', 1),
            new DraftProductProfile(self::ID, self::ID, new DraftProductData('Original', null, null)),
            new DraftProductProfile(self::ID, self::ID, new DraftProductData(' Synthetic Change Canary ', '', null), 2));
    }

    public function test_session_and_independent_permissions_precede_input_and_storage(): void
    {
        $this->mock(LocalDraftProductChangeStore::class)->shouldNotReceive('change', 'find');
        $this->mock(LocalDraftProductChangeWriter::class)->shouldNotReceive('execute');
        $this->putJson(self::URL, [])->assertUnauthorized();
        $this->getJson(self::HISTORY)->assertUnauthorized();
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11]), 'web');
        $this->mock(LocalDraftProductChangeAccess::class)->shouldReceive('actor')->andReturn(null);
        $this->putJson(self::URL.'?bad=1', [])->assertForbidden();
        $this->getJson(self::HISTORY.'?bad=1')->assertForbidden();
    }

    public function test_missing_and_invalid_etags_closed_body_and_queries_reject_before_writer(): void
    {
        $this->allow();
        $this->mock(LocalDraftProductChangeWriter::class)->shouldNotReceive('execute');
        $this->withoutHeader('If-Match')->putJson(self::URL, $this->body())->assertStatus(428)->assertJsonPath('error.code', 'precondition_required');
        foreach (['*', 'W/"catalog:'.self::ID.':v1"', '"catalog:'.self::ID.':v01"', '"catalog:'.self::ID.':v2147483648"'] as $etag) {
            $this->withHeader('If-Match', $etag)->putJson(self::URL, $this->body())->assertUnprocessable();
        }
        $this->withHeader('If-Match', '"catalog:'.self::ID.':v1"');
        foreach ([[], ['product' => []], $this->body() + ['actor' => self::ID], ['product' => $this->body()['product'] + ['version' => 1]]] as $body) {
            $this->putJson(self::URL, $body)->assertUnprocessable();
        }
        foreach (['', 'bad key', str_repeat('x', 256)] as $key) {
            $this->withHeader('Idempotency-Key', $key)->putJson(self::URL, $this->body())->assertUnprocessable();
        }
        $this->withHeader('Idempotency-Key', 'synthetic-change-key')->putJson(self::URL.'?limit=1', $this->body())->assertUnprocessable();
        $this->getJson(self::HISTORY.'?limit=1')->assertUnprocessable();
        $this->getJson(str_replace(self::ID, 'invalid', self::HISTORY))->assertUnprocessable();
        $this->withHeader('Content-Type', 'text/plain')->put(self::URL, $this->body())->assertStatus(415);
    }

    public function test_success_returns_original_closed_operation_with_privacy_and_current_correlation(): void
    {
        $this->allow();
        $this->mock(LocalDraftProductChangeWriter::class)->shouldReceive('execute')->once()->andReturn($this->operation());
        $result = $this->putJson(self::URL, $this->body())->assertOk()->assertJsonPath('data.type', 'local_draft_product_change_operation')
            ->assertJsonPath('data.attributes.product.before.name', 'Original')->assertJsonPath('data.attributes.product.after.version', 2);
        $this->assertSame($this->operation()->snapshot(), $result->json('data.attributes'));
        $this->assertFalse($result->headers->has('ETag'));
        $this->assertStringContainsString('no-store', $result->headers->get('Cache-Control'));
        $this->mock(LocalDraftProductChangeStore::class)->shouldReceive('find')->with(self::ID, self::ID)->once()->andReturn($this->operation());
        $this->getJson(self::HISTORY)->assertOk()->assertJsonPath('data', $result->json('data'));
    }

    public function test_expected_and_unexpected_failures_are_sanitized(): void
    {
        $this->allow();
        foreach (['no_changes' => 409, 'version_conflict' => 409, 'idempotency_mismatch' => 409, 'idempotency_expired' => 409, 'not_found' => 404, 'forbidden' => 403] as $code => $status) {
            $this->mock(LocalDraftProductChangeWriter::class)->shouldReceive('execute')->once()->andThrow(new DraftCatalogFailure($code));
            $this->putJson(self::URL, $this->body())->assertStatus($status)->assertJsonPath('error.code', $code);
        }
        $this->mock(LocalDraftProductChangeWriter::class)->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Private Change Canary SQL'));
        $result = $this->putJson(self::URL, $this->body())->assertStatus(500)->assertJsonPath('error.code', 'internal_error');
        $this->assertStringNotContainsString('Canary', $result->getContent());
    }

    public function test_update_and_history_rate_buckets_and_actors_are_separate(): void
    {
        $this->allow();
        $this->mock(LocalDraftProductChangeWriter::class)->shouldReceive('execute')->times(31)->andReturn($this->operation());
        for ($i = 0; $i < 30; $i++) {
            $this->putJson(self::URL, $this->body())->assertOk();
        }
        $this->putJson(self::URL, $this->body())->assertStatus(429)->assertHeader('Retry-After');
        $this->mock(LocalDraftProductChangeStore::class)->shouldReceive('find')->times(31)->andReturn($this->operation());
        for ($i = 0; $i < 30; $i++) {
            $this->getJson(self::HISTORY)->assertOk();
        }
        $this->getJson(self::HISTORY)->assertStatus(429)->assertHeader('Retry-After');
        $this->actingAs((new IdentityUser)->forceFill(['id' => 12]), 'web');
        $this->putJson(self::URL, $this->body())->assertOk();
        $this->getJson(self::HISTORY)->assertOk();
    }

    public function test_fresh_and_cached_routes_deny_nonlocal_before_session_csrf_and_all_ports(): void
    {
        $directory = sys_get_temp_dir().'/traepe_change_routes_'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($directory, 0700));
        $cache = $directory.'/routes.php';
        $env = ['APP_ENV' => 'local', 'APP_ROUTES_CACHE' => $cache, 'APP_CONFIG_CACHE' => $directory.'/config.php', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        try {
            foreach (['production', 'staging'] as $name) {
                $build = new Process([PHP_BINARY, 'artisan', 'route:cache', '--no-interaction'], base_path(), $env);
                $this->assertSame(0, $build->setTimeout(30)->run());
                foreach ([true, false] as $cached) {
                    if (! $cached) {
                        unlink($cache);
                    }
                    $worker = new Process([PHP_BINARY, base_path('tests/Support/product-change-environment-worker.php')], base_path(), array_replace($env, ['APP_ENV' => $name]));
                    $this->assertSame(0, $worker->setTimeout(30)->run());
                    $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                    $this->assertSame([false, false, false], $result['bindings']);
                    $this->assertSame([false, false, false, false, false], $result['touched']);
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
