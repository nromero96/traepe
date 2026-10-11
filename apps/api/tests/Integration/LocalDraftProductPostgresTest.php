<?php

namespace Tests\Integration;

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftCatalog;
use App\Modules\Catalog\Application\Drafts\CreateLocalDraftProduct;
use App\Modules\Catalog\Application\Drafts\ListLocalDraftProducts;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductCursorCodec;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductWriter;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftCatalogProfile;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftProduct;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductCursor;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Infrastructure\Drafts\PlatformLocalDraftProductWriter;
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

final class LocalDraftProductPostgresTest extends PostgresTestCase
{
    private const MIGRATION = '2026_10_10_000008_create_local_draft_product_operations.php';

    private function fixture(): array
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => '', 'status' => 'active']);
        $user = IdentityUser::query()->findOrFail($id);
        foreach ([LocalDraftCommerceAccess::CREATE, LocalDraftCatalogAccess::CREATE, LocalDraftCatalogAccess::READ, LocalDraftProductAccess::CREATE, LocalDraftProductAccess::CONTENT, LocalDraftProductAccess::READ] as $cap) {
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

    public function test_append_version_ciphertext_replay_and_both_histories_are_original(): void
    {
        [$user,$base] = $this->fixture();
        $old = $this->snapshot(['merchants', 'branches', 'marketplace_local_commerce_operations', 'catalog_local_draft_operations']);
        $created = $this->add($user, $base->catalogPublicId);
        $this->assertSame(2, DB::table('catalogs')->value('version'));
        $this->assertSame(2, DB::table('products')->count());
        $this->assertSame(1, DB::table('catalog_local_draft_product_operations')->count());
        $row = DB::table('catalog_local_draft_product_operations')->first();
        $plain = Crypt::decryptString($row->response_snapshot_ciphertext);
        $this->assertSame($created->snapshot(), json_decode($plain, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(hash('sha256', $plain), $row->response_hash);
        $this->assertStringNotContainsString('Canary', $row->response_snapshot_ciphertext);
        $claim = DB::table('platform_idempotency_keys')->where('scope_hash', hash('sha256', PlatformLocalDraftProductWriter::SCOPE))->first();
        $expires = $claim->expires_at;
        $this->assertSame($created->snapshot(), $this->add($user, $base->catalogPublicId)->snapshot());
        $this->assertSame($expires, DB::table('platform_idempotency_keys')->where('id', $claim->id)->value('expires_at'));
        $this->assertSame($base->snapshot(), app(LocalDraftCatalogStore::class)->find($base->publicId, $user->public_id)->snapshot());
        DB::table('catalogs')->update(['name' => 'Changed', 'deleted_at' => DB::raw('created_at')]);
        DB::table('products')->update(['name' => 'Changed', 'deleted_at' => DB::raw('created_at')]);
        $this->assertSame($created->snapshot(), app(ReadLocalDraftProduct::class)->execute((int) $user->getKey(), $created->publicId)->snapshot());
        $this->assertSame($created->snapshot(), $this->add($user, $base->catalogPublicId)->snapshot());
        $this->assertSame($old, $this->snapshot(array_keys($old)));
        $this->rejected(fn () => app(ReadLocalDraftCatalogProfile::class)->execute((int) $user->getKey(), $base->catalogPublicId), 'not_found');
    }

    public function test_current_reads_use_only_catalog_in_one_statement_and_paginate_without_duplicates(): void
    {
        [$user,$base] = $this->fixture();
        for ($n = 1; $n <= 51; $n++) {
            $this->add($user, $base->catalogPublicId, $n, 'key-'.$n);
        }
        $before = $this->snapshot(['catalogs', 'products', 'catalog_local_draft_product_operations', 'catalog_local_draft_operations', 'platform_idempotency_keys']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $profile = app(LocalDraftProductStore::class)->profile($base->catalogPublicId, $user->public_id);
        $this->assertSame(52, $profile->version);
        $this->assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();
        $page = app(LocalDraftProductStore::class)->page($base->catalogPublicId, $user->public_id, 50, null);
        $query = DB::getQueryLog();
        $this->assertCount(1, $query);
        $this->assertStringContainsString('LATERAL', $query[0]['query']);
        foreach (['merchants', 'users', 'platform_idempotency_keys'] as $private) {
            $this->assertStringNotContainsString($private, $query[0]['query']);
        }
        DB::disableQueryLog();
        $this->assertCount(50, $page->products);
        $this->assertTrue($page->hasMore);
        $first = app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 20, null);
        $ids = [];
        $token = null;
        $expires = null;
        do {
            $page = app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 20, $token);
            foreach ($page->products as $product) {
                $ids[] = $product->publicId;
            } $token = $page->nextCursor;
            if ($token !== null) {
                $cursor = app(LocalDraftProductCursorCodec::class)->decode($token);
                $expires ??= $cursor->expiresAt;
                $this->assertSame($expires, $cursor->expiresAt);
                $this->assertStringNotContainsString($user->public_id, $token);
            }
        } while ($token !== null);
        $this->assertSame(DB::table('products')->orderBy('id')->pluck('public_id')->all(), $ids);
        $this->assertSame(52, count(array_unique($ids)));
        $this->assertSame($before, $this->snapshot(array_keys($before)));
        $this->add($user, $base->catalogPublicId, 52, 'invalidate');
        $this->rejected(fn () => app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 20, $first->nextCursor), 'catalog_changed');
    }

    public function test_empty_pages_soft_deleted_products_and_cursor_context_tamper_expiry(): void
    {
        [$user,$base] = $this->fixture();
        $this->add($user, $base->catalogPublicId);
        $page = app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 1, null);
        $token = $page->nextCursor;
        $this->assertNotNull($token);
        $codec = app(LocalDraftProductCursorCodec::class);
        $cursor = $codec->decode($token);
        foreach (['bad', $token.'/', substr($token, 0, -8).'aaaaaaaa'] as $bad) {
            $this->rejected(fn () => $codec->decode($bad), 'validation_failed');
        }
        $this->rejected(fn () => app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 2, $token), 'validation_failed');
        [$other,$otherBase] = $this->fixture();
        $this->rejected(fn () => app(ListLocalDraftProducts::class)->execute((int) $other->getKey(), $base->catalogPublicId, 1, $token), 'validation_failed');
        $this->rejected(fn () => app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $otherBase->catalogPublicId, 1, $token), 'validation_failed');
        $expired = $codec->encode(new DraftProductCursor($user->public_id, $base->catalogPublicId, 2, 1, $cursor->lastId, time() - 1));
        $this->rejected(fn () => app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 1, $expired), 'cursor_expired');
        DB::table('products')->where('catalog_id', DB::table('catalogs')->where('public_id', $base->catalogPublicId)->value('id'))->update(['deleted_at' => DB::raw('created_at')]);
        $empty = app(ListLocalDraftProducts::class)->execute((int) $user->getKey(), $base->catalogPublicId, 20, null);
        $this->assertSame([], $empty->products);
        $this->assertFalse($empty->hasMore);
        $this->assertNull($empty->nextCursor);
    }

    public function test_ownership_manual_catalog_version_mismatch_expiry_and_maximum(): void
    {
        [$user,$base] = $this->fixture();
        [$other] = $this->fixture();
        $tables = ['catalogs', 'products', 'catalog_local_draft_product_operations', 'platform_idempotency_keys'];
        $before = $this->snapshot($tables);
        foreach ([$base->catalogPublicId, (string) Str::ulid()] as $id) {
            $this->rejected(fn () => app(LocalDraftProductStore::class)->profile($id, $other->public_id), 'not_found');
            $this->rejected(fn () => $this->add($other, $id, 1, 'foreign-'.$id), 'not_found');
        }
        $manual = (string) Str::ulid();
        DB::table('catalogs')->insert(['public_id' => $manual, 'merchant_public_id' => $base->input->merchantPublicId, 'name' => 'Synthetic Manual']);
        $this->rejected(fn () => $this->add($user, $manual, 1, 'manual'), 'not_found');
        $this->rejected(fn () => $this->add($user, $base->catalogPublicId, 2, 'stale'), 'version_conflict');
        $created = $this->add($user, $base->catalogPublicId);
        $this->rejected(fn () => $this->add($user, $base->catalogPublicId, 2), 'idempotency_mismatch');
        $this->rejected(fn () => app(ReadLocalDraftProduct::class)->execute((int) $other->getKey(), $created->publicId), 'not_found');
        DB::table('platform_idempotency_keys')->where('scope_hash', hash('sha256', PlatformLocalDraftProductWriter::SCOPE))->update(['expires_at' => now()->subDay()]);
        $this->rejected(fn () => $this->add($user, $base->catalogPublicId), 'idempotency_expired');
        DB::table('catalogs')->where('public_id', $base->catalogPublicId)->update(['version' => 2147483647]);
        $this->rejected(fn () => $this->add($user, $base->catalogPublicId, 2147483647, 'maximum'), 'version_conflict');
        $this->assertSame(1, DB::table('catalog_local_draft_product_operations')->count());
    }

    public function test_rollback_after_product_revision_and_journal_and_reauthorization(): void
    {
        [$user,$base] = $this->fixture();
        $tables = ['catalogs', 'products', 'catalog_local_draft_product_operations', 'platform_idempotency_keys'];
        $before = $this->snapshot($tables);
        foreach (['products' => "name <> ' Synthetic Additional Canary '", 'catalogs' => 'version=1', 'catalog_local_draft_product_operations' => 'schema_version<>1'] as $table => $check) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT product_forced_failure CHECK ({$check})");
            try {
                $this->add($user, $base->catalogPublicId, 1, 'fail-'.$table);
                $this->fail('Expected DB failure');
            } catch (QueryException) {
                $this->assertSame($before, $this->snapshot($tables));
            } finally {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT product_forced_failure");
            }
        }
        $this->rejected(fn () => app(LocalDraftProductWriter::class)->execute($this->input($base->catalogPublicId), $user->public_id, 'revoked', (string) Str::ulid(), fn () => throw new DraftCatalogFailure('forbidden')), 'forbidden');
        $this->assertSame($before, $this->snapshot($tables));
        DB::table('identity_permission_grants')->where('user_id', $user->getKey())->delete();
        $this->rejected(fn () => $this->add($user, $base->catalogPublicId), 'forbidden');
    }

    public function test_constraints_append_only_types_indexes_and_forward_only_migration(): void
    {
        [$user,$base] = $this->fixture();
        $this->add($user, $base->catalogPublicId);
        $row = (array) DB::table('catalog_local_draft_product_operations')->first();
        foreach (['public_id' => 'invalid', 'actor_public_id' => 'invalid', 'merchant_public_id' => 'invalid', 'correlation_id' => 'invalid', 'schema_version' => 2, 'key_hash' => 'raw', 'request_hash' => str_repeat('z', 64), 'response_hash' => str_repeat('A', 64), 'catalog_version_before' => 0, 'catalog_version_after' => 99, 'response_snapshot_ciphertext' => ' '] as $field => $value) {
            try {
                DB::transaction(fn () => DB::table('catalog_local_draft_product_operations')->insert(array_replace($row, [$field => $value])));
                $this->fail('Invalid journal accepted');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
        foreach ([fn () => DB::table('catalog_local_draft_product_operations')->update(['schema_version' => 1]), fn () => DB::table('catalog_local_draft_product_operations')->delete()] as $call) {
            try {
                DB::transaction($call);
                $this->fail('Mutable journal accepted');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
        $types = array_column(DB::select("SELECT column_name,data_type FROM information_schema.columns WHERE table_name='catalog_local_draft_product_operations'"), 'data_type', 'column_name');
        $this->assertSame('bigint', $types['catalog_id']);
        $this->assertSame('timestamp with time zone', $types['created_at']);
        $this->assertArrayNotHasKey('updated_at', $types);
        $this->assertNotEmpty(DB::select("SELECT indexdef FROM pg_indexes WHERE indexname='products_local_draft_page_index' AND indexdef LIKE '%WHERE%'"));
        $before = $this->snapshot(['catalogs', 'products', 'catalog_local_draft_operations', 'catalog_local_draft_product_operations']);
        try {
            (require database_path('migrations/'.self::MIGRATION))->down();
            $this->fail('Destructive rollback accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forward-only', $e->getMessage());
        }
        $this->assertSame($before, $this->snapshot(array_keys($before)));
    }

    public function test_journal_cross_resource_foreign_keys_and_revision_uniqueness(): void
    {
        [$user,$base] = $this->fixture();
        $this->add($user, $base->catalogPublicId);
        $row = (array) DB::table('catalog_local_draft_product_operations')->first();
        unset($row['id']);
        [$other,$otherBase] = $this->fixture();
        $otherCatalog = DB::table('catalogs')->where('public_id', $otherBase->catalogPublicId)->value('id');
        $freeProduct = DB::table('products')->insertGetId(['public_id' => (string) Str::ulid(), 'catalog_id' => $row['catalog_id'], 'name' => 'Synthetic Free']);
        $crossProduct = DB::table('products')->insertGetId(['public_id' => (string) Str::ulid(), 'catalog_id' => $otherCatalog, 'name' => 'Synthetic Other']);
        $valid = array_replace($row, ['public_id' => (string) Str::ulid(), 'product_id' => $freeProduct, 'catalog_version_before' => 2, 'catalog_version_after' => 3]);
        foreach ([array_replace($valid, ['merchant_public_id' => $otherBase->input->merchantPublicId]), array_replace($valid, ['product_id' => $crossProduct]), array_replace($valid, ['catalog_id' => $otherCatalog])] as $invalid) {
            try {
                DB::transaction(fn () => DB::table('catalog_local_draft_product_operations')->insert($invalid));
                $this->fail('Cross-resource journal accepted');
            } catch (QueryException $e) {
                $this->assertSame('23503', $e->errorInfo[0]);
            }
        }
        foreach ([array_replace($valid, ['catalog_version_before' => 1, 'catalog_version_after' => 2]), array_replace($valid, ['product_id' => $row['product_id']])] as $invalid) {
            try {
                DB::transaction(fn () => DB::table('catalog_local_draft_product_operations')->insert($invalid));
                $this->fail('Duplicate journal accepted');
            } catch (QueryException $e) {
                $this->assertSame('23505', $e->errorInfo[0]);
            }
        }
    }

    public function test_corrupt_snapshot_cipher_hash_closed_shape_and_revision_metadata_are_rejected(): void
    {
        [$user,$base] = $this->fixture();
        $created = $this->add($user, $base->catalogPublicId);
        $row = (array) DB::table('catalog_local_draft_product_operations')->first();
        unset($row['id']);
        foreach (['cipher', 'hash', 'shape', 'metadata'] as $n => $kind) {
            $productPublic = (string) Str::ulid();
            $productId = DB::table('products')->insertGetId(['public_id' => $productPublic, 'catalog_id' => $row['catalog_id'], 'name' => 'Synthetic Corruption']);
            $value = $created->snapshot();
            $value['catalog']['version_before'] = 10 + $n;
            $value['catalog']['version_after'] = 11 + $n;
            $value['product']['public_id'] = $productPublic;
            if ($kind === 'shape') {
                $value['product']['internal_id'] = 11;
            }
            $plain = json_encode($value, JSON_THROW_ON_ERROR);
            $public = (string) Str::ulid();
            DB::table('catalog_local_draft_product_operations')->insert(array_replace($row, ['public_id' => $public, 'product_id' => $productId, 'catalog_version_before' => 20 + $n, 'catalog_version_after' => 21 + $n,
                'response_hash' => $kind === 'hash' ? str_repeat('a', 64) : hash('sha256', $plain), 'response_snapshot_ciphertext' => $kind === 'cipher' ? 'invalid' : Crypt::encryptString($plain)]));
            try {
                app(LocalDraftProductStore::class)->find($public, $user->public_id);
                $this->fail('Corrupt snapshot accepted');
            } catch (DecryptException|\InvalidArgumentException|\LogicException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_two_processes_same_key_replay_and_distinct_keys_version_conflict(): void
    {
        [$user,$base] = $this->fixture();
        foreach ([['same', 'same'], ['distinct-a', 'distinct-b']] as $iteration => $keys) {
            $version = $iteration + 1;
            $processes = [];
            $start = microtime(true) + 1.5;
            foreach ($keys as $key) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/product-race-worker.php')], base_path(), ['TRAEPE_TEST_DATABASE' => $this->probeDatabase]);
                $process->setInput(json_encode(['actor_id' => $user->getKey(), 'catalog_public_id' => $base->catalogPublicId, 'version' => $version, 'key' => $key, 'start' => $start], JSON_THROW_ON_ERROR))->setTimeout(30)->start();
                $processes[] = $process;
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode());
                $results[] = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
            }
            if ($iteration === 0) {
                $this->assertSame($results[0], $results[1]);
                $this->assertTrue(Str::isUlid($results[0]['operation']));
            } else {
                $codes = array_column($results, 'code');
                sort($codes);
                $this->assertSame(['created', 'version_conflict'], $codes);
            }
        }
        $this->assertSame(3, DB::table('catalogs')->value('version'));
        $this->assertSame(3, DB::table('products')->count());
        $this->assertSame(2, DB::table('catalog_local_draft_product_operations')->count());
        $this->assertSame(2, DB::table('platform_idempotency_keys')->where('scope_hash', hash('sha256', PlatformLocalDraftProductWriter::SCOPE))->count());
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
            $this->assertFalse(Schema::hasTable('catalog_local_draft_product_operations'));
            $this->assertSame(0, Artisan::call('migrate', ['--path' => [database_path('migrations/'.self::MIGRATION)], '--realpath' => true, '--force' => true]));
            $this->assertSame($before, $this->snapshot($tables));
            $this->assertEquals($migrationRows, DB::table('migrations')->orderBy('id')->limit($migrations)->get()->toArray());
            $this->assertSame($migrations + 1, DB::table('migrations')->count());
            $this->assertSame(0, DB::table('catalog_local_draft_product_operations')->count());
            $this->assertSame(0, Artisan::call('migrate', ['--path' => [database_path('migrations/'.self::MIGRATION)], '--realpath' => true, '--force' => true]));
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

    public function test_nonlocal_adapters_guard_before_sql_or_decryption(): void
    {
        $store = app(LocalDraftProductStore::class);
        $codec = app(LocalDraftProductCursorCodec::class);
        foreach (['production', 'staging'] as $env) {
            $this->app->detectEnvironment(fn () => $env);
            DB::enableQueryLog();
            DB::flushQueryLog();
            foreach ([fn () => $store->profile('invalid', 'invalid'), fn () => $store->page('invalid', 'invalid', 20, null), fn () => $store->find('invalid', 'invalid'), fn () => $codec->decode('bad')] as $call) {
                try {
                    $call();
                    $this->fail('Nonlocal adapter accepted');
                } catch (HttpException $e) {
                    $this->assertSame(404, $e->getStatusCode());
                }
            }
            $this->assertSame([], DB::getQueryLog());
            DB::disableQueryLog();
        }
    }
}
