<?php

namespace Tests\Feature;

use App\Modules\Catalog\Application\Drafts\LocalDraftProductAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductWriter;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductOperation;
use App\Modules\Catalog\Domain\Drafts\DraftProductPage;
use App\Modules\Catalog\Domain\Drafts\DraftProductProfile;
use App\Modules\Identity\Infrastructure\IdentityUser;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LocalDraftProductHttpTest extends TestCase
{
    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private const BASE = '/api/v1/catalog/local-draft-profiles/'.self::ID;

    private const HISTORY = '/api/v1/catalog/local-draft-product-operations/'.self::ID;

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
        $this->mock(LocalDraftProductAccess::class)->shouldReceive('actor')->andReturn(self::ID);
        $this->withHeaders(['Idempotency-Key' => 'synthetic-additional-key', 'If-Match' => '"catalog:'.self::ID.':v1"']);
    }

    private function body(): array
    {
        return ['product' => ['name' => ' Synthetic Additional Canary ', 'description' => null, 'brand' => null]];
    }

    private function profile(): DraftCatalogProfile
    {
        return new DraftCatalogProfile(self::ID, self::ID, 'Synthetic Catalog', 1);
    }

    private function operation(): DraftProductOperation
    {
        return new DraftProductOperation(self::ID, $this->profile(), new DraftProductProfile(self::ID, self::ID, new DraftProductData(' Synthetic Additional Canary ', null, null)));
    }

    public function test_fresh_and_cached_local_routes_deny_nonlocal_before_session_and_all_ports(): void
    {
        $directory = sys_get_temp_dir().'/traepe_product_routes_'.bin2hex(random_bytes(8));
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
                    $worker = new Process([PHP_BINARY, base_path('tests/Support/product-environment-worker.php')], base_path(), array_replace($environment, ['APP_ENV' => $name]));
                    $this->assertSame(0, $worker->setTimeout(30)->run());
                    $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                    $this->assertSame([false, false, false, false], $result['bindings']);
                    $this->assertSame([false, false, false, false, false, false], $result['touched']);
                    $this->assertSame([404, 404, 404, 404], $result['statuses']);
                    $this->assertSame($cached, $result['registered']);
                }
            }
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }rmdir($directory);
        }
    }

    public function test_session_and_permissions_precede_input_and_storage(): void
    {
        $this->mock(LocalDraftProductStore::class)->shouldNotReceive('profile', 'page', 'find', 'append');
        $this->mock(LocalDraftProductWriter::class)->shouldNotReceive('execute');
        foreach ([self::BASE, self::BASE.'/products', self::HISTORY] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->postJson(self::BASE.'/products', [])->assertUnauthorized();
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11]), 'web');
        $this->mock(LocalDraftProductAccess::class)->shouldReceive('actor')->andReturn(null);
        $this->getJson(self::BASE.'?unexpected=1')->assertForbidden();
        $this->postJson(self::BASE.'/products', [])->assertForbidden();
    }

    public function test_closed_body_headers_and_queries_reject_before_writer(): void
    {
        $this->allow();
        $this->mock(LocalDraftProductWriter::class)->shouldNotReceive('execute');
        $this->withoutHeader('If-Match')->postJson(self::BASE.'/products', $this->body())->assertStatus(428)->assertJsonPath('error.code', 'precondition_required');
        foreach (['*', 'W/"catalog:'.self::ID.':v1"', '"catalog:'.self::ID.':v01"', '"catalog:'.self::ID.':v2147483648"'] as $etag) {
            $this->withHeader('If-Match', $etag)->postJson(self::BASE.'/products', $this->body())->assertUnprocessable();
        }
        $this->withHeader('If-Match', '"catalog:'.self::ID.':v1"');
        foreach ([[], ['product' => []], $this->body() + ['actor' => self::ID], ['product' => ['name' => 'x', 'description' => null, 'brand' => null, 'status' => 'active']]] as $body) {
            $this->postJson(self::BASE.'/products', $body)->assertUnprocessable();
        }
        $this->postJson(self::BASE.'/products?unexpected=1', $this->body())->assertUnprocessable();
        $this->getJson(self::BASE.'?limit=1')->assertUnprocessable();
        $this->getJson(self::HISTORY.'?limit=1')->assertUnprocessable();
        foreach (['limit=0', 'limit=51', 'limit=01', 'limit=1.0', 'limit[]=1', 'cursor=', 'cursor[]=x', 'offset=1'] as $q) {
            $this->getJson(self::BASE.'/products?'.$q)->assertUnprocessable();
        }
    }

    public function test_profile_collection_and_operation_preserve_contracts_and_privacy(): void
    {
        $this->allow();
        $store = $this->mock(LocalDraftProductStore::class);
        $store->shouldReceive('profile')->with(self::ID, self::ID)->once()->andReturn($this->profile());
        $this->getJson(self::BASE)->assertOk()->assertHeader('ETag', $this->profile()->etag())->assertJsonPath('data.attributes.version', 1);
        $store->shouldReceive('page')->with(self::ID, self::ID, 20, null)->once()->andReturn(new DraftProductPage(1, [$this->operation()->product], false, 11));
        $page = $this->getJson(self::BASE.'/products')->assertOk()->assertJsonPath('meta.catalog_version', 1)->assertJsonPath('meta.has_more', false)->assertJsonPath('meta.next_cursor', null);
        $this->assertSame(['correlation_id', 'catalog_version', 'next_cursor', 'has_more', 'filters'], array_keys($page->json('meta')));
        $this->assertSame($this->operation()->product->snapshot(), $page->json('data.0.attributes'));
        $this->mock(LocalDraftProductWriter::class)->shouldReceive('execute')->once()->andReturn($this->operation());
        $created = $this->postJson(self::BASE.'/products', $this->body())->assertCreated()->assertJsonPath('data.attributes.catalog.version_after', 2);
        $store->shouldReceive('find')->with(self::ID, self::ID)->once()->andReturn($this->operation());
        $this->getJson(self::HISTORY)->assertOk()->assertJsonPath('data', $created->json('data'));
        $this->assertStringContainsString('no-store', $created->headers->get('Cache-Control'));
    }

    public function test_failure_statuses_do_not_expose_private_conditions(): void
    {
        $this->allow();
        foreach (['version_conflict' => 409, 'idempotency_mismatch' => 409, 'idempotency_expired' => 409, 'not_found' => 404, 'forbidden' => 403] as $code => $status) {
            $this->mock(LocalDraftProductWriter::class)->shouldReceive('execute')->once()->andThrow(new DraftCatalogFailure($code));
            $this->postJson(self::BASE.'/products', $this->body())->assertStatus($status)->assertJsonPath('error.code', $code);
        }
        $this->mock(LocalDraftProductWriter::class)->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Private Synthetic Canary SQL'));
        $response = $this->postJson(self::BASE.'/products', $this->body())->assertStatus(500)->assertJsonPath('error.code', 'internal_error');
        $this->assertStringNotContainsString('Canary', $response->getContent());
    }

    public function test_content_shares_bucket_but_create_history_and_actors_have_independent_budgets(): void
    {
        $this->allow();
        $store = $this->mock(LocalDraftProductStore::class);
        $store->shouldReceive('profile')->times(15)->andReturn($this->profile());
        $store->shouldReceive('page')->times(15)->andReturn(new DraftProductPage(1, [], false, 0));
        for ($n = 0; $n < 15; $n++) {
            $this->getJson(self::BASE)->assertOk();
            $this->getJson(self::BASE.'/products')->assertOk();
        }
        $this->getJson(self::BASE)->assertStatus(429)->assertHeader('Retry-After');
        $this->getJson(self::BASE.'/products')->assertStatus(429);
        $this->mock(LocalDraftProductWriter::class)->shouldReceive('execute')->once()->andReturn($this->operation());
        $this->postJson(self::BASE.'/products', $this->body())->assertCreated();
        $store->shouldReceive('find')->once()->andReturn($this->operation());
        $this->getJson(self::HISTORY)->assertOk();
        $this->actingAs((new IdentityUser)->forceFill(['id' => 12]), 'web');
        $store->shouldReceive('profile')->once()->andReturn($this->profile());
        $this->getJson(self::BASE)->assertOk();
    }
}
