<?php

namespace Tests\Integration;

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftCatalog;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftMerchantSource;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftCatalog;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogOperation;
use App\Modules\Catalog\Infrastructure\Drafts\PlatformLocalDraftCatalogWriter;
use App\Modules\Identity\Infrastructure\IdentityUser;
use App\Modules\Marketplace\Application\Commerce\CreateLocalDraftCommerce;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceAccess;
use App\Modules\Marketplace\Application\Commerce\OwnedLocalDraftMerchantV1;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceInput;
use DateTimeImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

final class LocalDraftCatalogPostgresTest extends PostgresTestCase
{
    private const MIGRATION = '2026_10_10_000007_create_local_draft_catalog_foundation.php';

    private function actor(bool $grant = true): IdentityUser
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => '', 'status' => 'active']);
        $user = IdentityUser::query()->findOrFail($id);
        if ($grant) {
            $this->grant($user);
        }
        $this->grant($user, LocalDraftCommerceAccess::CREATE);

        return $user;
    }

    private function grant(IdentityUser $user, string $capability = LocalDraftCatalogAccess::CREATE, string $effect = 'allow', string $scopeType = 'platform', ?string $scopeId = null, ?string $expires = null): void
    {
        $permission = DB::table('identity_permissions')->where('code', $capability)->value('id') ?? DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => $capability]);
        DB::table('identity_permission_grants')->insert(['public_id' => (string) Str::ulid(), 'user_id' => $user->getKey(), 'permission_id' => $permission, 'scope_type' => $scopeType, 'scope_public_id' => $scopeId ?? LocalDraftCatalogAccess::SCOPE_PUBLIC_ID, 'effect' => $effect, 'expires_at' => $expires]);
    }

    private function merchant(IdentityUser $user): string
    {
        $country = DB::table('countries')->value('id') ?? DB::table('countries')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ']);
        $market = (string) Str::ulid();
        DB::table('markets')->insert(['public_id' => $market, 'country_id' => $country, 'name' => 'Synthetic Market', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']);
        $input = DraftCommerceInput::fromArray(['merchant' => ['legal_name' => 'Synthetic Legal', 'trade_name' => 'Synthetic Trade'], 'branch' => ['market_public_id' => $market, 'name' => 'Synthetic Branch', 'longitude' => 0.5, 'latitude' => 1.5, 'timezone' => 'Etc/UTC']]);

        return app(CreateLocalDraftCommerce::class)->execute((int) $user->getKey(), $input, (string) Str::ulid(), (string) Str::ulid())->merchant->value;
    }

    private function payload(string $merchant): array
    {
        return ['merchant_public_id' => $merchant, 'catalog' => ['name' => ' Synthetic Catalog Canary '], 'product' => ['name' => 'Synthetic Product Canary', 'description' => "Synthetic Description Canary\nliteral", 'brand' => 'Synthetic Brand Canary']];
    }

    private function create(IdentityUser $user, string $merchant, string $key = 'synthetic-catalog-key', ?array $payload = null): DraftCatalogOperation
    {
        return app(CreateLocalDraftCatalog::class)->execute((int) $user->getKey(), DraftCatalogInput::fromArray($payload ?? $this->payload($merchant)), $key, (string) Str::ulid());
    }

    private function counts(): array
    {
        return array_map(fn ($table) => DB::table($table)->count(), ['catalogs', 'products', 'catalog_local_draft_operations', 'platform_idempotency_keys']);
    }

    private function rejected(callable $call, string $code): void
    {
        try {
            $call();
            $this->fail('Expected catalog rejection.');
        } catch (DraftCatalogFailure $failure) {
            $this->assertSame($code, $failure->getMessage());
        }
    }

    private function constraint(callable $operation, string $state): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected integrity rejection.');
        } catch (QueryException $failure) {
            $this->assertSame($state, $failure->errorInfo[0]);
        }
    }

    private function snapshot(array $tables): array
    {
        $result = [];
        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->all();
            sort($rows);
            $result[$table] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        }

        return $result;
    }

    public function test_create_encrypted_original_snapshot_replay_and_independent_owned_read(): void
    {
        $user = $this->actor();
        $merchant = $this->merchant($user);
        $commerce = $this->snapshot(['merchants', 'branches', 'marketplace_local_commerce_operations']);
        $operation = $this->create($user, $merchant);
        $this->assertSame([1, 1, 1, 2], $this->counts());
        $row = DB::table('catalog_local_draft_operations')->first();
        $claim = DB::table('platform_idempotency_keys')->where('response_ref', $operation->publicId)->first();
        $plain = Crypt::decryptString($row->response_snapshot_ciphertext);
        $this->assertSame($operation->snapshot(), json_decode($plain, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(hash('sha256', $plain), $row->response_hash);
        $this->assertSame(hash('sha256', 'synthetic-catalog-key'), $row->key_hash);
        $this->assertSame($row->request_hash, $claim->request_hash);
        $this->assertSame(hash('sha256', PlatformLocalDraftCatalogWriter::SCOPE), $claim->scope_hash);
        $this->assertSame(hash('sha256', 'identity.user:'.$user->public_id), $claim->actor_hash);
        $ttl = strtotime($claim->expires_at) - strtotime($claim->created_at);
        $this->assertGreaterThanOrEqual(86399, $ttl);
        $this->assertLessThanOrEqual(86401, $ttl);
        $this->assertSame($row->catalog_id, DB::table('products')->value('catalog_id'));
        $this->assertNull(DB::table('products')->value('product_type'));
        foreach (['catalogs', 'products'] as $table) {
            $this->assertSame('draft', DB::table($table)->value('status'));
            $this->assertSame(1, DB::table($table)->value('version'));
            $this->assertNull(DB::table($table)->value('deleted_at'));
            DB::table($table)->update(['name' => 'Synthetic changed', 'deleted_at' => DB::raw('created_at')]);
        }
        foreach (['Synthetic Catalog Canary', 'Synthetic Product Canary', 'Synthetic Description Canary', 'Synthetic Brand Canary', 'synthetic-catalog-key'] as $canary) {
            $this->assertStringNotContainsString($canary, json_encode($row).json_encode($claim));
        }
        $before = $this->snapshot(['catalogs', 'products', 'catalog_local_draft_operations', 'platform_idempotency_keys']);
        $this->mock(LocalDraftMerchantSource::class)->shouldNotReceive('lockOwnedDraft');
        $this->assertEquals($operation, $this->create($user, $merchant));
        $this->rejected(fn () => app(ReadLocalDraftCatalog::class)->execute((int) $user->getKey(), $operation->publicId), 'forbidden');
        $this->grant($user, LocalDraftCatalogAccess::READ);
        $this->assertEquals($operation, app(ReadLocalDraftCatalog::class)->execute((int) $user->getKey(), $operation->publicId));
        $other = $this->actor(false);
        $this->grant($other, LocalDraftCatalogAccess::READ);
        foreach ([$operation->publicId, (string) Str::ulid()] as $id) {
            $this->rejected(fn () => app(ReadLocalDraftCatalog::class)->execute((int) $other->getKey(), $id), 'not_found');
        }
        $this->assertSame($before, $this->snapshot(array_keys($before)));
        $this->assertSame($commerce, $this->snapshot(array_keys($commerce)));
        $this->assertSame($claim->expires_at, DB::table('platform_idempotency_keys')->where('response_ref', $operation->publicId)->value('expires_at'));
    }

    public function test_owned_public_contract_requires_transaction_creator_journal_and_draft_state(): void
    {
        $user = $this->actor();
        $merchant = $this->merchant($user);
        $source = app(OwnedLocalDraftMerchantV1::class);
        try {
            $source->lockOwnedDraft($merchant, $user->public_id);
            $this->fail('Unlocked access accepted.');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
        $other = $this->actor();
        $manual = (string) Str::ulid();
        DB::table('merchants')->insert(['public_id' => $manual, 'legal_name' => 'Synthetic Manual', 'trade_name' => 'Synthetic Manual']);
        $before = $this->snapshot(['merchants', 'marketplace_local_commerce_operations']);
        DB::transaction(function () use ($source, $merchant, $manual, $user, $other) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->assertTrue($source->lockOwnedDraft($merchant, $user->public_id));
            $query = DB::getQueryLog()[0]['query'];
            DB::disableQueryLog();
            $this->assertStringStartsWith('select "m"."public_id"', $query);
            $this->assertStringContainsString('FOR UPDATE OF m', $query);
            foreach ([[$merchant, $other->public_id], [$manual, $user->public_id], [(string) Str::ulid(), $user->public_id], ['invalid', $user->public_id]] as [$id, $actor]) {
                $this->assertFalse($source->lockOwnedDraft($id, $actor));
            }
        });
        foreach ([$merchant, $manual, (string) Str::ulid()] as $id) {
            $this->rejected(fn () => $this->create($other, $id), 'merchant_not_available');
        }
        $this->assertSame([0, 0, 0, 1], $this->counts());
        $this->assertSame($before, $this->snapshot(array_keys($before)));
        $this->app->detectEnvironment(fn () => 'production');
        DB::transaction(function () use ($source, $merchant, $user) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $source->lockOwnedDraft($merchant, $user->public_id);
                $this->fail('Production access accepted.');
            } catch (HttpException $failure) {
                $this->assertSame(404, $failure->getStatusCode());
                $this->assertSame([], DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
        });
    }

    public function test_mismatch_expiry_actor_isolation_and_duplicate_names(): void
    {
        $user = $this->actor();
        $merchant = $this->merchant($user);
        $first = $this->create($user, $merchant);
        foreach (['name', 'description', 'brand'] as $field) {
            $body = $this->payload($merchant);
            $body['product'][$field] .= ' ';
            $this->rejected(fn () => $this->create($user, $merchant, payload: $body), 'idempotency_mismatch');
        }
        DB::table('platform_idempotency_keys')->where('response_ref', $first->publicId)->update(['expires_at' => new DateTimeImmutable('now')]);
        $this->rejected(fn () => $this->create($user, $merchant), 'idempotency_expired');
        $other = $this->actor();
        $otherMerchant = $this->merchant($other);
        $this->assertNotSame($first->publicId, $this->create($other, $otherMerchant)->publicId);
        $this->create($user, $merchant, 'another-key-same-names');
        $this->assertSame([3, 3, 3, 5], $this->counts());
    }

    public function test_product_journal_failure_and_callback_revocation_rollback_claim_and_catalog(): void
    {
        $user = $this->actor();
        $merchant = $this->merchant($user);
        foreach (['products' => "name <> 'Synthetic Product Canary'", 'catalog_local_draft_operations' => 'schema_version <> 1'] as $table => $check) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT synthetic_catalog_failure CHECK ({$check})");
            $this->constraint(fn () => $this->create($user, $merchant), '23514');
            $this->assertSame([0, 0, 0, 1], $this->counts());
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT synthetic_catalog_failure");
        }
        $access = $this->mock(LocalDraftCatalogAccess::class);
        $access->shouldReceive('actor')->once()->ordered()->andReturn($user->public_id);
        $access->shouldReceive('actor')->once()->ordered()->andReturn(null);
        $this->rejected(fn () => $this->create($user, $merchant), 'forbidden');
        $this->assertSame([0, 0, 0, 1], $this->counts());
    }

    public function test_real_permission_default_deny_scope_expiry_deny_block_and_independent_read(): void
    {
        $this->app->detectEnvironment(fn () => 'testing');
        $user = $this->actor(false);
        $merchant = $this->merchant($user);
        $url = '/api/v1/catalog/local-draft-catalogs';
        $this->actingAs($user, 'web')->withHeader('Idempotency-Key', 'policy-catalog-key');
        $this->grant($user, LocalDraftCatalogAccess::READ);
        $this->postJson($url, [])->assertForbidden();
        $this->grant($user, scopeType: 'merchant');
        $this->grant($user, scopeId: (string) Str::ulid());
        $this->grant($user, expires: (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP'));
        $this->postJson($url, $this->payload($merchant))->assertForbidden();
        $this->grant($user);
        $first = $this->postJson($url, $this->payload($merchant))->assertCreated();
        $operation = $first->json('data.id');
        $this->getJson($url.'/'.$operation)->assertOk()->assertJsonPath('data', $first->json('data'));
        $this->postJson($url, $this->payload($merchant))->assertCreated()->assertJsonPath('data', $first->json('data'));
        $this->assertSame($first->json('meta.correlation_id'), DB::table('catalog_local_draft_operations')->value('correlation_id'));
        $this->grant($user, effect: 'deny');
        $this->postJson($url, $this->payload($merchant))->assertForbidden();
        $this->getJson($url.'/'.$operation)->assertOk();
        $this->grant($user, LocalDraftCatalogAccess::READ, 'deny');
        $this->getJson($url.'/'.$operation)->assertForbidden();
        DB::table('identity_permission_grants')->where('effect', 'deny')->delete();
        DB::table('users')->where('id', $user->getKey())->update(['status' => 'blocked']);
        $this->postJson($url, $this->payload($merchant))->assertForbidden();
        $this->getJson($url.'/'.$operation)->assertForbidden();
        $this->assertSame([1, 1, 1, 2], $this->counts());
    }

    public function test_column_constraints_restrict_fk_composite_scope_and_append_only_journal(): void
    {
        $user = $this->actor();
        $merchant = $this->merchant($user);
        $this->create($user, $merchant);
        foreach (['catalogs', 'products'] as $table) {
            foreach (['public_id' => 'invalid', 'name' => ' ', 'status' => 'active', 'version' => 0, 'deleted_at' => '2000-01-01T00:00:00Z'] as $column => $value) {
                $this->constraint(fn () => DB::table($table)->update([$column => $value]), '23514');
            }
            foreach (["bad\nname", "bad\tname", "bad\x7fname"] as $value) {
                $this->constraint(fn () => DB::table($table)->update(['name' => $value]), '23514');
            }
        }
        foreach (['product_type' => 'unrestricted', 'description' => str_repeat('á', 4001), 'brand' => ' '] as $column => $value) {
            $this->constraint(fn () => DB::table('products')->update([$column => $value]), '23514');
        }
        foreach (["bad\ttext", "bad\rtext", "bad\x7ftext"] as $value) {
            $this->constraint(fn () => DB::table('products')->update(['description' => $value]), '23514');
        }
        DB::table('products')->update(['description' => str_repeat('á', 3999)."\n", 'brand' => null]);
        $row = (array) DB::table('catalog_local_draft_operations')->first();
        unset($row['id']);
        foreach (['public_id' => 'invalid', 'merchant_public_id' => 'invalid', 'actor_public_id' => 'invalid', 'correlation_id' => 'invalid', 'key_hash' => 'raw', 'request_hash' => str_repeat('z', 64), 'response_hash' => str_repeat('A', 64), 'schema_version' => 2, 'response_snapshot_ciphertext' => ' '] as $column => $value) {
            $this->constraint(fn () => DB::table('catalog_local_draft_operations')->insert(array_replace($row, [$column => $value])), '23514');
        }
        $this->constraint(fn () => DB::table('catalog_local_draft_operations')->update(['schema_version' => 1]), '23514');
        $this->constraint(fn () => DB::table('catalog_local_draft_operations')->delete(), '23514');
        $this->constraint(fn () => DB::table('merchants')->where('public_id', $merchant)->delete(), '23503');
        $this->constraint(fn () => DB::table('catalogs')->delete(), '23503');
        $this->constraint(fn () => DB::table('products')->delete(), '23503');
        $unusedCatalog = DB::table('catalogs')->insertGetId(['public_id' => (string) Str::ulid(), 'merchant_public_id' => $merchant, 'name' => 'Synthetic Unused']);
        $unusedProduct = DB::table('products')->insertGetId(['public_id' => (string) Str::ulid(), 'catalog_id' => $unusedCatalog, 'name' => 'Synthetic Unused']);
        $this->constraint(fn () => DB::table('catalog_local_draft_operations')->insert(array_replace($row, ['public_id' => (string) Str::ulid(), 'catalog_id' => $unusedCatalog, 'product_id' => $unusedProduct, 'merchant_public_id' => (string) Str::ulid()])), '23503');
        $otherCatalog = DB::table('catalogs')->insertGetId(['public_id' => (string) Str::ulid(), 'merchant_public_id' => $merchant, 'name' => 'Synthetic Other']);
        $otherProduct = DB::table('products')->insertGetId(['public_id' => (string) Str::ulid(), 'catalog_id' => $otherCatalog, 'name' => 'Synthetic Other']);
        $this->constraint(fn () => DB::table('catalog_local_draft_operations')->insert(array_replace($row, ['public_id' => (string) Str::ulid(), 'catalog_id' => $unusedCatalog, 'product_id' => $otherProduct])), '23503');
        $this->constraint(fn () => DB::table('catalog_local_draft_operations')->insert($row), '23505');
        $types = array_column(DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'catalog_local_draft_operations'"), 'data_type', 'column_name');
        $this->assertSame('bigint', $types['id']);
        $this->assertSame('timestamp with time zone', $types['created_at']);
        $this->assertArrayNotHasKey('updated_at', $types);
        $this->assertArrayNotHasKey('deleted_at', $types);
        $targets = array_column(DB::select("SELECT confrelid::regclass::text AS target FROM pg_constraint WHERE conrelid = 'catalog_local_draft_operations'::regclass AND contype = 'f'"), 'target');
        $this->assertNotContains('users', $targets);
        foreach (['catalogs', 'products', 'catalog_local_draft_operations'] as $table) {
            $indexes = implode(' ', array_column(DB::select('SELECT indexdef FROM pg_indexes WHERE tablename = ?', [$table]), 'indexdef'));
            $this->assertStringContainsString('(public_id)', $indexes);
        }
        $migration = require database_path('migrations/'.self::MIGRATION);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('forward-only');
        $migration->down();
    }

    public function test_corrupt_ciphertext_hash_and_closed_snapshot_are_never_restored(): void
    {
        $user = $this->actor();
        $merchant = $this->merchant($user);
        foreach (['cipher', 'hash', 'shape'] as $failure) {
            $catalog = DB::table('catalogs')->insertGetId(['public_id' => (string) Str::ulid(), 'merchant_public_id' => $merchant, 'name' => 'Synthetic']);
            $product = DB::table('products')->insertGetId(['public_id' => (string) Str::ulid(), 'catalog_id' => $catalog, 'name' => 'Synthetic']);
            $id = (string) Str::ulid();
            $plain = $failure === 'shape' ? '{"unexpected":true}' : '{}';
            DB::table('catalog_local_draft_operations')->insert(['public_id' => $id, 'catalog_id' => $catalog, 'product_id' => $product, 'merchant_public_id' => $merchant, 'actor_public_id' => $user->public_id, 'schema_version' => 1, 'key_hash' => str_repeat('a', 64), 'request_hash' => str_repeat('b', 64), 'response_hash' => $failure === 'hash' ? str_repeat('c', 64) : hash('sha256', $plain), 'correlation_id' => (string) Str::ulid(), 'response_snapshot_ciphertext' => $failure === 'cipher' ? 'invalid-ciphertext' : Crypt::encryptString($plain)]);
            try {
                app(LocalDraftCatalogStore::class)->find($id, $user->public_id);
                $this->fail('Corrupt snapshot accepted.');
            } catch (DecryptException|\LogicException|\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_independent_processes_serialize_same_key_and_allow_distinct_keys(): void
    {
        $user = $this->actor();
        $merchant = $this->merchant($user);
        foreach ([['same-key', 'same-key'], ['distinct-a', 'distinct-b']] as $keys) {
            $processes = [];
            foreach ($keys as $key) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/catalog-race-worker.php')], base_path(), ['TRAEPE_TEST_DATABASE' => $this->probeDatabase]);
                $process->setInput(json_encode(['actor_id' => $user->getKey(), 'key' => $key, 'payload' => $this->payload($merchant)], JSON_THROW_ON_ERROR))->setTimeout(30)->start();
                $processes[] = $process;
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode());
                $results[] = trim($process->getOutput());
                $this->assertTrue(Str::isUlid(end($results)));
            }
            $this->assertSame($keys[0] === $keys[1], $results[0] === $results[1]);
        }
        $this->assertSame([3, 3, 3, 4], $this->counts());
    }

    public function test_additive_installation_preserves_all_legacy_rows_and_second_migration_is_noop(): void
    {
        $legacy = 'traepe_00e_test_'.bin2hex(random_bytes(8));
        $this->assertTrue($legacy !== $this->probeDatabase && preg_match('/^traepe_00e_test_[a-f0-9]{16}$/D', $legacy) === 1);
        config(['database.connections.pgsql.database' => 'postgres']);
        DB::purge('pgsql');
        DB::statement('CREATE DATABASE "'.$legacy.'"');
        try {
            config(['database.connections.pgsql.database' => $legacy]);
            DB::purge('pgsql');
            $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== self::MIGRATION));
            $this->assertSame(0, Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]));
            $this->assertFalse(Schema::hasTable('catalogs'));
            $this->merchant($this->actor());
            $tables = array_column(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'migrations'"), 'tablename');
            $before = $this->snapshot($tables);
            $migrations = DB::table('migrations')->count();
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
            $this->assertSame($before, $this->snapshot($tables));
            $this->assertSame($migrations + 1, DB::table('migrations')->count());
            $this->assertSame([0, 0, 0, 1], $this->counts());
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
