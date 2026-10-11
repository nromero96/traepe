<?php

namespace Tests\Integration;

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftCatalog;
use App\Modules\Catalog\Application\Drafts\CreateLocalDraftProduct;
use App\Modules\Catalog\Application\Drafts\CreateLocalDraftVariant;
use App\Modules\Catalog\Application\Drafts\ListLocalDraftProducts;
use App\Modules\Catalog\Application\Drafts\ListLocalDraftVariants;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftMerchantSource;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantChangeAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantChangeStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantChangeWriter;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantStore;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftVariantChange;
use App\Modules\Catalog\Application\Drafts\UpdateLocalDraftProduct;
use App\Modules\Catalog\Application\Drafts\UpdateLocalDraftVariant;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantData;
use App\Modules\Catalog\Domain\Drafts\DraftVariantEditableData;
use App\Modules\Catalog\Domain\Drafts\DraftVariantInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantOperation;
use App\Modules\Catalog\Infrastructure\Drafts\PlatformLocalDraftVariantChangeWriter;
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

final class LocalDraftVariantChangePostgresTest extends PostgresTestCase
{
    private const MIGRATION = '2026_10_11_000011_create_local_draft_variant_changes.php';

    private function fixture(): array
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => '', 'status' => 'active']);
        $user = IdentityUser::query()->findOrFail($id);
        foreach ([LocalDraftVariantChangeAccess::UPDATE, LocalDraftVariantChangeAccess::READ, LocalDraftVariantAccess::CREATE, LocalDraftVariantAccess::CONTENT, LocalDraftVariantAccess::READ, LocalDraftCommerceAccess::CREATE, LocalDraftCatalogAccess::CREATE, LocalDraftCatalogAccess::READ, LocalDraftProductAccess::CREATE, LocalDraftProductAccess::CONTENT, LocalDraftProductAccess::READ, LocalDraftProductChangeAccess::UPDATE, LocalDraftProductChangeAccess::READ] as $cap) {
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
        return $this->snapshot(['catalogs', 'products', 'product_variants', 'catalog_local_draft_operations', 'catalog_local_draft_product_operations', 'catalog_local_draft_product_changes', 'catalog_local_draft_variant_operations', 'catalog_local_draft_variant_changes', 'platform_idempotency_keys']);
    }

    private function version(string $catalog): int
    {
        return (int) DB::table('catalogs')->where('public_id', $catalog)->value('version');
    }

    private function secondCatalog(IdentityUser $user, string $merchant)
    {
        return app(CreateLocalDraftCatalog::class)->execute((int) $user->getKey(), DraftCatalogInput::fromArray(['merchant_public_id' => $merchant, 'catalog' => ['name' => 'Another Catalog'], 'product' => ['name' => 'Another Product', 'description' => null, 'brand' => null]]), (string) Str::ulid(), (string) Str::ulid());
    }

    private function edit(IdentityUser $user, string $catalog, string $product, string $variant, int $version = 2, string $key = 'variant-edit-key', ?DraftVariantEditableData $data = null)
    {
        return app(UpdateLocalDraftVariant::class)->execute((int) $user->getKey(),
            new DraftVariantChangeInput($catalog, $product, $variant, $version, $data ?? new DraftVariantEditableData(' Synthetic Changed Canary ', null, 'unit', '!ASCII', [])),
            $key, (string) Str::ulid());
    }

    public function test_successive_edits_preserve_sku_original_histories_and_creation_replay(): void
    {
        [$user,$base] = $this->fixture();
        $first = $this->variant($user, $base->catalogPublicId, $base->productPublicId, 1, 'DEMO-001', 'original-key');
        $additional = $this->add($user, $base->catalogPublicId, 2, 'additional-key');
        $productChange = app(UpdateLocalDraftProduct::class)->execute((int) $user->getKey(), new DraftProductChangeInput($base->catalogPublicId, $additional->product->publicId, 3, new DraftProductData('Edited Product', null, null)), 'product-edit', (string) Str::ulid());
        $second = $this->variant($user, $base->catalogPublicId, $additional->product->publicId, 4, 'DEMO-002', 'additional-variant');
        $immutableTables = ['merchants', 'branches', 'marketplace_local_commerce_operations', 'catalog_local_draft_operations', 'catalog_local_draft_product_operations', 'catalog_local_draft_product_changes', 'catalog_local_draft_variant_operations'];
        $immutable = $this->snapshot($immutableTables);
        $original = (array) DB::table('product_variants')->where('public_id', $first->variant->publicId)->first();
        $parentOriginal = (array) DB::table('products')->where('public_id', $base->productPublicId)->first();
        $catalogOriginal = (array) DB::table('catalogs')->where('public_id', $base->catalogPublicId)->first();
        $op = $this->edit($user, $base->catalogPublicId, $base->productPublicId, $first->variant->publicId, 5);
        $this->assertSame(6, $this->version($base->catalogPublicId));
        $this->assertSame(3, $op->snapshot()['product']['version_after']);
        $this->assertSame(1, $op->snapshot()['variant']['before']['version']);
        $this->assertSame(2, $op->snapshot()['variant']['after']['version']);
        $this->assertSame('DEMO-001', $op->variantAfter->variant->sku);
        $this->assertSame('{}', DB::table('product_variants')->where('public_id', $first->variant->publicId)->value('attributes'));
        $var = (array) DB::table('product_variants')->where('public_id', $first->variant->publicId)->first();
        foreach (['id', 'public_id', 'catalog_id', 'product_id', 'merchant_public_id', 'sku', 'status', 'created_at', 'deleted_at'] as $field) {
            $this->assertSame($original[$field], $var[$field]);
        }
        foreach (['products' => $parentOriginal, 'catalogs' => $catalogOriginal] as $table => $old) {
            $new = (array) DB::table($table)->where('id', $old['id'])->first();
            unset($old['version'], $old['updated_at'], $new['version'], $new['updated_at']);
            $this->assertSame($old, $new);
        }
        $next = $this->edit($user, $base->catalogPublicId, $base->productPublicId, $first->variant->publicId, 6, 'second-edit',
            new DraftVariantEditableData('Another', ' opaque ', 'unit2', null, ['z' => ' último ', 'a' => 'primero']));
        $this->assertSame(3, $next->variantAfter->version);
        $stateAfterNext = $this->state();
        $this->assertEquals($next->snapshot(), $this->edit($user, $base->catalogPublicId, $base->productPublicId, $first->variant->publicId, 6, 'second-edit',
            new DraftVariantEditableData('Another', ' opaque ', 'unit2', null, ['a' => 'primero', 'z' => ' último ']))->snapshot());
        $this->assertSame($stateAfterNext, $this->state());
        $other = $this->edit($user, $base->catalogPublicId, $additional->product->publicId, $second->variant->publicId, 7, 'additional-edit');
        $this->assertSame('Edited Product', $other->productBefore->product->name);
        $this->assertSame(3, $other->productBefore->version);
        $this->assertSame($immutable, $this->snapshot($immutableTables));
        $this->assertEquals($additional->snapshot(), app(LocalDraftProductStore::class)->find($additional->publicId, $user->public_id)->snapshot());
        $this->assertEquals($productChange->snapshot(), app(LocalDraftProductChangeStore::class)->find($productChange->publicId, $user->public_id)->snapshot());
        $before = $this->state();
        $this->assertEquals($first->snapshot(), $this->variant($user, $base->catalogPublicId, $base->productPublicId, 1, 'DEMO-001', 'original-key')->snapshot());
        $this->assertEquals($op->snapshot(), $this->edit($user, $base->catalogPublicId, $base->productPublicId, $first->variant->publicId, 5)->snapshot());
        $this->assertEquals($op->snapshot(), app(ReadLocalDraftVariantChange::class)->execute((int) $user->getKey(), $op->publicId)->snapshot());
        $this->assertSame($before, $this->state());
        $row = DB::table('catalog_local_draft_variant_changes')->where('public_id', $op->publicId)->first();
        $this->assertStringNotContainsString('Changed Canary', $row->response_snapshot_ciphertext);
        $this->assertSame($row->response_hash, hash('sha256', Crypt::decryptString($row->response_snapshot_ciphertext)));
        $claim = DB::table('platform_idempotency_keys')->where('request_hash', $row->request_hash)->first();
        $this->assertSame(hash('sha256', PlatformLocalDraftVariantChangeWriter::SCOPE), $claim->scope_hash);
        $this->assertSame(86400, strtotime($claim->expires_at) - strtotime($claim->created_at));
    }

    public function test_no_changes_ordering_stale_maximum_and_replay_fingerprints_are_atomic(): void
    {
        [$user,$base] = $this->fixture();
        $op = $this->variant($user, $base->catalogPublicId, $base->productPublicId, 1, attributes: ['z' => 'last', 'a' => 'first']);
        $same = new DraftVariantEditableData(' Synthetic Variant Canary ', ' 250 ', ' unit ', null, ['a' => 'first', 'z' => 'last']);
        $before = $this->state();
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId, data: $same), 'no_changes');
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId, 1, data: $same), 'version_conflict');
        $this->assertSame($before, $this->state());
        foreach (['catalogs' => $base->catalogPublicId, 'products' => $base->productPublicId, 'product_variants' => $op->variant->publicId] as $table => $public) {
            $old = DB::table($table)->where('public_id', $public)->value('version');
            DB::table($table)->where('public_id', $public)->update(['version' => 2147483647]);
            $before = $this->state();
            $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId, $table === 'catalogs' ? 2147483647 : 2, 'max-'.$table, $same), 'version_conflict');
            $this->assertSame($before, $this->state());
            DB::table($table)->where('public_id', $public)->update(['version' => $old]);
        }
        $changed = $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId);
        $before = $this->state();
        foreach ([
            [$base->catalogPublicId, $base->productPublicId, $op->variant->publicId, 3, null],
            [$base->catalogPublicId, $base->productPublicId, $op->variant->publicId, 2, new DraftVariantEditableData('Other', null, 'unit', '!ASCII', [])],
            [(string) Str::ulid(), $base->productPublicId, $op->variant->publicId, 2, null],
            [$base->catalogPublicId, (string) Str::ulid(), $op->variant->publicId, 2, null],
            [$base->catalogPublicId, $base->productPublicId, (string) Str::ulid(), 2, null],
        ] as [$cat,$prod,$var,$ver,$data]) {
            $this->rejected(fn () => $this->edit($user, $cat, $prod, $var, $ver, data: $data), 'idempotency_mismatch');
        }
        $this->assertSame($before, $this->state());
        DB::table('platform_idempotency_keys')->where('scope_hash', hash('sha256', PlatformLocalDraftVariantChangeWriter::SCOPE))->update(['expires_at' => DB::raw("CURRENT_TIMESTAMP-INTERVAL '1 second'")]);
        $before = $this->state();
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId), 'idempotency_expired');
        $this->assertSame($before, $this->state());
        $this->assertSame(2, $changed->variantAfter->version);
    }

    public function test_crossed_foreign_manual_ineligible_and_deleted_variants_are_indistinguishable(): void
    {
        [$user,$base] = $this->fixture();
        [$other,$foreign] = $this->fixture();
        $op = $this->variant($user, $base->catalogPublicId, $base->productPublicId);
        $foreignVariant = $this->variant($other, $foreign->catalogPublicId, $foreign->productPublicId);
        $manual = (array) DB::table('product_variants')->where('public_id', $op->variant->publicId)->first();
        unset($manual['id']);
        $manual['public_id'] = (string) Str::ulid();
        $manual['sku'] = 'MANUAL';
        DB::table('product_variants')->insert($manual);
        foreach ([
            [$base->catalogPublicId, $base->productPublicId, (string) Str::ulid()],
            [$foreign->catalogPublicId, $foreign->productPublicId, $foreignVariant->variant->publicId],
            [$base->catalogPublicId, $foreign->productPublicId, $foreignVariant->variant->publicId],
            [$base->catalogPublicId, $base->productPublicId, $manual['public_id']],
            [(string) Str::ulid(), $base->productPublicId, $op->variant->publicId],
            [$base->catalogPublicId, (string) Str::ulid(), $op->variant->publicId],
        ] as [$cat,$prod,$var]) {
            $before = $this->state();
            $this->rejected(fn () => $this->edit($user, $cat, $prod, $var), 'not_found');
            $this->assertSame($before, $this->state());
        }
        foreach (['catalogs' => $base->catalogPublicId, 'products' => $base->productPublicId, 'product_variants' => $op->variant->publicId] as $table => $public) {
            DB::table($table)->where('public_id', $public)->update(['deleted_at' => DB::raw('created_at')]);
            $before = $this->state();
            $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId), 'not_found');
            $this->assertSame($before, $this->state());
            DB::table($table)->where('public_id', $public)->update(['deleted_at' => null]);
        }
        $before = $this->state();
        try {
            DB::table('products')->where('public_id', $base->productPublicId)->update(['product_type' => 'physical']);
            $this->fail('Nonlocal product type accepted');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
        $this->assertSame($before, $this->state());
        $this->mock(LocalDraftMerchantSource::class)->shouldReceive('lockOwnedDraft')->once()->andReturn(false);
        $this->app->forgetInstance(LocalDraftVariantChangeWriter::class);
        $before = $this->state();
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId), 'not_found');
        $this->assertSame($before, $this->state());
    }

    public function test_failures_after_every_write_and_both_authorization_rechecks_rollback_claims(): void
    {
        [$user,$base] = $this->fixture();
        $op = $this->variant($user, $base->catalogPublicId, $base->productPublicId);
        foreach (['product_variants', 'products', 'catalogs', 'catalog_local_draft_variant_changes'] as $table) {
            DB::unprepared("CREATE FUNCTION synthetic_variant_change_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Synthetic change failure' USING ERRCODE='23514'; END; $$; CREATE TRIGGER synthetic_variant_change_failure AFTER INSERT OR UPDATE ON ".$table.' FOR EACH ROW EXECUTE FUNCTION synthetic_variant_change_failure()');
            $before = $this->state();
            try {
                $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId);
                $this->fail('Expected rollback');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
            $this->assertSame($before, $this->state());
            DB::unprepared('DROP TRIGGER synthetic_variant_change_failure ON '.$table.'; DROP FUNCTION synthetic_variant_change_failure()');
        }
        foreach ([[$user->public_id, null], [$user->public_id, $user->public_id, null]] as $answers) {
            $this->mock(LocalDraftVariantChangeAccess::class)->shouldReceive('actor')->andReturn(...$answers);
            $this->app->forgetInstance(UpdateLocalDraftVariant::class);
            $this->app->forgetInstance(LocalDraftVariantChangeWriter::class);
            $before = $this->state();
            $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId), 'forbidden');
            $this->assertSame($before, $this->state());
        }
    }

    public function test_independent_permissions_revocation_block_and_journal_only_reads(): void
    {
        [$user,$base] = $this->fixture();
        $op = $this->variant($user, $base->catalogPublicId, $base->productPublicId);
        $read = DB::table('identity_permissions')->where('code', LocalDraftVariantChangeAccess::READ)->value('id');
        $update = DB::table('identity_permissions')->where('code', LocalDraftVariantChangeAccess::UPDATE)->value('id');
        DB::table('identity_permission_grants')->where('user_id', $user->getKey())->where('permission_id', $read)->delete();
        $changed = $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId);
        $before = $this->state();
        $this->assertSame($changed->publicId, $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId)->publicId);
        $this->rejected(fn () => app(ReadLocalDraftVariantChange::class)->execute((int) $user->getKey(), $changed->publicId), 'forbidden');
        DB::table('identity_permission_grants')->insert(['public_id' => (string) Str::ulid(), 'user_id' => $user->getKey(), 'permission_id' => $read, 'scope_type' => 'platform', 'scope_public_id' => LocalDraftVariantChangeAccess::SCOPE_PUBLIC_ID, 'effect' => 'allow']);
        DB::table('identity_permission_grants')->where('user_id', $user->getKey())->where('permission_id', $update)->delete();
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId), 'forbidden');
        DB::table('product_variants')->update(['deleted_at' => DB::raw('created_at')]);
        DB::table('products')->update(['deleted_at' => DB::raw('created_at')]);
        DB::table('catalogs')->update(['deleted_at' => DB::raw('created_at')]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertEquals($changed->snapshot(), app(LocalDraftVariantChangeStore::class)->find($changed->publicId, $user->public_id)->snapshot());
        $this->assertCount(1, DB::getQueryLog());
        $this->assertStringContainsString('from "catalog_local_draft_variant_changes"', DB::getQueryLog()[0]['query']);
        DB::disableQueryLog();
        $this->assertEquals($changed->snapshot(), app(ReadLocalDraftVariantChange::class)->execute((int) $user->getKey(), $changed->publicId)->snapshot());
        [$other] = $this->fixture();
        $this->rejected(fn () => app(ReadLocalDraftVariantChange::class)->execute((int) $other->getKey(), $changed->publicId), 'not_found');
        DB::table('identity_permission_grants')->insert(['public_id' => (string) Str::ulid(), 'user_id' => $user->getKey(), 'permission_id' => $update,
            'scope_type' => 'platform', 'scope_public_id' => LocalDraftVariantChangeAccess::SCOPE_PUBLIC_ID, 'effect' => 'allow']);
        $deletedState = $this->state();
        $this->assertEquals($changed->snapshot(), $this->edit($user, $base->catalogPublicId, $base->productPublicId, $op->variant->publicId)->snapshot());
        $this->assertSame($deletedState, $this->state());
        $user->update(['status' => 'blocked']);
        $this->rejected(fn () => app(ReadLocalDraftVariantChange::class)->execute((int) $user->getKey(), $changed->publicId), 'forbidden');
        $this->assertNotSame($before, $this->state());
    }

    public function test_current_v2_real_revisions_legacy_cursor_rejection_and_all_parent_invalidations(): void
    {
        [$user,$base] = $this->fixture();
        $first = $this->variant($user, $base->catalogPublicId, $base->productPublicId);
        $second = $this->variant($user, $base->catalogPublicId, $base->productPublicId, 2, 'OTHER');
        $additional = $this->add($user, $base->catalogPublicId, 3);
        $other = $this->variant($user, $base->catalogPublicId, $additional->product->publicId, 4, 'OTHER-PRODUCT');
        $variantPage = app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 1, null);
        $productPage = app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 1, null);
        $payload = json_decode(Crypt::decryptString($variantPage->nextCursor), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(2, $payload['schema_version']);
        $this->assertSame('catalog.local.draft_variants.page.v2', $payload['purpose']);
        $payload['schema_version'] = 1;
        $payload['purpose'] = 'catalog.local.draft_variants.page.v1';
        $legacy = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
        $this->rejected(fn () => app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 1, $legacy), 'validation_failed');
        $this->edit($user, $base->catalogPublicId, $additional->product->publicId, $other->variant->publicId, 5, 'edit-other-product');
        $before = $this->state();
        $this->rejected(fn () => app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 1, $variantPage->nextCursor), 'catalog_changed');
        $this->rejected(fn () => app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 1, $productPage->nextCursor), 'catalog_changed');
        $current = app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $additional->product->publicId, 50, null);
        $this->assertSame(2, $current->variants[0]->version);
        $this->assertSame(2, $current->variants[0]->snapshot()['schema_version']);
        $this->assertSame($before, $this->state());
        foreach (['edit-outside-page', 'append', 'product-change', 'variant-create'] as $mode) {
            $page = app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 1, null);
            $version = $this->version($base->catalogPublicId);
            if ($mode === 'edit-outside-page') {
                $this->edit($user, $base->catalogPublicId, $base->productPublicId, $second->variant->publicId, $version, $mode);
            } elseif ($mode === 'append') {
                $this->add($user, $base->catalogPublicId, $version, $mode);
            } elseif ($mode === 'product-change') {
                app(UpdateLocalDraftProduct::class)->execute((int) $user->getKey(), new DraftProductChangeInput($base->catalogPublicId, $base->productPublicId, $version, new DraftProductData('Changed Parent', null, null)), $mode, (string) Str::ulid());
            } else {
                $this->variant($user, $base->catalogPublicId, $base->productPublicId, $version, 'NEW-ONE', $mode);
            }
            $this->rejected(fn () => app(ListLocalDraftVariants::class)->execute((int) $user->getKey(), $base->catalogPublicId, $base->productPublicId, 1, $page->nextCursor), 'catalog_changed');
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $page = app(LocalDraftVariantStore::class)->page($base->catalogPublicId, $base->productPublicId, $user->public_id, 50, null);
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame([1, 2, 1], array_map(fn ($v) => $v->version, $page->variants));
        DB::disableQueryLog();
    }

    public function test_journal_constraints_three_revision_pairs_composed_fks_append_only_and_index(): void
    {
        [$user,$base] = $this->fixture();
        $created = $this->variant($user, $base->catalogPublicId, $base->productPublicId);
        $op = $this->edit($user, $base->catalogPublicId, $base->productPublicId, $created->variant->publicId);
        $this->edit($user, $base->catalogPublicId, $base->productPublicId, $created->variant->publicId, 3, 'next', new DraftVariantEditableData('Next', null, 'unit', null, []));
        $row = (array) DB::table('catalog_local_draft_variant_changes')->where('public_id', $op->publicId)->first();
        unset($row['id']);
        $candidate = array_replace($row, ['public_id' => (string) Str::ulid(), 'catalog_version_before' => 20, 'catalog_version_after' => 21,
            'product_version_before' => 20, 'product_version_after' => 21, 'variant_version_before' => 20, 'variant_version_after' => 21]);
        $reject = function (array $changes, string $state = '23514') use ($candidate): void {
            try {
                DB::table('catalog_local_draft_variant_changes')->insert(array_replace($candidate, $changes));
                $this->fail('Constraint missing');
            } catch (QueryException $e) {
                $this->assertSame($state, $e->errorInfo[0]);
            }
        };
        $before = $this->state();
        foreach (['public_id' => 'invalid', 'actor_public_id' => 'invalid', 'merchant_public_id' => 'invalid', 'correlation_id' => 'invalid', 'schema_version' => 2,
            'key_hash' => 'raw', 'request_hash' => str_repeat('A', 64), 'response_hash' => 'raw', 'response_snapshot_ciphertext' => ' '] as $field => $value) {
            $reject([$field => $value]);
        }
        foreach (['catalog', 'product', 'variant'] as $profile) {
            $reject([$profile.'_version_before' => 0]);
            $reject([$profile.'_version_before' => 2147483647, $profile.'_version_after' => 2147483647]);
            $reject([$profile.'_version_after' => 99]);
            $reject([$profile.'_version_before' => $row[$profile.'_version_before'], $profile.'_version_after' => $row[$profile.'_version_after']], '23505');
        }
        $reject(['public_id' => $op->publicId], '23505');
        [$other,$foreign] = $this->fixture();
        $foreignCreated = $this->variant($other, $foreign->catalogPublicId, $foreign->productPublicId);
        foreach ([
            ['catalog_id' => DB::table('catalogs')->where('public_id', $foreign->catalogPublicId)->value('id')],
            ['merchant_public_id' => $foreign->input->merchantPublicId],
            ['product_id' => DB::table('products')->where('public_id', $foreign->productPublicId)->value('id')],
            ['variant_id' => DB::table('product_variants')->where('public_id', $foreignCreated->variant->publicId)->value('id')],
        ] as $fields) {
            $reject($fields, '23503');
        }
        foreach ([fn () => DB::table('catalog_local_draft_variant_changes')->update(['schema_version' => 1]), fn () => DB::table('catalog_local_draft_variant_changes')->delete()] as $call) {
            try {
                $call();
                $this->fail('History changed');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
        try {
            (require database_path('migrations/'.self::MIGRATION))->down();
            $this->fail('Destructive down');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forward-only', $e->getMessage());
        }
        DB::transaction(function () use ($row): void {
            DB::statement('SET LOCAL enable_seqscan=off');
            $plan = DB::select('EXPLAIN SELECT * FROM catalog_local_draft_variant_changes WHERE public_id=? AND actor_public_id=?', [$row['public_id'], $row['actor_public_id']]);
            $this->assertStringContainsString('catalog_variant_changes_public_unique', json_encode($plan, JSON_THROW_ON_ERROR));
        });
        $columns = array_column(DB::select("SELECT column_name,data_type FROM information_schema.columns WHERE table_name='catalog_local_draft_variant_changes'"), 'data_type', 'column_name');
        $this->assertSame('timestamp with time zone', $columns['created_at']);
        $this->assertArrayNotHasKey('updated_at', $columns);
        $this->assertArrayNotHasKey('deleted_at', $columns);
        $this->assertSame(2, DB::table('catalog_local_draft_variant_changes')->count());
    }

    public function test_cipher_hash_closed_map_and_metadata_corruption_are_rejected_without_profile_reads(): void
    {
        [$user,$base] = $this->fixture();
        $created = $this->variant($user, $base->catalogPublicId, $base->productPublicId);
        $op = $this->edit($user, $base->catalogPublicId, $base->productPublicId, $created->variant->publicId);
        $row = (array) DB::table('catalog_local_draft_variant_changes')->where('public_id', $op->publicId)->first();
        DB::statement('ALTER TABLE catalog_local_draft_variant_changes DISABLE TRIGGER catalog_local_draft_variant_changes_append_only');
        try {
            $cases = [
                ['response_snapshot_ciphertext' => 'invalid'],
                ['response_hash' => str_repeat('a', 64)],
                ['catalog_version_before' => 9, 'catalog_version_after' => 10],
                ['product_version_before' => 9, 'product_version_after' => 10],
                ['variant_version_before' => 9, 'variant_version_after' => 10],
            ];
            $plain = Crypt::decryptString($row['response_snapshot_ciphertext']);
            $bad = json_decode($plain);
            $bad->variant->after->attributes = [];
            $badPlain = json_encode($bad, JSON_THROW_ON_ERROR);
            $cases[] = ['response_snapshot_ciphertext' => Crypt::encryptString($badPlain), 'response_hash' => hash('sha256', $badPlain)];
            $bad = json_decode($plain);
            $bad->variant->product_public_id = (string) Str::ulid();
            $badPlain = json_encode($bad, JSON_THROW_ON_ERROR);
            $cases[] = ['response_snapshot_ciphertext' => Crypt::encryptString($badPlain), 'response_hash' => hash('sha256', $badPlain)];
            foreach ($cases as $changes) {
                DB::table('catalog_local_draft_variant_changes')->where('public_id', $op->publicId)->update(array_replace($row, $changes));
                DB::enableQueryLog();
                DB::flushQueryLog();
                try {
                    app(LocalDraftVariantChangeStore::class)->find($op->publicId, $user->public_id);
                    $this->fail('Expected corrupted snapshot rejection');
                } catch (DecryptException|\LogicException|\InvalidArgumentException $e) {
                    $this->assertCount(1, DB::getQueryLog());
                    $this->assertStringContainsString('catalog_local_draft_variant_changes', DB::getQueryLog()[0]['query']);
                } finally {
                    DB::disableQueryLog();
                }
            }
        } finally {
            DB::table('catalog_local_draft_variant_changes')->where('public_id', $op->publicId)->update($row);
            DB::statement('ALTER TABLE catalog_local_draft_variant_changes ENABLE TRIGGER catalog_local_draft_variant_changes_append_only');
        }
    }

    public function test_real_process_races_cover_same_key_mismatch_versions_and_cross_mutations(): void
    {
        [$user,$base] = $this->fixture();
        $first = $this->variant($user, $base->catalogPublicId, $base->productPublicId);
        $second = $this->variant($user, $base->catalogPublicId, $base->productPublicId, 2, 'OTHER');
        $additional = $this->add($user, $base->catalogPublicId, 3);
        $third = $this->variant($user, $base->catalogPublicId, $additional->product->publicId, 4, 'THIRD');
        $race = function (array $requests): array {
            $workers = [];
            $results = [];
            foreach ($requests as $request) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/variant-change-race-worker.php')], base_path(), ['APP_ENV' => 'testing', 'TRAEPE_TEST_DATABASE' => $this->probeDatabase]);
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
        $request = function (string $catalog, string $product, string $variant, int $version, string $key, string $mode = 'variant_change') use ($user): array {
            return ['mode' => $mode, 'actor_id' => (int) $user->getKey(), 'catalog_public_id' => $catalog, 'product_public_id' => $product, 'variant_public_id' => $variant,
                'version' => $version, 'key' => $key, 'sku' => 'SKU-'.strtoupper($key), 'name' => 'Race '.$key, 'start' => microtime(true) + 1];
        };
        foreach (['replay', 'mismatch', 'distinct', 'different-variant', 'different-product', 'append', 'change', 'variant'] as $mode) {
            $version = $this->version($base->catalogPublicId);
            $before = DB::table('platform_idempotency_keys')->count();
            $a = $request($base->catalogPublicId, $base->productPublicId, $first->variant->publicId, $version, 'race-'.$mode.'-a');
            $b = $a;
            if ($mode === 'mismatch') {
                $b['name'] = 'Another input';
            } elseif ($mode !== 'replay') {
                $b = $request($base->catalogPublicId, $mode === 'different-product' ? $additional->product->publicId : $base->productPublicId,
                    $mode === 'different-variant' ? $second->variant->publicId : ($mode === 'different-product' ? $third->variant->publicId : $first->variant->publicId),
                    $version, 'race-'.$mode.'-b', in_array($mode, ['append', 'change', 'variant'], true) ? $mode : 'variant_change');
            }
            $b['start'] = $a['start'];
            $results = $race([$a, $b]);
            $codes = array_column($results, 'code');
            if ($mode === 'replay') {
                $this->assertSame(['variant_changed', 'variant_changed'], $codes);
                $this->assertSame($results[0]['operation'], $results[1]['operation']);
            } else {
                $this->assertCount(1, array_filter($codes, fn ($code) => $code === ($mode === 'mismatch' ? 'idempotency_mismatch' : 'version_conflict')));
                $this->assertCount(1, array_filter($codes, fn ($code) => in_array($code, ['variant_changed', 'created', 'added', 'changed'], true)));
            }
            $this->assertSame($before + 1, DB::table('platform_idempotency_keys')->count());
            $this->assertSame($version + 1, $this->version($base->catalogPublicId));
        }
        $other = $this->secondCatalog($user, $base->input->merchantPublicId);
        $otherVariant = $this->variant($user, $other->catalogPublicId, $other->productPublicId, 1, 'OTHER-CATALOG');
        $before = DB::table('platform_idempotency_keys')->count();
        $a = $request($base->catalogPublicId, $base->productPublicId, $first->variant->publicId, $this->version($base->catalogPublicId), 'separate-a');
        $b = $request($other->catalogPublicId, $other->productPublicId, $otherVariant->variant->publicId, 2, 'separate-b');
        $b['start'] = $a['start'];
        $this->assertSame(['variant_changed', 'variant_changed'], array_column($race([$a, $b]), 'code'));
        $this->assertSame($before + 2, DB::table('platform_idempotency_keys')->count());
    }

    public function test_additive_migration_preserves_37_prior_tables_and_second_run_is_noop(): void
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
            [$user,$base] = $this->fixture();
            $this->variant($user, $base->catalogPublicId, $base->productPublicId);
            $tables = array_column(DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename<>'migrations'"), 'tablename');
            $this->assertCount(37, $tables);
            $before = $this->snapshot($tables);
            $old = DB::table('migrations')->orderBy('id')->get()->toArray();
            $count = count($old);
            $this->assertFalse(Schema::hasTable('catalog_local_draft_variant_changes'));
            $defs = array_column(DB::select("SELECT conname,pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid='product_variants'::regclass"), 'definition', 'conname');
            $this->assertSame('CHECK ((version = 1))', $defs['product_variants_version_check']);
            DB::statement('ALTER TABLE product_variants DROP CONSTRAINT product_variants_version_check, ADD CONSTRAINT product_variants_version_check CHECK (version>=1)');
            try {
                DB::transaction(fn () => (require database_path('migrations/'.self::MIGRATION))->up());
                $this->fail('Unexpected migration baseline accepted');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
            $this->assertFalse(Schema::hasTable('catalog_local_draft_variant_changes'));
            $this->assertSame($before, $this->snapshot($tables));
            DB::statement('ALTER TABLE product_variants DROP CONSTRAINT product_variants_version_check, ADD CONSTRAINT product_variants_version_check CHECK (version=1)');
            $this->assertSame(0, Artisan::call('migrate', ['--path' => [database_path('migrations/'.self::MIGRATION)], '--realpath' => true, '--force' => true]));
            $this->assertSame($before, $this->snapshot($tables));
            $this->assertEquals($old, DB::table('migrations')->orderBy('id')->limit($count)->get()->toArray());
            $this->assertSame($count + 1, DB::table('migrations')->count());
            $this->assertSame(0, DB::table('catalog_local_draft_variant_changes')->count());
            $newDefs = array_column(DB::select("SELECT conname,pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid='product_variants'::regclass"), 'definition', 'conname');
            $this->assertSame('CHECK ((version >= 1))', $newDefs['product_variants_version_check']);
            unset($defs['product_variants_version_check'], $newDefs['product_variants_version_check']);
            $this->assertSame($defs, $newDefs);
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

    public function test_nonlocal_changes_deny_before_sql_callback_and_claim(): void
    {
        $store = app(LocalDraftVariantChangeStore::class);
        $writer = app(LocalDraftVariantChangeWriter::class);
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';
        $input = new DraftVariantChangeInput($id, $id, $id, 1, new DraftVariantEditableData('Test', null, 'unit', null, []));
        foreach (['production', 'staging'] as $env) {
            $this->app->detectEnvironment(fn () => $env);
            DB::enableQueryLog();
            DB::flushQueryLog();
            foreach ([fn () => $store->change($input, $id, str_repeat('a', 64), str_repeat('b', 64), $id), fn () => $store->find($id, $id),
                fn () => $writer->execute($input, $id, 'key', $id, function (): void {
                    $this->fail('Unexpected callback');
                })] as $call) {
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
