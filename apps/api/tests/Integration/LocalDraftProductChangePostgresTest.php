<?php

namespace Tests\Integration;

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftCatalog;
use App\Modules\Catalog\Application\Drafts\CreateLocalDraftProduct;
use App\Modules\Catalog\Application\Drafts\ListLocalDraftProducts;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftMerchantSource;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeWriter;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductStore;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftProductChange;
use App\Modules\Catalog\Application\Drafts\UpdateLocalDraftProduct;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeOperation;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Infrastructure\Drafts\PlatformLocalDraftProductChangeWriter;
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

final class LocalDraftProductChangePostgresTest extends PostgresTestCase
{
    private const MIGRATION = '2026_10_11_000009_create_local_draft_product_changes.php';

    private function fixture(): array
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => '', 'status' => 'active']);
        $user = IdentityUser::query()->findOrFail($id);
        foreach ([LocalDraftCommerceAccess::CREATE, LocalDraftCatalogAccess::CREATE, LocalDraftCatalogAccess::READ, LocalDraftProductAccess::CREATE, LocalDraftProductAccess::CONTENT, LocalDraftProductAccess::READ, LocalDraftProductChangeAccess::UPDATE, LocalDraftProductChangeAccess::READ] as $cap) {
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

    private function edit(IdentityUser $user, string $catalog, string $product, int $version = 1, string $key = 'synthetic-change-key', ?DraftProductData $data = null): DraftProductChangeOperation
    {
        return app(UpdateLocalDraftProduct::class)->execute((int) $user->getKey(), new DraftProductChangeInput($catalog, $product, $version, $data ?? new DraftProductData(' Synthetic Changed Canary ', '', null)), $key, (string) Str::ulid());
    }

    private function state(): array
    {
        return $this->snapshot(['catalogs', 'products', 'catalog_local_draft_operations', 'catalog_local_draft_product_operations', 'catalog_local_draft_product_changes', 'platform_idempotency_keys']);
    }

    public function test_initial_and_additional_edits_preserve_identity_original_histories_cipher_and_replay(): void
    {
        [$user,$base] = $this->fixture();
        $additional = $this->add($user, $base->catalogPublicId);
        $old = $this->snapshot(['merchants', 'branches', 'marketplace_local_commerce_operations', 'catalog_local_draft_operations', 'catalog_local_draft_product_operations']);
        $initialBefore = (array) DB::table('products')->where('public_id', $base->productPublicId)->first();
        $op = $this->edit($user, $base->catalogPublicId, $base->productPublicId, 2);
        $this->assertSame(3, DB::table('catalogs')->value('version'));
        $this->assertSame(2, DB::table('products')->where('public_id', $base->productPublicId)->value('version'));
        $initialAfter = (array) DB::table('products')->where('public_id', $base->productPublicId)->first();
        foreach (['id', 'public_id', 'catalog_id', 'product_type', 'status', 'deleted_at', 'created_at'] as $field) {
            $this->assertSame($initialBefore[$field], $initialAfter[$field]);
        }
        $row = DB::table('catalog_local_draft_product_changes')->first();
        $plain = Crypt::decryptString($row->response_snapshot_ciphertext);
        $this->assertSame($op->snapshot(), json_decode($plain, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(hash('sha256', $plain), $row->response_hash);
        $this->assertStringNotContainsString('Canary', $row->response_snapshot_ciphertext);
        $claim = DB::table('platform_idempotency_keys')->where('scope_hash', hash('sha256', PlatformLocalDraftProductChangeWriter::SCOPE))->first();
        $this->assertSame($op->snapshot(), $this->edit($user, $base->catalogPublicId, $base->productPublicId, 2)->snapshot());
        $this->assertSame($claim->expires_at, DB::table('platform_idempotency_keys')->where('id', $claim->id)->value('expires_at'));
        $other = $this->edit($user, $base->catalogPublicId, $additional->product->publicId, 3, 'second-change');
        $this->assertSame(4, DB::table('catalogs')->value('version'));
        $this->assertSame(2, DB::table('catalog_local_draft_product_changes')->count());
        $this->assertSame($old, $this->snapshot(array_keys($old)));
        $this->assertSame($base->snapshot(), app(LocalDraftCatalogStore::class)->find($base->publicId, $user->public_id)->snapshot());
        $this->assertSame($additional->snapshot(), app(LocalDraftProductStore::class)->find($additional->publicId, $user->public_id)->snapshot());
        DB::table('catalogs')->update(['name' => 'Changed', 'deleted_at' => DB::raw('created_at')]);
        DB::table('products')->update(['name' => 'Changed', 'deleted_at' => DB::raw('created_at')]);
        $this->assertSame($op->snapshot(), $this->edit($user, $base->catalogPublicId, $base->productPublicId, 2)->snapshot());
        $this->assertSame($other->snapshot(), app(ReadLocalDraftProductChange::class)->execute((int) $user->getKey(), $other->publicId)->snapshot());
    }

    public function test_no_changes_stale_and_maximum_revisions_and_mismatch_leave_no_partial_claims(): void
    {
        [$user,$base] = $this->fixture();
        $before = $this->state();
        $same = new DraftProductData('Synthetic Initial', null, null);
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, data: $same), 'no_changes');
        $this->assertSame($before, $this->state());
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, 2), 'version_conflict');
        $this->assertSame($before, $this->state());
        $op = $this->edit($user, $base->catalogPublicId, $base->productPublicId);
        $before = $this->state();
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, 2), 'idempotency_mismatch');
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, data: new DraftProductData('Other', null, null)), 'idempotency_mismatch');
        $this->assertSame($before, $this->state());
        DB::table('platform_idempotency_keys')->where('scope_hash', hash('sha256', PlatformLocalDraftProductChangeWriter::SCOPE))->update(['expires_at' => DB::raw("CURRENT_TIMESTAMP-INTERVAL '1 second'")]);
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId), 'idempotency_expired');
        foreach (['catalogs', 'products'] as $table) {
            DB::table($table)->update(['version' => 2147483647]);
            $before = $this->state();
            $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId, $table === 'catalogs' ? 2147483647 : 2, 'maximum-'.$table), 'version_conflict');
            $this->assertSame($before, $this->state());
            DB::table('catalogs')->update(['version' => 2]);
        }
        $this->assertSame('Synthetic Initial', $op->snapshot()['product']['before']['name']);
    }

    public function test_unknown_foreign_crossed_manual_deleted_resources_and_unavailable_merchant_are_indistinguishable(): void
    {
        [$user,$base] = $this->fixture();
        [$other,$foreign] = $this->fixture();
        $manual = (string) Str::ulid();
        DB::table('products')->insert(['public_id' => $manual, 'catalog_id' => DB::table('catalogs')->where('public_id', $base->catalogPublicId)->value('id'), 'name' => 'Manual']);
        foreach ([[$base->catalogPublicId, (string) Str::ulid()], [$foreign->catalogPublicId, $foreign->productPublicId], [$base->catalogPublicId, $foreign->productPublicId], [$base->catalogPublicId, $manual], [(string) Str::ulid(), $base->productPublicId]] as [$catalog,$product]) {
            $before = $this->state();
            $this->rejected(fn () => $this->edit($user, $catalog, $product), 'not_found');
            $this->assertSame($before, $this->state());
        }
        $this->rejected(fn () => app(ReadLocalDraftProductChange::class)->execute((int) $other->getKey(), (string) Str::ulid()), 'not_found');
        foreach (['products', 'catalogs', 'merchants'] as $table) {
            $value = $table === 'merchants' ? ['version' => 1] : ['deleted_at' => DB::raw('created_at')];
            if ($table === 'merchants') {
                $source = $this->mock(LocalDraftMerchantSource::class);
                $source->shouldReceive('lockOwnedDraft')->once()->andReturn(false);
                $this->app->forgetInstance(LocalDraftProductChangeWriter::class);
            } else {
                DB::table($table)->update($value);
            }
            $before = $this->state();
            $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId), 'not_found');
            $this->assertSame($before, $this->state());
            if ($table !== 'merchants') {
                DB::table($table)->update(['deleted_at' => null]);
            }
        }
    }

    public function test_failures_after_each_write_roll_back_all_fields_timestamps_journal_and_claim(): void
    {
        [$user,$base] = $this->fixture();
        foreach (['products', 'catalogs', 'catalog_local_draft_product_changes'] as $table) {
            DB::unprepared("CREATE FUNCTION synthetic_change_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Synthetic change failure' USING ERRCODE='23514'; END; $$; CREATE TRIGGER synthetic_change_failure AFTER INSERT OR UPDATE ON ".$table.' FOR EACH ROW EXECUTE FUNCTION synthetic_change_failure()');
            $before = $this->state();
            try {
                $this->edit($user, $base->catalogPublicId, $base->productPublicId);
                $this->fail('Expected rollback');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
            $this->assertSame($before, $this->state());
            DB::unprepared('DROP TRIGGER synthetic_change_failure ON '.$table.'; DROP FUNCTION synthetic_change_failure()');
        }
        $store = app(LocalDraftProductChangeStore::class);
        $this->mock(LocalDraftProductChangeAccess::class)->shouldReceive('actor')->andReturn($user->public_id, null);
        $before = $this->state();
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId), 'forbidden');
        $this->assertSame($before, $this->state());
        $this->assertInstanceOf(LocalDraftProductChangeStore::class, $store);
    }

    public function test_update_only_read_only_revocation_block_and_snapshot_read_without_profiles(): void
    {
        [$user,$base] = $this->fixture();
        $readPermission = DB::table('identity_permissions')->where('code', LocalDraftProductChangeAccess::READ)->value('id');
        $updatePermission = DB::table('identity_permissions')->where('code', LocalDraftProductChangeAccess::UPDATE)->value('id');
        DB::table('identity_permission_grants')->where('user_id', $user->getKey())->where('permission_id', $readPermission)->delete();
        $op = $this->edit($user, $base->catalogPublicId, $base->productPublicId);
        $this->assertSame($op->publicId, $this->edit($user, $base->catalogPublicId, $base->productPublicId)->publicId);
        $this->rejected(fn () => app(ReadLocalDraftProductChange::class)->execute((int) $user->getKey(), $op->publicId), 'forbidden');
        DB::table('identity_permission_grants')->insert(['public_id' => (string) Str::ulid(), 'user_id' => $user->getKey(), 'permission_id' => $readPermission, 'scope_type' => 'platform', 'scope_public_id' => LocalDraftProductChangeAccess::SCOPE_PUBLIC_ID, 'effect' => 'allow']);
        DB::table('identity_permission_grants')->where('user_id', $user->getKey())->where('permission_id', $updatePermission)->delete();
        $this->rejected(fn () => $this->edit($user, $base->catalogPublicId, $base->productPublicId), 'forbidden');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($op->snapshot(), app(LocalDraftProductChangeStore::class)->find($op->publicId, $user->public_id)->snapshot());
        $this->assertCount(1, DB::getQueryLog());
        foreach (['products', 'catalogs', 'merchants', 'users', 'platform_idempotency_keys'] as $private) {
            $this->assertStringNotContainsString('from "'.$private.'"', DB::getQueryLog()[0]['query']);
        }
        DB::disableQueryLog();
        $this->assertSame($op->snapshot(), app(ReadLocalDraftProductChange::class)->execute((int) $user->getKey(), $op->publicId)->snapshot());
        $user->update(['status' => 'blocked']);
        $this->rejected(fn () => app(ReadLocalDraftProductChange::class)->execute((int) $user->getKey(), $op->publicId), 'forbidden');
    }

    public function test_edit_invalidates_an_older_page_cursor_and_current_reads_remain_read_only(): void
    {
        [$user,$base] = $this->fixture();
        $additional = $this->add($user, $base->catalogPublicId);
        $page = app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 1, null);
        $this->assertNotNull($page->nextCursor);
        $this->edit($user, $base->catalogPublicId, $additional->product->publicId, 2);
        $before = $this->state();
        $this->rejected(fn () => app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 1, $page->nextCursor), 'catalog_changed');
        $current = app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 50, null);
        $this->assertSame(3, $current->catalogVersion);
        $this->assertSame(' Synthetic Changed Canary ', $current->products[1]->product->name);
        $this->assertSame(2, $current->products[1]->version);
        $this->assertSame($before, $this->state());
    }

    public function test_constraints_fk_unique_cipher_metadata_append_only_and_forward_only(): void
    {
        [$user,$base] = $this->fixture();
        $op = $this->edit($user, $base->catalogPublicId, $base->productPublicId);
        $this->edit($user, $base->catalogPublicId, $base->productPublicId, 2, 'next', new DraftProductData('Next', null, null));
        $row = (array) DB::table('catalog_local_draft_product_changes')->where('public_id', $op->publicId)->first();
        foreach (['public_id' => 'invalid', 'actor_public_id' => 'invalid', 'merchant_public_id' => 'invalid', 'schema_version' => 2, 'key_hash' => 'raw', 'response_hash' => str_repeat('A', 64), 'product_version_before' => 0, 'product_version_after' => 99, 'catalog_version_before' => 2147483647, 'response_snapshot_ciphertext' => ' '] as $field => $value) {
            try {
                DB::transaction(fn () => DB::table('catalog_local_draft_product_changes')->insert(array_replace($row, [$field => $value])));
                $this->fail('Constraint missing');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
        foreach ([fn () => DB::table('catalog_local_draft_product_changes')->update(['schema_version' => 1]), fn () => DB::table('catalog_local_draft_product_changes')->delete()] as $call) {
            try {
                DB::transaction($call);
                $this->fail('History changed');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
        unset($row['id']);
        $candidate = array_replace($row, ['public_id' => (string) Str::ulid(), 'catalog_version_before' => 3, 'catalog_version_after' => 4, 'product_version_before' => 3, 'product_version_after' => 4]);
        foreach ([['catalog_version_before' => 1, 'catalog_version_after' => 2], ['product_version_before' => 1, 'product_version_after' => 2], ['public_id' => $row['public_id']]] as $fields) {
            try {
                DB::transaction(fn () => DB::table('catalog_local_draft_product_changes')->insert(array_replace($candidate, $fields)));
                $this->fail('Duplicate revision accepted');
            } catch (QueryException $e) {
                $this->assertSame('23505', $e->errorInfo[0]);
            }
        }
        [$other,$otherBase] = $this->fixture();
        $otherProduct = DB::table('products')->where('public_id', $otherBase->productPublicId)->value('id');
        foreach ([['product_id' => $otherProduct], ['merchant_public_id' => $otherBase->input->merchantPublicId], ['catalog_id' => 0]] as $fields) {
            try {
                DB::transaction(fn () => DB::table('catalog_local_draft_product_changes')->insert(array_replace($candidate, $fields)));
                $this->fail('Cross relation accepted');
            } catch (QueryException $e) {
                $this->assertSame('23503', $e->errorInfo[0]);
            }
        }
        foreach (['cipher', 'hash', 'shape', 'metadata'] as $i => $kind) {
            $snapshot = $op->snapshot();
            $snapshot['catalog']['version_before'] = 10 + $i;
            $snapshot['catalog']['version_after'] = 11 + $i;
            $snapshot['product']['before']['version'] = 10 + $i;
            $snapshot['product']['after']['version'] = 11 + $i;
            if ($kind === 'shape') {
                $snapshot['product']['after']['unexpected'] = true;
            }
            $plain = json_encode($snapshot, JSON_THROW_ON_ERROR);
            $copy = array_replace($row, ['public_id' => (string) Str::ulid(), 'catalog_version_before' => 10 + $i, 'catalog_version_after' => 11 + $i, 'product_version_before' => 10 + $i, 'product_version_after' => 11 + $i,
                'response_snapshot_ciphertext' => $kind === 'cipher' ? 'broken' : Crypt::encryptString($plain), 'response_hash' => $kind === 'hash' ? str_repeat('a', 64) : hash('sha256', $plain)]);
            if ($kind === 'metadata') {
                $copy['product_version_before'] = 20 + $i;
                $copy['product_version_after'] = 21 + $i;
            }
            DB::table('catalog_local_draft_product_changes')->insert($copy);
            try {
                app(LocalDraftProductChangeStore::class)->find($copy['public_id'], $user->public_id);
                $this->fail('Corrupt snapshot restored');
            } catch (DecryptException|\LogicException|\InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
        $before = $this->state();
        try {
            (require database_path('migrations/'.self::MIGRATION))->down();
            $this->fail('Destructive down');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forward-only', $e->getMessage());
        }
        $this->assertSame($before, $this->state());
        DB::transaction(function () use ($row): void {
            DB::statement('SET LOCAL enable_seqscan=off');
            $plan = DB::select('EXPLAIN SELECT * FROM catalog_local_draft_product_changes WHERE public_id=? AND actor_public_id=?', [$row['public_id'], $row['actor_public_id']]);
            $this->assertStringContainsString('catalog_product_changes_public_unique', json_encode($plan, JSON_THROW_ON_ERROR));
        });
        $columns = array_column(DB::select("SELECT column_name,data_type FROM information_schema.columns WHERE table_name='catalog_local_draft_product_changes'"), 'data_type', 'column_name');
        $this->assertSame('timestamp with time zone', $columns['created_at']);
        $this->assertArrayNotHasKey('updated_at', $columns);
        $this->assertArrayNotHasKey('deleted_at', $columns);
    }

    public function test_nonlocal_persistence_and_writer_deny_before_queries_or_callbacks(): void
    {
        $store = app(LocalDraftProductChangeStore::class);
        $writer = app(LocalDraftProductChangeWriter::class);
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';
        $input = new DraftProductChangeInput($id, $id, 1, new DraftProductData('Changed', null, null));
        foreach (['production', 'staging'] as $env) {
            $this->app->detectEnvironment(fn () => $env);
            DB::enableQueryLog();
            DB::flushQueryLog();
            foreach ([fn () => $store->find($id, $id), fn () => $store->change($input, $id, 'bad', 'bad', $id),
                fn () => $writer->execute($input, $id, 'key', $id, fn () => $this->fail('Nonlocal callback'))] as $call) {
                try {
                    $call();
                    $this->fail('Nonlocal operation accepted');
                } catch (HttpException $e) {
                    $this->assertSame(404, $e->getStatusCode());
                }
            }
            $this->assertSame([], DB::getQueryLog());
            DB::disableQueryLog();
        }
    }

    public function test_real_races_same_key_distinct_keys_products_and_append_share_catalog_revision(): void
    {
        [$user,$base] = $this->fixture();
        $race = function (array $requests): array {
            $workers = [];
            foreach ($requests as $request) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/'.($request['mode'] === 'append' ? 'product-race-worker.php' : 'product-change-race-worker.php'))], base_path(), ['TRAEPE_TEST_DATABASE' => config('database.connections.pgsql.database')]);
                $worker->setInput(json_encode($request, JSON_THROW_ON_ERROR))->setTimeout(30)->start();
                $workers[] = $worker;
            }
            $results = [];
            foreach ($workers as $worker) {
                $this->assertSame(0, $worker->wait());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        };
        foreach ([true, false] as $same) {
            $version = DB::table('catalogs')->value('version');
            $start = microtime(true) + 1;
            $requests = [];
            for ($i = 0; $i < 2; $i++) {
                $requests[] = ['mode' => 'change', 'actor_id' => (int) $user->getKey(), 'catalog_public_id' => $base->catalogPublicId, 'product_public_id' => $base->productPublicId, 'version' => $version, 'key' => 'race-'.$version.'-'.($same ? 'same' : $i), 'name' => 'Race '.$version, 'start' => $start];
            }
            $results = $race($requests);
            $codes = array_column($results, 'code');
            sort($codes);
            if ($same) {
                $this->assertSame(['changed', 'changed'], $codes);
                $this->assertSame($results[0]['operation'], $results[1]['operation']);
            } else {
                $this->assertSame(['changed', 'version_conflict'], $codes);
            }
        }
        $additional = $this->add($user, $base->catalogPublicId, 3, 'race-additional');
        foreach ([false, true] as $withAppend) {
            $version = DB::table('catalogs')->value('version');
            $start = microtime(true) + 1;
            $requests = [
                ['mode' => 'change', 'actor_id' => (int) $user->getKey(), 'catalog_public_id' => $base->catalogPublicId, 'product_public_id' => $base->productPublicId, 'version' => $version, 'key' => 'mixed-'.$version.'-a', 'name' => 'Mixed '.$version, 'start' => $start],
                ['mode' => $withAppend ? 'append' : 'change', 'actor_id' => (int) $user->getKey(), 'catalog_public_id' => $base->catalogPublicId, 'product_public_id' => $additional->product->publicId, 'version' => $version, 'key' => 'mixed-'.$version.'-b', 'name' => 'Other '.$version, 'start' => $start]];
            $results = $race($requests);
            $codes = array_column($results, 'code');
            $this->assertCount(1, array_filter($codes, fn ($code) => $code === 'version_conflict'));
            $this->assertCount(1, array_filter($codes, fn ($code) => in_array($code, ['changed', 'created'], true)));
        }
        $this->assertSame(6, DB::table('catalogs')->value('version'));
        $this->assertSame(7, DB::table('platform_idempotency_keys')->count());
    }

    public function test_additive_migration_preserves_all_legacy_data_and_second_run_is_noop(): void
    {
        $legacy = 'traepe_00e_test_'.bin2hex(random_bytes(8));
        config(['database.connections.pgsql.database' => 'postgres']);
        DB::purge('pgsql');
        DB::statement('CREATE DATABASE "'.$legacy.'"');
        try {
            config(['database.connections.pgsql.database' => $legacy]);
            DB::purge('pgsql');
            $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < self::MIGRATION));
            $this->assertSame(0, Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]));
            $this->fixture();
            $tables = array_column(DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename<>'migrations'"), 'tablename');
            $before = $this->snapshot($tables);
            $migrationRows = DB::table('migrations')->orderBy('id')->get()->toArray();
            $migrations = count($migrationRows);
            $this->assertFalse(Schema::hasTable('catalog_local_draft_product_changes'));
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
            $this->assertSame($before, $this->snapshot($tables));
            $this->assertEquals($migrationRows, DB::table('migrations')->orderBy('id')->limit($migrations)->get()->toArray());
            $this->assertSame($migrations + 1, DB::table('migrations')->count());
            $this->assertSame(0, DB::table('catalog_local_draft_product_changes')->count());
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
            $this->assertStringContainsString('Nothing to migrate', Artisan::output());
            $this->assertSame($migrations + 1, DB::table('migrations')->count());
            $this->assertSame($before, $this->snapshot($tables));
        } finally {
            config(['database.connections.pgsql.database' => 'postgres']);
            DB::purge('pgsql');
            DB::statement('DROP DATABASE "'.$legacy.'" WITH (FORCE)');
            config(['database.connections.pgsql.database' => $this->probeDatabase]);
            DB::purge('pgsql');
        }
    }
}
