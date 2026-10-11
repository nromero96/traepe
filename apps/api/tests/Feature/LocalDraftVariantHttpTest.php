<?php

namespace Tests\Feature;

use App\Modules\Catalog\Application\Drafts\LocalDraftVariantAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantWriter;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductProfile;
use App\Modules\Catalog\Domain\Drafts\DraftVariantData;
use App\Modules\Catalog\Domain\Drafts\DraftVariantOperation;
use App\Modules\Catalog\Domain\Drafts\DraftVariantPage;
use App\Modules\Catalog\Domain\Drafts\DraftVariantProfile;
use App\Modules\Identity\Infrastructure\IdentityUser;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LocalDraftVariantHttpTest extends TestCase
{
    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private const PRODUCT = '01ARZ3NDEKTSV4RRFFQ69G5FB1';

    private const URL = '/api/v1/catalog/local-draft-profiles/'.self::ID.'/products/'.self::PRODUCT.'/variants';

    private const HISTORY = '/api/v1/catalog/local-draft-variant-operations/'.self::ID;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn () => 'testing');
        config(['session.driver' => 'array', 'cache.limiter' => 'array']);
        $this->app->forgetInstance(RateLimiter::class);
    }

    private function body(): array
    {
        return ['variant' => ['name' => 'Presentation', 'sku' => 'DEMO-001', 'size' => null, 'unit' => 'unit', 'barcode' => null, 'attributes' => (object) []]];
    }

    private function allow(): void
    {
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11]), 'web');
        $this->mock(LocalDraftVariantAccess::class)->shouldReceive('actor')->andReturn(self::ID);
        $this->withHeaders(['If-Match' => '"catalog:'.self::ID.':v1"', 'Idempotency-Key' => 'synthetic-variant-key']);
    }

    private function operation(): DraftVariantOperation
    {
        return new DraftVariantOperation(self::ID, new DraftCatalogProfile(self::ID, self::ID, 'Catalog', 1),
            new DraftProductProfile(self::PRODUCT, self::ID, new DraftProductData('Product', null, null), 1),
            new DraftVariantProfile(self::ID, self::ID, self::PRODUCT, new DraftVariantData('Presentation', 'DEMO-001', null, 'unit', null, [])));
    }

    public function test_authentication_and_capability_precede_input_and_sql(): void
    {
        $this->mock(LocalDraftVariantStore::class)->shouldNotReceive('find', 'page');
        $this->mock(LocalDraftVariantWriter::class)->shouldNotReceive('execute');
        $this->postJson(self::URL, [])->assertUnauthorized();
        $this->getJson(self::URL)->assertUnauthorized();
        $this->getJson(self::HISTORY)->assertUnauthorized();
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11]), 'web');
        $this->mock(LocalDraftVariantAccess::class)->shouldReceive('actor')->andReturn(null);
        $this->postJson(self::URL.'?bad=1', [])->assertForbidden();
        $this->getJson(self::URL.'?bad=1')->assertForbidden();
        $this->getJson(self::HISTORY.'?bad=1')->assertForbidden();
    }

    public function test_closed_json_objects_etags_keys_queries_and_media_are_validated(): void
    {
        $this->allow();
        $this->mock(LocalDraftVariantWriter::class)->shouldNotReceive('execute');
        $this->withoutHeader('If-Match')->postJson(self::URL, $this->body())->assertStatus(428)->assertJsonPath('error.code', 'precondition_required');
        foreach (['*', 'W/"catalog:'.self::ID.':v1"', '"catalog:'.self::ID.':v01"', '"catalog:'.self::PRODUCT.':v1"'] as $etag) {
            $this->withHeader('If-Match', $etag)->postJson(self::URL, $this->body())->assertUnprocessable();
        }
        $this->withHeader('If-Match', '"catalog:'.self::ID.':v1"');
        foreach ([[], ['variant' => []], $this->body() + ['actor' => self::ID], ['variant' => $this->body()['variant'] + ['version' => 1]], ['variant' => array_replace($this->body()['variant'], ['attributes' => []])]] as $body) {
            $this->postJson(self::URL, $body)->assertUnprocessable();
        }
        foreach (['', 'bad key', str_repeat('x', 256)] as $key) {
            $this->withHeader('Idempotency-Key', $key)->postJson(self::URL, $this->body())->assertUnprocessable();
        }
        $this->withHeader('Idempotency-Key', 'synthetic-variant-key')->postJson(self::URL.'?limit=1', $this->body())->assertUnprocessable();
        foreach (['?limit=01', '?limit=51', '?offset=1', '?cursor=', '?limit[]=1', '?cursor[]=x'] as $query) {
            $this->getJson(self::URL.$query)->assertUnprocessable();
        }
        $this->getJson(self::HISTORY.'?limit=1')->assertUnprocessable();
        $this->getJson(str_replace(self::PRODUCT, 'invalid', self::URL))->assertUnprocessable();
        $this->withHeader('Content-Type', 'text/plain')->post(self::URL, $this->body())->assertStatus(415);
    }

    public function test_original_operation_and_empty_page_preserve_object_shape_privacy_and_etag(): void
    {
        $this->allow();
        $this->mock(LocalDraftVariantWriter::class)->shouldReceive('execute')->once()->andReturn($this->operation());
        $result = $this->postJson(self::URL, $this->body())->assertCreated()->assertJsonPath('data.type', 'local_draft_variant_operation')
            ->assertJsonPath('data.attributes.product.version_after', 2)->assertJsonPath('data.attributes.variant.sku', 'DEMO-001');
        $this->assertStringContainsString('"attributes":{}', $result->getContent());
        $this->assertFalse($result->headers->has('ETag'));
        $this->assertStringContainsString('no-store', $result->headers->get('Cache-Control'));
        $this->mock(LocalDraftVariantStore::class)->shouldReceive('find')->once()->with(self::ID, self::ID)->andReturn($this->operation());
        $this->getJson(self::HISTORY)->assertOk()->assertJsonPath('data', $result->json('data'));
        $this->mock(LocalDraftVariantStore::class)->shouldReceive('page')->once()->with(self::ID, self::PRODUCT, self::ID, 20, null)->andReturn(new DraftVariantPage(2, 2, [], false, 0));
        $page = $this->getJson(self::URL)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.product_version', 2)
            ->assertJsonPath('meta.next_cursor', null)->assertJsonPath('meta.has_more', false)->assertHeader('ETag', '"catalog:'.self::ID.':v2"');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
    }

    public function test_expected_conflicts_and_private_failures_are_sanitized(): void
    {
        $this->allow();
        foreach (['sku_conflict' => 409, 'version_conflict' => 409, 'idempotency_mismatch' => 409, 'idempotency_expired' => 409, 'not_found' => 404, 'forbidden' => 403] as $code => $status) {
            $this->mock(LocalDraftVariantWriter::class)->shouldReceive('execute')->once()->andThrow(new DraftCatalogFailure($code));
            $this->postJson(self::URL, $this->body())->assertStatus($status)->assertJsonPath('error.code', $code);
        }
        $this->mock(LocalDraftVariantWriter::class)->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Private Variant Canary SQL'));
        $result = $this->postJson(self::URL, $this->body())->assertStatus(500)->assertJsonPath('error.code', 'internal_error');
        $this->assertStringNotContainsString('Canary', $result->getContent());
    }

    public function test_three_rate_buckets_and_actors_are_independent(): void
    {
        $this->allow();
        $this->mock(LocalDraftVariantWriter::class)->shouldReceive('execute')->times(31)->andReturn($this->operation());
        $this->mock(LocalDraftVariantStore::class)->shouldReceive('find')->times(31)->andReturn($this->operation())->shouldReceive('page')->times(31)->andReturn(new DraftVariantPage(1, 1, [], false, 0));
        for ($i = 0; $i < 30; $i++) {
            $this->postJson(self::URL, $this->body())->assertCreated();
            $this->getJson(self::URL)->assertOk();
            $this->getJson(self::HISTORY)->assertOk();
        }
        $this->postJson(self::URL, $this->body())->assertStatus(429)->assertHeader('Retry-After');
        $this->getJson(self::URL)->assertStatus(429)->assertHeader('Retry-After');
        $this->getJson(self::HISTORY)->assertStatus(429)->assertHeader('Retry-After');
        $this->actingAs((new IdentityUser)->forceFill(['id' => 12]), 'web');
        $this->postJson(self::URL, $this->body())->assertCreated();
        $this->getJson(self::URL)->assertOk();
        $this->getJson(self::HISTORY)->assertOk();
    }

    public function test_fresh_and_cached_routes_deny_nonlocal_before_session_csrf_and_all_ports(): void
    {
        $directory = sys_get_temp_dir().'/traepe_variant_routes_'.bin2hex(random_bytes(8));
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
                    $worker = new Process([PHP_BINARY, base_path('tests/Support/variant-environment-worker.php')], base_path(), array_replace($env, ['APP_ENV' => $name]));
                    $this->assertSame(0, $worker->setTimeout(30)->run());
                    $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                    $this->assertSame([false, false, false, false], $result['bindings']);
                    $this->assertSame([false, false, false, false, false, false], $result['touched']);
                    $this->assertSame([404, 404, 404], $result['statuses']);
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
