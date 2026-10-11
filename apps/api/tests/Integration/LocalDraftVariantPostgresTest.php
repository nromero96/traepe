<?php

namespace Tests\Integration;

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftCatalog;
use App\Modules\Catalog\Application\Drafts\CreateLocalDraftProduct;
use App\Modules\Catalog\Application\Drafts\CreateLocalDraftVariant;
use App\Modules\Catalog\Application\Drafts\ListLocalDraftProducts;
use App\Modules\Catalog\Application\Drafts\ListLocalDraftVariants;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftMerchantSource;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantCursorCodec;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantWriter;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftVariant;
use App\Modules\Catalog\Application\Drafts\UpdateLocalDraftProduct;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantCursor;
use App\Modules\Catalog\Domain\Drafts\DraftVariantData;
use App\Modules\Catalog\Domain\Drafts\DraftVariantInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantOperation;
use App\Modules\Identity\Infrastructure\IdentityUser;
use App\Modules\Marketplace\Application\Commerce\CreateLocalDraftCommerce;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceAccess;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceInput;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

final class LocalDraftVariantPostgresTest extends PostgresTestCase
{
    private const MIGRATION = '2026_10_11_000010_create_local_draft_variants.php';

    private function fixture(): array
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => '', 'status' => 'active']);
        $user = IdentityUser::query()->findOrFail($id);
        foreach ([LocalDraftVariantAccess::CREATE, LocalDraftVariantAccess::CONTENT, LocalDraftVariantAccess::READ, LocalDraftCommerceAccess::CREATE, LocalDraftCatalogAccess::CREATE, LocalDraftCatalogAccess::READ, LocalDraftProductAccess::CREATE, LocalDraftProductAccess::CONTENT, LocalDraftProductAccess::READ, LocalDraftProductChangeAccess::UPDATE, LocalDraftProductChangeAccess::READ] as $cap) {
            $permission = DB::table('identity_permissions')->where('code', $cap)->value('id') ?? DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => $cap]);
            DB::table('identity_permission_grants')->insert(['public_id' => (string) Str::ulid(), 'user_id' => $id, 'permission_id' => $permission, 'scope_type' => 'platform', 'scope_public_id' => LocalDraftProductAccess::SCOPE_PUBLIC_ID, 'effect' => 'allow']);
        }
        $country = DB::table('countries')->value('id') ?? DB::table('countries')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic', 'currency_code' => 'ZZZ']);
        $market = (string) Str::ulid();
        DB::table('markets')->insert(['public_id' => $market, 'country_id' => $country, 'name' => 'Synthetic', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']);
        $commerce = app(CreateLocalDraftCommerce::class)->execute($id, DraftCommerceInput::fromArray(['merchant' => ['legal_name' => 'Synthetic Legal', 'trade_name' => 'Synthetic Trade'], 'branch' => ['market_public_id' => $market, 'name' => 'Synthetic Branch', 'longitude' => 0.5, 'latitude' => 1.5, 'timezone' => 'Etc/UTC']]), (string) Str::ulid(), (string) Str::ulid());
        $catalog = app(CreateLocalDraftCatalog::class)->execute($id, DraftCatalogInput::fromArray(['merchant_public_id' => $commerce->merchant->value, 'catalog' => ['name' => ' Synthetic Catalog '], 'product' => ['name' => 'Synthetic Initial', 'description' => null, 'brand' => null]]), (string) Str::ulid(), (string) Str::ulid());

        return [$user, $catalog];
    }

    private function input(string $catalog, int $version = 1): DraftProductInput
    {
        return new DraftProductInput($catalog, $version, new DraftProductData(' Synthetic Additional Canary ', "Synthetic Description Canary\nliteral", null));
    }

    private function add(IdentityUser $user, string $catalog, int $version = 1, string $key = 'synthetic-product-key')
    {
        return app(CreateLocalDraftProduct::class)->execute((int) $user->getKey(), $this->input($catalog, $version), $key, (string) Str::ulid());
    }

    private function rejected(callable $call, string $code): void
    {
        try {
            $call();
            $this->fail('Expected product rejection.');
        } catch (DraftCatalogFailure $failure) {
            $this->assertSame($code, $failure->getMessage());
        }
    }

    private function snapshot(array $tables): array
    {
        $values = [];
        foreach ($tables as $table) {
            $rows = DB::select('SELECT row_to_json(t)::text AS payload FROM "'.$table.'" t ORDER BY row_to_json(t)::text');
            $values[$table] = hash('sha256', json_encode(array_column($rows, 'payload'), JSON_THROW_ON_ERROR));
        }

        return $values;
    }

    private function variant(IdentityUser $user, string $catalog, string $product, int $version = 1, string $sku = 'DEMO-001', ?string $key = null, array $attributes = []): DraftVariantOperation
    {
        return app(CreateLocalDraftVariant::class)->execute((int) $user->getKey(), new DraftVariantInput($catalog, $product, $version,
            new DraftVariantData(' Synthetic Variant Canary ', $sku, ' 250 ', ' unit ', null, $attributes)), $key ?? (string) Str::ulid(), (string) Str::ulid());
    }

    private function state(): array
    {
        return $this->snapshot(['catalogs', 'products', 'product_variants', 'catalog_local_draft_operations', 'catalog_local_draft_product_operations', 'catalog_local_draft_product_changes', 'catalog_local_draft_variant_operations', 'platform_idempotency_keys']);
    }

    private function version(string $catalog): int
    {
        return (int) DB::table('catalogs')->where('public_id', $catalog)->value('version');
    }

    private function secondCatalog(IdentityUser $user, string $merchant)
    {
        return app(CreateLocalDraftCatalog::class)->execute((int) $user->getKey(), DraftCatalogInput::fromArray(['merchant_public_id' => $merchant, 'catalog' => ['name' => 'Another Catalog'], 'product' => ['name' => 'Another Product', 'description' => null, 'brand' => null]]), (string) Str::ulid(), (string) Str::ulid());
    }

    public function test_initial_additional_and_edited_products_keep_original_histories_and_replay(): void
    {
        [$user,$base] = $this->fixture();
        $legacy = $this->snapshot(['merchants', 'branches', 'marketplace_local_commerce_operations', 'catalog_local_draft_operations']);
        $first = $this->variant($user, $base->catalogPublicId, $base->productPublicId, 1, 'DEMO-001', 'original-key');
        $this->assertSame(2, $this->version($base->catalogPublicId));
        $this->assertSame(2, DB::table('products')->where('public_id', $base->productPublicId)->value('version'));
        $this->assertSame('{}', DB::table('product_variants')->value('attributes'));
        $additional = $this->add($user, $base->catalogPublicId, 2, 'additional-key');
        $changed = app(UpdateLocalDraftProduct::class)->execute((int) $user->getKey(), new DraftProductChangeInput($base->catalogPublicId, $additional->product->publicId, 3, new DraftProductData('Edited Product', null, null)), 'edit-key', (string) Str::ulid());
        $second = $this->variant($user, $base->catalogPublicId, $additional->product->publicId, 4, 'DEMO-002', 'second-key', ['color' => ' azul ', 'material' => 'ficticio']);
        $this->assertSame('Edited Product', $second->productBefore->product->name);
        $this->assertSame(2, $second->productBefore->version);
        $this->assertSame($legacy, $this->snapshot(array_keys($legacy)));
        $this->assertSame('Synthetic Initial', app(LocalDraftCatalogStore::class)->find($base->publicId, $user->public_id)->input->productName);
        $this->assertSame($additional->snapshot(), app(LocalDraftProductStore::class)->find($additional->publicId, $user->public_id)->snapshot());
        $this->assertSame($changed->snapshot(), app(LocalDraftProductChangeStore::class)->find($changed->publicId, $user->public_id)->snapshot());
        $before = $this->state();
        $replay = $this->variant($user, $base->catalogPublicId, $base->productPublicId, 1, 'DEMO-001', 'original-key');
        $this->assertEquals($first->snapshot(), $replay->snapshot());
        $this->assertSame($first->publicId, $replay->publicId);
        $this->assertSame($before, $this->state());
        $row = DB::table('catalog_local_draft_variant_operations')->where('public_id', $first->publicId)->first();
        $this->assertStringNotContainsString('Variant Canary', $row->response_snapshot_ciphertext);
        $this->assertSame($row->response_hash, hash('sha256', Crypt::decryptString($row->response_snapshot_ciphertext)));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $read = app(LocalDraftVariantStore::class)->find($first->publicId, $user->public_id);
        $this->assertCount(1, DB::getQueryLog());
        $this->assertStringContainsString('catalog_local_draft_variant_operations', DB::getQueryLog()[0]['query']);
        DB::disableQueryLog();
        $this->assertEquals($first->snapshot(), $read->snapshot());
    }

    public function test_sku_scope_soft_delete_version_precedence_and_idempotency_are_atomic(): void
    {
        [$user,$base] = $this->fixture();
        $first = $this->variant($user, $base->catalogPublicId, $base->productPublicId, 1, 'SAME-SKU', 'sku-key');
        $before = $this->state();
        $this->rejected(fn () => $this->variant($user, $base->catalogPublicId, $base->productPublicId, 1, 'SAME-SKU'), 'version_conflict');
        $this->rejected(fn () => $this->variant($user, $base->catalogPublicId, $base->productPublicId, 2, 'SAME-SKU'), 'sku_conflict');
        $this->rejected(fn () => $this->variant($user, $base->catalogPublicId, $base->productPublicId, 2, 'OTHER', 'sku-key'), 'idempotency_mismatch');
        $this->assertSame($before, $this->state());
        DB::table('product_variants')->where('public_id', $first->variant->publicId)->update(['deleted_at' => DB::raw('created_at')]);
        $before = $this->state();
        $this->rejected(fn () => $this->variant($user, $base->catalogPublicId, $base->productPublicId, 2, 'SAME-SKU'), 'sku_conflict');
        $this->assertSame($before, $this->state());
        $second = $this->secondCatalog($user, $first->catalogBefore->merchantPublicId);
        $before = $this->state();
        $this->rejected(fn () => $this->variant($user, $second->catalogPublicId, $second->productPublicId, 1, 'SAME-SKU'), 'sku_conflict');
        $this->assertSame($before, $this->state());
        [$other,$foreign] = $this->fixture();
        $this->assertSame('SAME-SKU', $this->variant($other, $foreign->catalogPublicId, $foreign->productPublicId, 1, 'SAME-SKU')->variant->variant->sku);
        DB::table('platform_idempotency_keys')->where('request_hash', DB::table('catalog_local_draft_variant_operations')->where('public_id', $first->publicId)->value('request_hash'))->update(['expires_at' => DB::raw("CURRENT_TIMESTAMP-INTERVAL '1 second'")]);
        $before = $this->state();
        $this->rejected(fn () => $this->variant($user, $base->catalogPublicId, $base->productPublicId, 1, 'SAME-SKU', 'sku-key'), 'idempotency_expired');
        $this->assertSame($before, $this->state());
    }

    public function test_ownership_provenance_parent_availability_and_overflow_never_leave_claims(): void
    {
        [$user,$base] = $this->fixture();
        [$other,$foreign] = $this->fixture();
        $manual = (string) Str::ulid();
        $catalogId = DB::table('catalogs')->where('public_id', $base->catalogPublicId)->value('id');
        DB::table('products')->insert(['public_id' => $manual, 'catalog_id' => $catalogId, 'name' => 'Manual']);
        foreach ([[$base->catalogPublicId, (string) Str::ulid()], [$foreign->catalogPublicId, $foreign->productPublicId], [$base->catalogPublicId, $foreign->productPublicId], [$base->catalogPublicId, $manual], [(string) Str::ulid(), $base->productPublicId]] as [$catalog,$product]) {
            $before = $this->state();
            $this->rejected(fn () => $this->variant($user, $catalog, $product), 'not_found');
            $this->assertSame($before, $this->state());
            $this->rejected(fn () => app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $catalog, $product, 20, null), 'not_found');
        }
        foreach (['products', 'catalogs'] as $table) {
            $public = $table === 'products' ? $base->productPublicId : $base->catalogPublicId;
            DB::table($table)->where('public_id', $public)->update(['deleted_at' => DB::raw('created_at')]);
            $before = $this->state();
            $this->rejected(fn () => $this->variant($user, $base->catalogPublicId, $base->productPublicId), 'not_found');
            $this->assertSame($before, $this->state());
            DB::table($table)->where('public_id', $public)->update(['deleted_at' => null, 'version' => 2147483647]);
            $before = $this->state();
            $this->rejected(fn () => $this->variant($user, $base->catalogPublicId, $base->productPublicId, $this->version($base->catalogPublicId)), 'version_conflict');
            $this->assertSame($before, $this->state());
            DB::table($table)->where('public_id', $public)->update(['version' => 1]);
        }
        $source = $this->mock(LocalDraftMerchantSource::class);
        $source->shouldReceive('lockOwnedDraft')->once()->andReturn(false);
        $this->app->forgetInstance(LocalDraftVariantWriter::class);
        $before = $this->state();
        $this->rejected(fn () => $this->variant($user, $base->catalogPublicId, $base->productPublicId), 'not_found');
        $this->assertSame($before, $this->state());
    }

    public function test_failures_after_each_write_and_callback_revocation_roll_back_everything(): void
    {
        [$user,$base] = $this->fixture();
        foreach (['product_variants', 'products', 'catalogs', 'catalog_local_draft_variant_operations'] as $table) {
            DB::unprepared("CREATE FUNCTION synthetic_variant_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Synthetic variant failure' USING ERRCODE='23514'; END; $$; CREATE TRIGGER synthetic_variant_failure AFTER INSERT OR UPDATE ON ".$table.' FOR EACH ROW EXECUTE FUNCTION synthetic_variant_failure()');
            $before = $this->state();
            try {
                $this->variant($user, $base->catalogPublicId, $base->productPublicId);
                $this->fail('Expected rollback');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
            $this->assertSame($before, $this->state());
            DB::unprepared('DROP TRIGGER synthetic_variant_failure ON '.$table.'; DROP FUNCTION synthetic_variant_failure()');
        }
        $this->mock(LocalDraftVariantAccess::class)->shouldReceive('actor')->andReturn($user->public_id, null);
        $before = $this->state();
        $this->rejected(fn () => $this->variant($user, $base->catalogPublicId, $base->productPublicId), 'forbidden');
        $this->assertSame($before, $this->state());
    }

    public function test_create_current_and_history_permissions_revoke_independently_and_history_ignores_profiles(): void
    {
        [$user,$base] = $this->fixture();
        $permissionIds = DB::table('identity_permissions')->whereIn('code', [LocalDraftVariantAccess::CONTENT, LocalDraftVariantAccess::READ])->pluck('id')->all();
        $reads = DB::table('identity_permission_grants')->where('user_id', $user->getKey())->whereIn('permission_id', $permissionIds)->get()->map(fn ($row) => (array) $row)->all();
        DB::table('identity_permission_grants')->where('user_id', $user->getKey())->whereIn('permission_id', $permissionIds)->delete();
        $op = $this->variant($user, $base->catalogPublicId, $base->productPublicId, 1, 'PERM-SKU', 'perm-key');
        $this->assertSame($op->publicId, $this->variant($user, $base->catalogPublicId, $base->productPublicId, 1, 'PERM-SKU', 'perm-key')->publicId);
        $this->rejected(fn () => app(ReadLocalDraftVariant::class)->execute((int) $user->getKey(), $op->publicId), 'forbidden');
        $this->rejected(fn () => app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 20, null), 'forbidden');
        DB::table('identity_permission_grants')->insert($reads);
        $createId = DB::table('identity_permissions')->where('code', LocalDraftVariantAccess::CREATE)->value('id');
        DB::table('identity_permission_grants')->where('user_id', $user->getKey())->where('permission_id', $createId)->update(['effect' => 'deny']);
        $before = $this->state();
        $this->rejected(fn () => $this->variant($user, $base->catalogPublicId, $base->productPublicId, 1, 'PERM-SKU', 'perm-key'), 'forbidden');
        $this->assertSame($before, $this->state());
        DB::table('products')->where('public_id', $base->productPublicId)->update(['deleted_at' => DB::raw('created_at')]);
        DB::table('catalogs')->where('public_id', $base->catalogPublicId)->update(['deleted_at' => DB::raw('created_at')]);
        $this->assertEquals($op->snapshot(), app(ReadLocalDraftVariant::class)->execute((int) $user->getKey(), $op->publicId)->snapshot());
        [$other] = $this->fixture();
        $this->rejected(fn () => app(ReadLocalDraftVariant::class)->execute((int) $other->getKey(), $op->publicId), 'not_found');
    }

    public function test_keyset_pages_are_one_statement_and_cursors_bind_both_parents_and_revisions(): void
    {
        [$user,$base] = $this->fixture();
        $empty = app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 20, null);
        $this->assertSame([], $empty->variants);
        $this->assertSame(1, $empty->productVersion);
        $ids = [];
        for ($i = 0; $i < 52; $i++) {
            $ids[] = $this->variant($user, $base->catalogPublicId, $base->productPublicId, $i + 1, 'PAGE-'.$i)->variant->publicId;
        }
        $seen = [];
        $token = null;
        do {
            $page = app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 20, $token);
            $seen = array_merge($seen, array_map(fn ($v) => $v->publicId, $page->variants));
            $token = $page->nextCursor;
        } while ($token !== null);
        $this->assertSame($ids, $seen);
        $this->assertSame(53, $page->catalogVersion);
        $this->assertSame(53, $page->productVersion);
        $first = app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 20, null);
        $codec = app(LocalDraftVariantCursorCodec::class);
        $cursor = $codec->decode($first->nextCursor);
        $second = app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 20, $first->nextCursor);
        $this->assertSame($cursor->expiresAt, $codec->decode($second->nextCursor)->expiresAt);
        foreach ([new DraftVariantCursor((string) Str::ulid(), $cursor->catalogPublicId, $cursor->productPublicId, 53, 53, 20, $cursor->lastId, $cursor->expiresAt),
            new DraftVariantCursor($cursor->actorPublicId, (string) Str::ulid(), $cursor->productPublicId, 53, 53, 20, $cursor->lastId, $cursor->expiresAt),
            new DraftVariantCursor($cursor->actorPublicId, $cursor->catalogPublicId, (string) Str::ulid(), 53, 53, 20, $cursor->lastId, $cursor->expiresAt),
            new DraftVariantCursor($cursor->actorPublicId, $cursor->catalogPublicId, $cursor->productPublicId, 53, 53, 21, $cursor->lastId, $cursor->expiresAt)] as $bad) {
            $this->rejected(fn () => app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 20, $codec->encode($bad)), 'validation_failed');
        }
        $this->rejected(fn () => app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 20, 'invalid-token'), 'validation_failed');
        $expired = new DraftVariantCursor($user->public_id, $base->catalogPublicId, $base->productPublicId, 53, 53, 20, $cursor->lastId, 1);
        $this->rejected(fn () => app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 20, $codec->encode($expired)), 'cursor_expired');
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(LocalDraftVariantStore::class)->page($base->catalogPublicId, $base->productPublicId, $user->public_id, 20, null);
        $this->assertCount(1, DB::getQueryLog());
        $query = DB::getQueryLog()[0];
        DB::disableQueryLog();
        $this->assertStringNotContainsString('identity_', $query['query']);
        $this->assertStringNotContainsString('platform_', $query['query']);
        $this->assertStringNotContainsString('merchants', $query['query']);
        DB::statement('SET enable_seqscan=off');
        $explain = DB::select('EXPLAIN '.$query['query'], $query['bindings']);
        DB::statement('RESET enable_seqscan');
        $this->assertStringContainsString('catalog_variants_product_keyset_index', json_encode($explain));
        $additional = $this->add($user, $base->catalogPublicId, 53, 'outside-product');
        $this->rejected(fn () => app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 20, $first->nextCursor), 'catalog_changed');
        $fresh = app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 20, null);
        app(UpdateLocalDraftProduct::class)->execute((int) $user->getKey(), new DraftProductChangeInput($base->catalogPublicId, $additional->product->publicId, 54, new DraftProductData('Outside Changed', null, null)), 'outside-change', (string) Str::ulid());
        $this->rejected(fn () => app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 20, $fresh->nextCursor), 'catalog_changed');
        $products = app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 1, null);
        $this->variant($user, $base->catalogPublicId, $base->productPublicId, 55, 'LAST-PAGE');
        $this->rejected(fn () => app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 1, $products->nextCursor), 'catalog_changed');
        DB::table('product_variants')->where('public_id', $ids[0])->update(['deleted_at' => DB::raw('created_at')]);
        $row = (array) DB::table('product_variants')->where('public_id', $ids[1])->first();
        unset($row['id']);
        $row['public_id'] = (string) Str::ulid();
        $row['sku'] = 'MANUAL';
        DB::table('product_variants')->insert($row);
        $visible = app(LocalDraftVariantStore::class)->page($base->catalogPublicId, $base->productPublicId, $user->public_id, 50, null);
        $this->assertNotContains($ids[0], array_map(fn ($v) => $v->publicId, $visible->variants));
        $this->assertNotContains($row['public_id'], array_map(fn ($v) => $v->publicId, $visible->variants));
    }

    public function test_postgres_enforces_map_text_sku_references_append_only_and_forward_only(): void
    {
        [$user,$base] = $this->fixture();
        [$other,$foreign] = $this->fixture();
        $op = $this->variant($user, $base->catalogPublicId, $base->productPublicId);
        $row = (array) DB::table('product_variants')->where('public_id', $op->variant->publicId)->first();
        unset($row['id']);
        $reject = function (array $overrides, string $state = '23514') use ($row): void {
            try {
                DB::table('product_variants')->insert(array_replace($row, ['public_id' => (string) Str::ulid(), 'sku' => (string) Str::ulid()], $overrides));
                $this->fail('Expected DB constraint');
            } catch (QueryException $e) {
                $this->assertSame($state, $e->errorInfo[0]);
            }
        };
        foreach (['[]', 'null', '1', '"text"', '{"a":null}', '{"a":true}', '{"a":1}', '{"a":[]}', '{"a":{}}', '{"a":""}', '{"a":" "}', '{"A":"x"}', '{"bad-key":"x"}', '{"a":"x\\n"}', json_encode((object) array_fill_keys(array_map(fn ($i) => 'k'.$i, range(0, 20)), 'x')), json_encode((object) ['a' => str_repeat('é', 256)])] as $json) {
            $reject(['attributes' => $json]);
        }
        foreach ([['name' => ''], ['unit' => ' '], ['size' => ''], ['name' => "x\n"], ['barcode' => ' '], ['sku' => 'lower'], ['sku' => "A\n"], ['status' => 'active'], ['version' => 2], ['public_id' => 'invalid']] as $invalid) {
            $reject($invalid);
        }
        $reject(['sku' => $op->variant->variant->sku], '23505');
        $foreignProduct = DB::table('products')->where('public_id', $foreign->productPublicId)->value('id');
        $reject(['product_id' => $foreignProduct], '23503');
        $reject(['merchant_public_id' => DB::table('catalogs')->where('public_id', $foreign->catalogPublicId)->value('merchant_public_id')], '23503');
        foreach (['update', 'delete'] as $action) {
            try {
                $query = DB::table('catalog_local_draft_variant_operations')->where('public_id', $op->publicId);
                $action === 'update' ? $query->update(['response_hash' => str_repeat('a', 64)]) : $query->delete();
                $this->fail('Expected append-only rejection');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
        try {
            DB::table('product_variants')->where('public_id', $op->variant->publicId)->delete();
            $this->fail('Expected restrictive FK');
        } catch (QueryException $e) {
            $this->assertSame('23503', $e->errorInfo[0]);
        }
        $migration = require database_path('migrations/'.self::MIGRATION);
        try {
            $migration->down();
            $this->fail('Expected forward-only');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forward-only', $e->getMessage());
        }
    }

    public function test_variant_journal_constraints_keep_reference_graph_and_revision_uniqueness(): void
    {
        [$user,$base] = $this->fixture();
        [$other,$foreign] = $this->fixture();
        $op = $this->variant($user, $base->catalogPublicId, $base->productPublicId);
        $variant = (array) DB::table('product_variants')->where('public_id', $op->variant->publicId)->first();
        unset($variant['id']);
        $variant['public_id'] = (string) Str::ulid();
        $variant['sku'] = 'JOURNAL-CANDIDATE';
        $candidate = DB::table('product_variants')->insertGetId($variant);
        $row = (array) DB::table('catalog_local_draft_variant_operations')->where('public_id', $op->publicId)->first();
        unset($row['id']);
        $row = array_replace($row, ['variant_id' => $candidate, 'catalog_version_before' => 2, 'catalog_version_after' => 3, 'product_version_before' => 2, 'product_version_after' => 3]);
        $before = $this->state();
        $reject = function (array $changes, string $code = '23514') use ($row): void {
            try {
                DB::table('catalog_local_draft_variant_operations')->insert(array_replace($row, ['public_id' => (string) Str::ulid()], $changes));
                $this->fail('Expected journal constraint');
            } catch (QueryException $e) {
                $this->assertSame($code, $e->errorInfo[0]);
            }
        };
        foreach (['public_id' => 'invalid', 'actor_public_id' => 'invalid', 'schema_version' => 2, 'key_hash' => 'raw', 'request_hash' => str_repeat('A', 64), 'response_hash' => 'raw', 'correlation_id' => 'invalid', 'catalog_version_before' => 0, 'catalog_version_after' => 99, 'catalog_version_before' => 2147483647, 'product_version_before' => 0, 'product_version_after' => 99, 'response_snapshot_ciphertext' => ' '] as $field => $value) {
            $reject([$field => $value]);
        }
        $foreignCatalog = DB::table('catalogs')->where('public_id', $foreign->catalogPublicId)->first();
        $reject(['catalog_id' => $foreignCatalog->id, 'merchant_public_id' => $foreignCatalog->merchant_public_id], '23503');
        $reject(['product_id' => DB::table('products')->where('public_id', $foreign->productPublicId)->value('id')], '23503');
        $reject(['public_id' => $op->publicId], '23505');
        $reject(['variant_id' => DB::table('product_variants')->where('public_id', $op->variant->publicId)->value('id')], '23505');
        $reject(['catalog_version_before' => 1, 'catalog_version_after' => 2], '23505');
        $reject(['product_version_before' => 1, 'product_version_after' => 2], '23505');
        $this->assertSame($before, $this->state());
    }

    public function test_cipher_hash_closed_map_and_metadata_corruption_are_rejected_without_profile_reads(): void
    {
        [$user,$base] = $this->fixture();
        $op = $this->variant($user, $base->catalogPublicId, $base->productPublicId);
        $row = (array) DB::table('catalog_local_draft_variant_operations')->where('public_id', $op->publicId)->first();
        DB::statement('ALTER TABLE catalog_local_draft_variant_operations DISABLE TRIGGER catalog_local_draft_variant_operations_append_only');
        try {
            $cases = [
                ['response_snapshot_ciphertext' => 'invalid'],
                ['response_hash' => str_repeat('a', 64)],
                ['catalog_version_before' => 9, 'catalog_version_after' => 10],
                ['product_version_before' => 9, 'product_version_after' => 10],
            ];
            $plain = Crypt::decryptString($row['response_snapshot_ciphertext']);
            $bad = json_decode($plain);
            $bad->variant->attributes = [];
            $badPlain = json_encode($bad, JSON_THROW_ON_ERROR);
            $cases[] = ['response_snapshot_ciphertext' => Crypt::encryptString($badPlain), 'response_hash' => hash('sha256', $badPlain)];
            $bad = json_decode($plain);
            $bad->variant->product_public_id = (string) Str::ulid();
            $badPlain = json_encode($bad, JSON_THROW_ON_ERROR);
            $cases[] = ['response_snapshot_ciphertext' => Crypt::encryptString($badPlain), 'response_hash' => hash('sha256', $badPlain)];
            foreach ($cases as $changes) {
                DB::table('catalog_local_draft_variant_operations')->where('public_id', $op->publicId)->update(array_replace($row, $changes));
                try {
                    app(LocalDraftVariantStore::class)->find($op->publicId, $user->public_id);
                    $this->fail('Expected corrupted snapshot rejection');
                } catch (DecryptException|\LogicException|\InvalidArgumentException $e) {
                    $this->assertTrue(true);
                }
            }
        } finally {
            DB::table('catalog_local_draft_variant_operations')->where('public_id', $op->publicId)->update($row);
            DB::statement('ALTER TABLE catalog_local_draft_variant_operations ENABLE TRIGGER catalog_local_draft_variant_operations_append_only');
        }
    }

    public function test_real_process_races_cover_replay_versions_cross_mutations_and_merchant_sku(): void
    {
        [$user,$base] = $this->fixture();
        $race = function (array $requests): array {
            $workers = [];
            $results = [];
            foreach ($requests as $request) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/variant-race-worker.php')], base_path(), ['APP_ENV' => 'testing', 'TRAEPE_TEST_DATABASE' => $this->probeDatabase]);
                $worker->setInput(json_encode($request, JSON_THROW_ON_ERROR));
                $worker->setTimeout(45);
                $worker->start();
                $workers[] = $worker;
            }
            foreach ($workers as $worker) {
                $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        };
        $request = function (string $catalog, string $product, int $version, string $key, string $sku, string $mode = 'variant') use ($user): array {
            return ['mode' => $mode, 'actor_id' => (int) $user->getKey(), 'catalog_public_id' => $catalog, 'product_public_id' => $product, 'version' => $version, 'key' => $key, 'sku' => $sku, 'name' => 'Race '.$key, 'start' => microtime(true) + 1];
        };
        foreach ([true, false] as $same) {
            $v = $this->version($base->catalogPublicId);
            $before = DB::table('platform_idempotency_keys')->count();
            $a = $request($base->catalogPublicId, $base->productPublicId, $v, 'race-'.$v.'-same', 'RACE-'.$v);
            $b = $same ? $a : $request($base->catalogPublicId, $base->productPublicId, $v, 'race-'.$v.'-other', 'RACE-'.$v.'-B');
            $b['start'] = $a['start'];
            $results = $race([$a, $b]);
            $codes = array_column($results, 'code');
            sort($codes);
            $this->assertSame($same ? ['created', 'created'] : ['created', 'version_conflict'], $codes);
            if ($same) {
                $this->assertSame($results[0]['operation'], $results[1]['operation']);
            }
            $this->assertSame($before + 1, DB::table('platform_idempotency_keys')->count());
            $this->assertSame($v + 1, $this->version($base->catalogPublicId));
        }
        $additional = $this->add($user, $base->catalogPublicId, $this->version($base->catalogPublicId), 'race-additional');
        foreach (['variant', 'append', 'change'] as $mode) {
            $v = $this->version($base->catalogPublicId);
            $before = DB::table('platform_idempotency_keys')->count();
            $a = $request($base->catalogPublicId, $base->productPublicId, $v, 'mixed-'.$v.'-a', 'MIXED-'.$v.'-A');
            $b = $request($base->catalogPublicId, $additional->product->publicId, $v, 'mixed-'.$v.'-b', 'MIXED-'.$v.'-B', $mode);
            $b['start'] = $a['start'];
            $results = $race([$a, $b]);
            $codes = array_column($results, 'code');
            $this->assertCount(1, array_filter($codes, fn ($c) => $c === 'version_conflict'));
            $this->assertCount(1, array_filter($codes, fn ($c) => in_array($c, ['created', 'added', 'changed'], true)));
            $this->assertSame($before + 1, DB::table('platform_idempotency_keys')->count());
            $this->assertSame($v + 1, $this->version($base->catalogPublicId));
        }
        $merchant = DB::table('catalogs')->where('public_id', $base->catalogPublicId)->value('merchant_public_id');
        $other = $this->secondCatalog($user, $merchant);
        foreach ([true, false] as $sameSku) {
            $before = DB::table('platform_idempotency_keys')->count();
            $a = $request($base->catalogPublicId, $base->productPublicId, $this->version($base->catalogPublicId), 'merchant-'.(int) $sameSku.'-a', 'MERCHANT-'.(int) $sameSku);
            $b = $request($other->catalogPublicId, $other->productPublicId, $this->version($other->catalogPublicId), 'merchant-'.(int) $sameSku.'-b', $sameSku ? $a['sku'] : 'MERCHANT-OTHER');
            $b['start'] = $a['start'];
            $results = $race([$a, $b]);
            $codes = array_column($results, 'code');
            sort($codes);
            $this->assertSame($sameSku ? ['created', 'sku_conflict'] : ['created', 'created'], $codes);
            $this->assertSame($before + ($sameSku ? 1 : 2), DB::table('platform_idempotency_keys')->count());
        }
    }

    public function test_additive_migration_preserves_35_prior_tables_and_second_run_is_noop(): void
    {
        $legacy = 'traepe_00e_test_'.bin2hex(random_bytes(8));
        config(['database.connections.pgsql.database' => 'postgres']);
        DB::purge('pgsql');
        DB::statement('CREATE DATABASE "'.$legacy.'"');
        try {
            config(['database.connections.pgsql.database' => $legacy]);
            DB::purge('pgsql');
            $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($p) => basename($p) < self::MIGRATION));
            $this->assertSame(0, Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]));
            $this->fixture();
            $tables = array_column(DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename<>'migrations'"), 'tablename');
            $this->assertCount(35, $tables);
            $before = $this->snapshot($tables);
            $old = DB::table('migrations')->orderBy('id')->get()->toArray();
            $count = count($old);
            $this->assertFalse(Schema::hasTable('product_variants'));
            $this->assertSame(0, Artisan::call('migrate', ['--path' => [database_path('migrations/'.self::MIGRATION)], '--realpath' => true, '--force' => true]));
            $this->assertSame($before, $this->snapshot($tables));
            $this->assertEquals($old, DB::table('migrations')->orderBy('id')->limit($count)->get()->toArray());
            $this->assertSame($count + 1, DB::table('migrations')->count());
            $this->assertSame(0, DB::table('product_variants')->count());
            $this->assertSame(0, DB::table('catalog_local_draft_variant_operations')->count());
            $this->assertSame(0, Artisan::call('migrate', ['--path' => [database_path('migrations/'.self::MIGRATION)], '--realpath' => true, '--force' => true]));
            $this->assertStringContainsString('Nothing to migrate', Artisan::output());
            $this->assertSame($before, $this->snapshot($tables));
            $this->assertSame($count + 1, DB::table('migrations')->count());
        } finally {
            config(['database.connections.pgsql.database' => 'postgres']);
            DB::purge('pgsql');
            DB::statement('DROP DATABASE "'.$legacy.'" WITH (FORCE)');
            config(['database.connections.pgsql.database' => $this->probeDatabase]);
            DB::purge('pgsql');
        }
    }

    public function test_nonlocal_store_writer_and_cursor_deny_before_sql_callback_or_claim(): void
    {
        $store = app(LocalDraftVariantStore::class);
        $writer = app(LocalDraftVariantWriter::class);
        $codec = app(LocalDraftVariantCursorCodec::class);
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';
        $input = new DraftVariantInput($id, $id, 1, new DraftVariantData('Test', 'TEST', null, 'unit', null, []));
        foreach (['production', 'staging'] as $env) {
            $this->app->detectEnvironment(fn () => $env);
            DB::enableQueryLog();
            DB::flushQueryLog();
            foreach ([fn () => $store->append($input, $id, str_repeat('a', 64), str_repeat('b', 64), $id), fn () => $store->find($id, $id), fn () => $store->page($id, $id, $id, 20, null),
                fn () => $writer->execute($input, $id, 'key', $id, function (): void {
                    $this->fail('Unexpected callback');
                }), fn () => $codec->encode(new DraftVariantCursor($id, $id, $id, 1, 1, 20, 1, 1)), fn () => $codec->decode('x')] as $call) {
                try {
                    $call();
                    $this->fail('Expected nonlocal guard');
                } catch (HttpException $e) {
                    $this->assertSame(404, $e->getStatusCode());
                }
            }
            $this->assertSame([], DB::getQueryLog());
            DB::disableQueryLog();
        }
    }
}
