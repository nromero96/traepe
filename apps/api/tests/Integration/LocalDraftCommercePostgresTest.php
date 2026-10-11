<?php

namespace Tests\Integration;

use App\Modules\Identity\Infrastructure\IdentityUser;
use App\Modules\Marketplace\Application\Commerce\CreateLocalDraftCommerce;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceAccess;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceStore;
use App\Modules\Marketplace\Application\Commerce\ReadLocalDraftCommerce;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceFailure;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceInput;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceOperation;
use App\Modules\Marketplace\Infrastructure\Commerce\PlatformLocalDraftCommerceWriter;
use DateTimeImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class LocalDraftCommercePostgresTest extends PostgresTestCase
{
    private const MIGRATION = '2026_10_10_000006_create_marketplace_local_commerce_operations.php';

    private function actor(bool $grant = true): IdentityUser
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => '', 'status' => 'active']);
        $user = IdentityUser::query()->findOrFail($id);
        if ($grant) {
            $this->grant($user);
        }

        return $user;
    }

    private function grant(IdentityUser $user, string $capability = LocalDraftCommerceAccess::CREATE, string $effect = 'allow', string $scopeType = 'platform', ?string $scopeId = null, ?string $expires = null): void
    {
        $permission = DB::table('identity_permissions')->where('code', $capability)->value('id') ?? DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => $capability]);
        DB::table('identity_permission_grants')->insert(['public_id' => (string) Str::ulid(), 'user_id' => $user->getKey(), 'permission_id' => $permission, 'scope_type' => $scopeType, 'scope_public_id' => $scopeId ?? LocalDraftCommerceAccess::SCOPE_PUBLIC_ID, 'effect' => $effect, 'expires_at' => $expires]);
    }

    private function market(): string
    {
        $country = DB::table('countries')->value('id') ?? DB::table('countries')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ']);
        $publicId = (string) Str::ulid();
        DB::table('markets')->insert(['public_id' => $publicId, 'country_id' => $country, 'name' => 'Synthetic Market', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']);

        return $publicId;
    }

    private function payload(string $market): array
    {
        return ['merchant' => ['legal_name' => ' Synthetic Legal Canary ', 'trade_name' => 'Synthetic Trade Canary'], 'branch' => ['market_public_id' => $market, 'name' => 'Synthetic Branch Canary', 'longitude' => 0.5, 'latitude' => 1.5, 'timezone' => 'Etc/UTC']];
    }

    private function create(IdentityUser $user, string $market, string $key = 'synthetic-commerce-key', ?array $payload = null): DraftCommerceOperation
    {
        return app(CreateLocalDraftCommerce::class)->execute((int) $user->getKey(), DraftCommerceInput::fromArray($payload ?? $this->payload($market)), $key, (string) Str::ulid());
    }

    private function counts(): array
    {
        return array_map(fn ($table) => DB::table($table)->count(), ['countries', 'markets', 'merchants', 'branches', 'marketplace_local_commerce_operations', 'platform_idempotency_keys']);
    }

    private function rejected(callable $call, string $code): void
    {
        try {
            $call();
            $this->fail('Expected commerce rejection.');
        } catch (DraftCommerceFailure $failure) {
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

    public function test_create_encrypted_journal_replay_original_snapshot_ttl_and_independent_owned_read(): void
    {
        $user = $this->actor();
        $market = $this->market();
        $operation = $this->create($user, $market);
        $this->assertSame([1, 1, 1, 1, 1, 1], $this->counts());
        $row = DB::table('marketplace_local_commerce_operations')->first();
        $claim = DB::table('platform_idempotency_keys')->first();
        $plain = Crypt::decryptString($row->response_snapshot_ciphertext);
        $this->assertSame($operation->snapshot(), json_decode($plain, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(hash('sha256', $plain), $row->response_hash);
        $this->assertSame(hash('sha256', 'synthetic-commerce-key'), $row->key_hash);
        $this->assertSame($row->request_hash, $claim->request_hash);
        $this->assertSame(hash('sha256', PlatformLocalDraftCommerceWriter::SCOPE), $claim->scope_hash);
        $this->assertSame(hash('sha256', 'identity.user:'.$user->public_id), $claim->actor_hash);
        $this->assertSame($operation->publicId, $claim->response_ref);
        $ttl = strtotime($claim->expires_at) - strtotime($claim->created_at);
        $this->assertGreaterThanOrEqual(86399, $ttl);
        $this->assertLessThanOrEqual(86401, $ttl);
        $branch = DB::table('branches')->first();
        $this->assertSame($row->merchant_id, $branch->merchant_id);
        $this->assertSame($row->market_id, $branch->market_id);
        $this->assertNull(DB::table('merchants')->value('tax_id'));
        $this->assertNull(DB::table('merchants')->value('risk_level'));
        $point = DB::selectOne('SELECT ST_SRID(point::geometry) AS srid, ST_X(point::geometry) AS x, ST_Y(point::geometry) AS y FROM branches WHERE id = ?', [$row->branch_id]);
        $this->assertSame([4326, 0.5, 1.5], [$point->srid, $point->x, $point->y]);
        $dump = json_encode($row).json_encode($claim);
        foreach (['Synthetic Legal Canary', 'Synthetic Trade Canary', 'Synthetic Branch Canary', 'synthetic-commerce-key'] as $canary) {
            $this->assertStringNotContainsString($canary, $dump);
        }
        DB::table('merchants')->update(['legal_name' => 'Synthetic changed']);
        DB::table('branches')->update(['name' => 'Synthetic changed']);
        $before = $this->snapshot(['merchants', 'branches', 'marketplace_local_commerce_operations', 'platform_idempotency_keys']);
        $this->assertEquals($operation, $this->create($user, $market));
        $this->rejected(fn () => app(ReadLocalDraftCommerce::class)->execute((int) $user->getKey(), $operation->publicId), 'forbidden');
        $this->grant($user, LocalDraftCommerceAccess::READ);
        $this->assertEquals($operation, app(ReadLocalDraftCommerce::class)->execute((int) $user->getKey(), $operation->publicId));
        $other = $this->actor(false);
        $this->grant($other, LocalDraftCommerceAccess::READ);
        foreach ([$operation->publicId, (string) Str::ulid()] as $id) {
            $this->rejected(fn () => app(ReadLocalDraftCommerce::class)->execute((int) $other->getKey(), $id), 'not_found');
        }
        $this->assertSame($before, $this->snapshot(array_keys($before)));
        $this->assertSame($claim->expires_at, DB::table('platform_idempotency_keys')->value('expires_at'));
    }

    public function test_mismatch_expiry_and_actor_key_isolation_preserve_rows(): void
    {
        $user = $this->actor();
        $market = $this->market();
        $operation = $this->create($user, $market);
        $payload = $this->payload($market);
        $payload['branch']['latitude'] = 2;
        $this->rejected(fn () => $this->create($user, $market, payload: $payload), 'idempotency_mismatch');
        DB::table('platform_idempotency_keys')->update(['expires_at' => new DateTimeImmutable('now')]);
        $this->rejected(fn () => $this->create($user, $market), 'idempotency_expired');
        $this->assertSame([1, 1, 1, 1, 1, 1], $this->counts());
        $other = $this->actor();
        $otherOperation = $this->create($other, $market);
        $this->assertNotSame($operation->publicId, $otherOperation->publicId);
        $this->create($user, $market, 'another-key-same-names');
        $this->assertSame([1, 1, 3, 3, 3, 3], $this->counts());
    }

    public function test_numeric_equivalence_and_signed_zero_share_fingerprint_without_rounding_names(): void
    {
        $user = $this->actor();
        $market = $this->market();
        $payload = $this->payload($market);
        $payload['branch']['longitude'] = 1;
        $payload['branch']['latitude'] = -0.0;
        $first = $this->create($user, $market, payload: $payload);
        $payload['branch']['longitude'] = 1.0;
        $payload['branch']['latitude'] = 0;
        $this->assertEquals($first, $this->create($user, $market, payload: $payload));
        $payload['merchant']['legal_name'] = trim($payload['merchant']['legal_name']);
        $this->rejected(fn () => $this->create($user, $market, payload: $payload), 'idempotency_mismatch');
        $this->assertSame([1, 1, 1, 1, 1, 1], $this->counts());
    }

    public function test_missing_market_partial_branch_and_journal_failures_rollback_every_new_row(): void
    {
        $user = $this->actor();
        $this->rejected(fn () => $this->create($user, (string) Str::ulid()), 'market_not_available');
        $this->assertSame([0, 0, 0, 0, 0, 0], $this->counts());
        $market = $this->market();
        $before = $this->snapshot(['countries', 'markets']);
        foreach (['branches' => "name <> 'Synthetic Branch Canary'", 'marketplace_local_commerce_operations' => 'schema_version <> 1'] as $table => $check) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT synthetic_commerce_failure CHECK ({$check})");
            $this->constraint(fn () => $this->create($user, $market), '23514');
            $this->assertSame([1, 1, 0, 0, 0, 0], $this->counts());
            $this->assertSame($before, $this->snapshot(array_keys($before)));
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT synthetic_commerce_failure");
        }
    }

    public function test_callback_reauthorization_rolls_back_claim_before_commercial_write(): void
    {
        $user = $this->actor();
        $market = $this->market();
        $access = $this->mock(LocalDraftCommerceAccess::class);
        $access->shouldReceive('actor')->once()->ordered()->andReturn($user->public_id);
        $access->shouldReceive('actor')->once()->ordered()->andReturn(null);
        $this->rejected(fn () => $this->create($user, $market), 'forbidden');
        $this->assertSame([1, 1, 0, 0, 0, 0], $this->counts());
    }

    public function test_real_permission_policy_default_deny_scope_expiry_deny_block_and_independent_capabilities(): void
    {
        $this->app->detectEnvironment(fn () => 'testing');
        $user = $this->actor(false);
        $market = $this->market();
        $payload = $this->payload($market);
        $url = '/api/v1/marketplace/local-draft-commerces';
        $this->actingAs($user, 'web')->withHeader('Idempotency-Key', 'policy-commerce-key');
        $this->grant($user, LocalDraftCommerceAccess::READ);
        $this->postJson($url, [])->assertForbidden();
        $this->grant($user, scopeType: 'branch');
        $this->grant($user, scopeId: (string) Str::ulid());
        $this->grant($user, expires: (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP'));
        $this->postJson($url, $payload)->assertForbidden();
        $this->assertSame([1, 1, 0, 0, 0, 0], $this->counts());
        $this->grant($user);
        $first = $this->postJson($url, $payload)->assertCreated();
        $operation = $first->json('data.id');
        $this->getJson($url.'/'.$operation)->assertOk()->assertJsonPath('data', $first->json('data'));
        $this->postJson($url, $payload)->assertCreated()->assertJsonPath('data', $first->json('data'));
        $this->assertSame($first->json('meta.correlation_id'), DB::table('marketplace_local_commerce_operations')->value('correlation_id'));
        $this->grant($user, effect: 'deny');
        $this->postJson($url, $payload)->assertForbidden();
        $this->getJson($url.'/'.$operation)->assertOk();
        $this->grant($user, LocalDraftCommerceAccess::READ, 'deny');
        $this->getJson($url.'/'.$operation)->assertForbidden();
        DB::table('identity_permission_grants')->where('effect', 'deny')->delete();
        DB::table('users')->where('id', $user->getKey())->update(['status' => 'blocked']);
        $this->postJson($url, $payload)->assertForbidden();
        $this->getJson($url.'/'.$operation)->assertForbidden();
        $this->assertSame([1, 1, 1, 1, 1, 1], $this->counts());
    }

    public function test_journal_integrity_append_only_restrict_indexes_and_forward_only(): void
    {
        $this->create($this->actor(), $this->market());
        $row = (array) DB::table('marketplace_local_commerce_operations')->first();
        unset($row['id']);
        foreach (['public_id' => 'invalid', 'actor_public_id' => 'invalid', 'correlation_id' => 'invalid', 'key_hash' => 'raw', 'request_hash' => str_repeat('z', 64), 'response_hash' => str_repeat('A', 64), 'schema_version' => 2, 'response_snapshot_ciphertext' => ' '] as $column => $value) {
            $this->constraint(fn () => DB::table('marketplace_local_commerce_operations')->insert(array_replace($row, [$column => $value])), '23514');
        }
        $unusedMerchant = DB::table('merchants')->insertGetId(['public_id' => (string) Str::ulid(), 'legal_name' => 'Synthetic Integrity', 'trade_name' => 'Synthetic Integrity']);
        $unusedBranch = DB::table('branches')->insertGetId(['public_id' => (string) Str::ulid(), 'merchant_id' => $unusedMerchant, 'market_id' => $row['market_id'], 'name' => 'Synthetic Integrity', 'timezone' => 'Etc/UTC', 'point' => 'SRID=4326;POINT(0 0)']);
        foreach (['merchant_id', 'branch_id', 'market_id'] as $column) {
            $this->constraint(fn () => DB::table('marketplace_local_commerce_operations')->insert(array_replace($row, ['public_id' => (string) Str::ulid(), 'merchant_id' => $unusedMerchant, 'branch_id' => $unusedBranch, $column => 999999999])), '23503');
        }
        $this->constraint(fn () => DB::table('marketplace_local_commerce_operations')->insert($row), '23505');
        $this->constraint(fn () => DB::table('marketplace_local_commerce_operations')->update(['schema_version' => 1]), '23514');
        $this->constraint(fn () => DB::table('marketplace_local_commerce_operations')->delete(), '23514');
        foreach (['merchants' => 'merchant_id', 'branches' => 'branch_id', 'markets' => 'market_id'] as $table => $column) {
            $this->constraint(fn () => DB::table($table)->where('id', $row[$column])->delete(), '23503');
        }
        $types = array_column(DB::select("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'marketplace_local_commerce_operations'"), null, 'column_name');
        foreach (['id' => 'bigint', 'merchant_id' => 'bigint', 'branch_id' => 'bigint', 'response_snapshot_ciphertext' => 'text', 'created_at' => 'timestamp with time zone'] as $column => $type) {
            $this->assertSame($type, $types[$column]->data_type);
        }
        foreach ($types as $column) {
            $this->assertSame('NO', $column->is_nullable);
        }
        $this->assertArrayNotHasKey('updated_at', $types);
        $this->assertArrayNotHasKey('deleted_at', $types);
        $references = array_column(DB::select("SELECT confrelid::regclass::text AS target FROM pg_constraint WHERE conrelid = 'marketplace_local_commerce_operations'::regclass AND contype = 'f' ORDER BY target"), 'target');
        $this->assertSame(['branches', 'markets', 'merchants'], $references);
        $indexes = implode(' ', array_column(DB::select("SELECT indexdef FROM pg_indexes WHERE tablename = 'marketplace_local_commerce_operations'"), 'indexdef'));
        foreach (['public_id', 'merchant_id', 'branch_id', 'market_id', 'actor_public_id', 'created_at'] as $column) {
            $this->assertStringContainsString('('.$column.')', $indexes);
        }
        $before = $this->snapshot(['merchants', 'branches', 'marketplace_local_commerce_operations']);
        $migration = require database_path('migrations/'.self::MIGRATION);
        try {
            $migration->down();
            $this->fail('Unreviewed rollback accepted.');
        } catch (\RuntimeException $failure) {
            $this->assertStringContainsString('forward-only', $failure->getMessage());
        }
        $this->assertSame($before, $this->snapshot(array_keys($before)));
    }

    public function test_corrupt_ciphertext_hash_or_closed_snapshot_is_never_restored(): void
    {
        $user = $this->actor();
        $market = $this->market();
        foreach (['cipher', 'hash', 'shape'] as $failure) {
            $merchant = DB::table('merchants')->insertGetId(['public_id' => (string) Str::ulid(), 'legal_name' => 'Synthetic', 'trade_name' => 'Synthetic']);
            $branch = DB::table('branches')->insertGetId(['public_id' => (string) Str::ulid(), 'merchant_id' => $merchant, 'market_id' => DB::table('markets')->value('id'), 'name' => 'Synthetic', 'timezone' => 'Etc/UTC', 'point' => 'SRID=4326;POINT(0 0)']);
            $id = (string) Str::ulid();
            $plain = $failure === 'shape' ? '{"unexpected":true}' : '{}';
            DB::table('marketplace_local_commerce_operations')->insert(['public_id' => $id, 'merchant_id' => $merchant, 'branch_id' => $branch, 'market_id' => DB::table('markets')->value('id'), 'actor_public_id' => $user->public_id, 'schema_version' => 1, 'key_hash' => str_repeat('a', 64), 'request_hash' => str_repeat('b', 64), 'response_hash' => $failure === 'hash' ? str_repeat('c', 64) : hash('sha256', $plain), 'correlation_id' => (string) Str::ulid(), 'response_snapshot_ciphertext' => $failure === 'cipher' ? 'invalid-ciphertext' : Crypt::encryptString($plain)]);
            try {
                app(LocalDraftCommerceStore::class)->find($id, $user->public_id);
                $this->fail('Corrupt snapshot accepted.');
            } catch (DecryptException|\LogicException|\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(3, DB::table('marketplace_local_commerce_operations')->count());
    }

    public function test_independent_processes_serialize_same_key_and_allow_same_names_with_distinct_keys(): void
    {
        $user = $this->actor();
        $market = $this->market();
        foreach ([['same-key', 'same-key'], ['distinct-a', 'distinct-b']] as $keys) {
            $processes = [];
            foreach ($keys as $key) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/commerce-race-worker.php')], base_path(), ['TRAEPE_TEST_DATABASE' => $this->probeDatabase]);
                $process->setInput(json_encode(['actor_id' => $user->getKey(), 'key' => $key, 'payload' => $this->payload($market)], JSON_THROW_ON_ERROR))->setTimeout(30)->start();
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
        $this->assertSame([1, 1, 3, 3, 3, 3], $this->counts());
    }

    public function test_additive_journal_installation_preserves_every_legacy_row_and_reapplies_without_changes(): void
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
            $this->assertFalse(Schema::hasTable('marketplace_local_commerce_operations'));
            $this->market();
            $this->actor();
            $merchant = DB::table('merchants')->insertGetId(['public_id' => (string) Str::ulid(), 'legal_name' => 'Synthetic Legacy', 'trade_name' => 'Synthetic Legacy']);
            DB::table('branches')->insert(['public_id' => (string) Str::ulid(), 'merchant_id' => $merchant, 'market_id' => DB::table('markets')->value('id'), 'name' => 'Synthetic Legacy', 'timezone' => 'Etc/UTC', 'point' => 'SRID=4326;POINT(0 0)']);
            $tables = array_column(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'migrations'"), 'tablename');
            $before = $this->snapshot($tables);
            $migrations = DB::table('migrations')->count();
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
            $this->assertSame($before, $this->snapshot($tables));
            $this->assertSame($migrations + 1, DB::table('migrations')->count());
            $this->assertSame(0, DB::table('marketplace_local_commerce_operations')->count());
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
