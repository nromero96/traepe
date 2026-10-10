<?php

namespace Tests\Integration;

use App\Modules\Identity\Infrastructure\IdentityUser;
use App\Modules\Marketplace\Application\Fixtures\CreateLocalDraftFixture;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureAccess;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureStore;
use App\Modules\Marketplace\Domain\Fixtures\FixtureFailure;
use App\Modules\Marketplace\Domain\Fixtures\FixtureOperation;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
use App\Modules\Marketplace\Infrastructure\Coverage\PostgisLocalZoneSource;
use App\Modules\Marketplace\Infrastructure\Fixtures\PlatformLocalFixtureWriter;
use App\Modules\Platform\Application\Delivery\IdempotencyStore;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class LocalDraftFixturePostgresTest extends PostgresTestCase
{
    private function actor(bool $grant = true): IdentityUser
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => '', 'status' => 'active']);
        $user = IdentityUser::query()->findOrFail($id);
        if ($grant) {
            $this->grant($user);
        }

        return $user;
    }

    private function grant(IdentityUser $user, string $effect = 'allow', string $capability = LocalDraftFixtureAccess::CAPABILITY, string $scopeType = 'platform', ?string $scopeId = null, ?string $expires = null): void
    {
        $permission = DB::table('identity_permissions')->where('code', $capability)->value('id') ?? DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => $capability]);
        DB::table('identity_permission_grants')->insert(['public_id' => (string) Str::ulid(), 'user_id' => $user->getKey(), 'permission_id' => $permission, 'scope_type' => $scopeType, 'scope_public_id' => $scopeId ?? LocalDraftFixtureAccess::SCOPE_PUBLIC_ID, 'effect' => $effect, 'expires_at' => $expires]);
    }

    private function create(IdentityUser $user, string $key = 'synthetic-key', FixtureProfile $profile = FixtureProfile::A): FixtureOperation
    {
        return app(CreateLocalDraftFixture::class)->execute((int) $user->getKey(), $profile, $key, (string) Str::ulid());
    }

    private function counts(): array
    {
        return array_map(fn ($table) => DB::table($table)->count(), ['countries', 'markets', 'service_zones', 'marketplace_local_fixture_operations', 'platform_idempotency_keys']);
    }

    private function rejected(callable $call, string $code): void
    {
        try {
            $call();
            $this->fail('Expected fixture rejection.');
        } catch (FixtureFailure $failure) {
            $this->assertSame($code, $failure->getMessage());
        }
    }

    public function test_creation_replay_snapshot_hashes_ttl_country_reuse_and_actor_scope_isolation(): void
    {
        $user = $this->actor();
        $operation = $this->create($user);
        $this->assertSame([1, 1, 3, 1, 1], $this->counts());
        $row = DB::table('marketplace_local_fixture_operations')->first();
        $claim = DB::table('platform_idempotency_keys')->first();
        $this->assertSame(hash('sha256', 'synthetic-key'), $row->key_hash);
        $this->assertSame($row->request_hash, $claim->request_hash);
        $this->assertSame(hash('sha256', PlatformLocalFixtureWriter::SCOPE), $claim->scope_hash);
        $this->assertSame(hash('sha256', 'identity.user:'.$user->public_id), $claim->actor_hash);
        $this->assertSame($operation->publicId, $claim->response_ref);
        $ttl = (new DateTimeImmutable($claim->expires_at))->getTimestamp() - (new DateTimeImmutable($claim->created_at))->getTimestamp();
        $this->assertGreaterThanOrEqual(86398, $ttl);
        $this->assertLessThanOrEqual(86401, $ttl);
        DB::table('markets')->update(['name' => 'Synthetic changed name']);
        $this->assertEquals($operation, $this->create($user));
        $this->assertSame($claim->expires_at, DB::table('platform_idempotency_keys')->value('expires_at'));
        $this->assertSame($row->correlation_id, DB::table('marketplace_local_fixture_operations')->value('correlation_id'));
        $this->assertSame([1, 1, 3, 1, 1], $this->counts());
        $next = $this->create($user, 'second', FixtureProfile::B);
        $otherUser = $this->actor();
        $other = $this->create($otherUser);
        $this->assertSame($operation->countryPublicId, $next->countryPublicId);
        $this->assertSame($operation->countryPublicId, $other->countryPublicId);
        $this->assertNotSame($operation->marketPublicId, $next->marketPublicId);
        $this->assertNotSame($operation->publicId, $other->publicId);
        $this->assertSame([1, 3, 9, 3, 3], $this->counts());
        $differentScope = (string) Str::ulid();
        $this->assertSame($differentScope, app(IdempotencyStore::class)->execute('synthetic.other.scope', 'identity.user:'.$user->public_id, 'synthetic-key', ['fixture_profile' => FixtureProfile::B->value], new DateTimeImmutable('+1 hour', new DateTimeZone('UTC')), fn () => $differentScope));
        foreach (DB::table('service_zones')->where('market_id', $row->market_id)->orderBy('id')->get() as $index => $zone) {
            $this->assertSame($operation->zonePublicIds[$index], $zone->public_id);
            $this->assertNotSame(PostgisLocalZoneSource::FIXTURES[$index][0], $zone->public_id);
            $this->assertSame('draft', $zone->status);
            $this->assertSame('fixture', $zone->zone_type);
            $this->assertSame(PostgisLocalZoneSource::FIXTURES[$index][1], $zone->priority);
            $geometry = DB::selectOne('SELECT ST_SRID(polygon::geometry) AS srid, ST_GeometryType(polygon::geometry) AS type, ST_Equals(polygon::geometry, ST_Multi(ST_GeomFromText(?,4326))) AS equal FROM service_zones WHERE id = ?', [PostgisLocalZoneSource::FIXTURES[$index][2], $zone->id]);
            $this->assertSame(4326, $geometry->srid);
            $this->assertSame('ST_MultiPolygon', $geometry->type);
            $this->assertTrue($geometry->equal);
        }
        $dump = DB::table('marketplace_local_fixture_operations')->get()->toJson().DB::table('platform_idempotency_keys')->get()->toJson();
        $this->assertStringNotContainsString('synthetic-key', $dump);
        $this->expectException(\LogicException::class);
        app(LocalDraftFixtureStore::class)->find($operation->publicId, $otherUser->public_id);
    }

    public function test_mismatch_expiry_and_current_permission_reject_without_new_rows(): void
    {
        $user = $this->actor();
        $this->create($user);
        $before = $this->counts();
        $this->rejected(fn () => $this->create($user, profile: FixtureProfile::B), 'idempotency_mismatch');
        DB::table('platform_idempotency_keys')->update(['expires_at' => new DateTimeImmutable('now', new DateTimeZone('UTC'))]);
        $this->rejected(fn () => $this->create($user), 'idempotency_expired');
        DB::table('identity_permission_grants')->delete();
        $this->rejected(fn () => $this->create($user), 'forbidden');
        $this->grant($user);
        DB::table('users')->where('id', $user->getKey())->update(['status' => 'blocked']);
        $this->rejected(fn () => $this->create($user, 'blocked-new'), 'forbidden');
        $this->assertSame($before, $this->counts());
    }

    public function test_conflicting_country_is_never_overwritten_and_claim_rolls_back(): void
    {
        $user = $this->actor();
        DB::table('countries')->insert(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Other Synthetic Country', 'currency_code' => 'ZZZ']);
        $before = DB::table('countries')->get()->toJson();
        $this->rejected(fn () => $this->create($user), 'fixture_country_conflict');
        $this->assertSame([1, 0, 0, 0, 0], $this->counts());
        $this->assertSame($before, DB::table('countries')->get()->toJson());
    }

    public function test_partial_zone_and_journal_failures_rollback_claim_and_new_country_and_preserve_existing_country(): void
    {
        $user = $this->actor();
        foreach (['service_zones' => "name <> 'Synthetic Zone B'", 'marketplace_local_fixture_operations' => 'schema_version <> 1'] as $table => $check) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT synthetic_failure CHECK ({$check})");
            try {
                $this->create($user);
                $this->fail('Injected fixture failure must reject.');
            } catch (QueryException $failure) {
                $this->assertSame('23514', $failure->errorInfo[0]);
            }
            $this->assertSame([0, 0, 0, 0, 0], $this->counts());
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT synthetic_failure");
        }
        DB::table('countries')->insert(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ']);
        $before = DB::table('countries')->get()->toJson();
        DB::statement("ALTER TABLE service_zones ADD CONSTRAINT synthetic_failure CHECK (name <> 'Synthetic Zone B')");
        try {
            $this->create($user);
            $this->fail('Injected fixture failure must reject.');
        } catch (QueryException $failure) {
            $this->assertSame('23514', $failure->errorInfo[0]);
        }
        $this->assertSame([1, 0, 0, 0, 0], $this->counts());
        $this->assertSame($before, DB::table('countries')->get()->toJson());
    }

    public function test_revalidation_inside_transaction_rolls_back_reserved_claim(): void
    {
        $user = $this->actor();
        $access = $this->mock(LocalDraftFixtureAccess::class);
        $access->shouldReceive('actor')->once()->ordered()->andReturn($user->public_id);
        $access->shouldReceive('actor')->once()->ordered()->andReturn(null);
        $this->rejected(fn () => $this->create($user), 'forbidden');
        $this->assertSame([0, 0, 0, 0, 0], $this->counts());
    }

    public function test_migration_integrity_append_only_and_forward_only(): void
    {
        $this->assertSame([0, 0, 0, 0, 0], $this->counts());
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertStringContainsString('Nothing to migrate', Artisan::output());
        $this->create($this->actor());
        $row = (array) DB::table('marketplace_local_fixture_operations')->first();
        unset($row['id']);
        foreach (['public_id' => 'invalid', 'actor_public_id' => '11', 'correlation_id' => 'invalid', 'key_hash' => 'raw-key', 'request_hash' => str_repeat('z', 64), 'profile_version' => 'operational', 'schema_version' => 2, 'response_snapshot' => '[]'] as $column => $value) {
            $bad = array_replace($row, ['public_id' => (string) Str::ulid(), 'market_id' => $this->newMarket()], [$column => $value]);
            $this->constraint(fn () => DB::table('marketplace_local_fixture_operations')->insert($bad), '23514');
        }
        $this->constraint(fn () => DB::table('marketplace_local_fixture_operations')->insert($row), '23505');
        $bad = array_replace($row, ['public_id' => (string) Str::ulid()]);
        $this->constraint(fn () => DB::table('marketplace_local_fixture_operations')->insert($bad), '23505');
        $this->constraint(fn () => DB::table('marketplace_local_fixture_operations')->update(['schema_version' => 1]), '23514');
        $this->constraint(fn () => DB::table('marketplace_local_fixture_operations')->delete(), '23514');
        $this->constraint(fn () => DB::table('markets')->where('id', $row['market_id'])->delete(), '23503');
        $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'marketplace_local_fixture_operations'");
        $this->assertNotContains('updated_at', array_column($columns, 'column_name'));
        $this->assertNotContains('deleted_at', array_column($columns, 'column_name'));
        $types = DB::select("SELECT column_name, data_type, udt_name, is_nullable, character_maximum_length FROM information_schema.columns WHERE table_name = 'marketplace_local_fixture_operations'");
        $byName = array_column($types, null, 'column_name');
        foreach (['id' => 'bigint', 'market_id' => 'bigint', 'public_id' => 'character', 'actor_public_id' => 'character', 'schema_version' => 'smallint', 'response_snapshot' => 'jsonb', 'created_at' => 'timestamp with time zone'] as $column => $type) {
            $this->assertSame($type, $byName[$column]->data_type);
        }
        foreach ($byName as $column) {
            $this->assertSame('NO', $column->is_nullable);
        }
        foreach (['public_id' => 26, 'actor_public_id' => 26, 'correlation_id' => 26, 'key_hash' => 64, 'request_hash' => 64, 'profile_version' => 64] as $column => $length) {
            $this->assertSame($length, $byName[$column]->character_maximum_length);
        }
        $references = DB::select("SELECT confrelid::regclass::text AS target FROM pg_constraint WHERE conrelid = 'marketplace_local_fixture_operations'::regclass AND contype = 'f'");
        $this->assertSame(['markets'], array_column($references, 'target'));
        $bad = array_replace($row, ['public_id' => (string) Str::ulid(), 'market_id' => 999999999]);
        $this->constraint(fn () => DB::table('marketplace_local_fixture_operations')->insert($bad), '23503');
        $indexes = DB::select("SELECT indexdef FROM pg_indexes WHERE tablename = 'marketplace_local_fixture_operations'");
        $definitions = implode(' ', array_column($indexes, 'indexdef'));
        foreach (['public_id', 'market_id', 'actor_public_id', 'created_at'] as $column) {
            $this->assertStringContainsString('('.$column.')', $definitions);
        }
        $migration = require database_path('migrations/2026_10_10_000004_create_marketplace_local_fixture_operations.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    private function newMarket(): int
    {
        return DB::table('markets')->insertGetId(['public_id' => (string) Str::ulid(), 'country_id' => DB::table('countries')->value('id'), 'name' => 'Synthetic Integrity Market', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']);
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

    public function test_independent_processes_serialize_same_key_and_share_country_for_distinct_keys(): void
    {
        $user = $this->actor();
        foreach ([['same-key', 'same-key'], ['distinct-a', 'distinct-b']] as $keys) {
            $processes = [];
            foreach ($keys as $key) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/fixture-race-worker.php')], base_path(), ['TRAEPE_TEST_DATABASE' => $this->probeDatabase]);
                $process->setInput(json_encode(['actor_id' => $user->getKey(), 'key' => $key], JSON_THROW_ON_ERROR))->setTimeout(30)->start();
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
        $this->assertSame([1, 3, 9, 3, 3], $this->counts());
    }

    public function test_http_real_identity_policy_read_does_not_create_and_context_is_server_owned(): void
    {
        $this->app->detectEnvironment(fn () => 'testing');
        $user = $this->actor(false);
        $this->actingAs($user, 'web');
        $url = '/api/v1/marketplace/local-draft-fixtures';
        $this->withHeader('Idempotency-Key', 'policy-key');
        $this->grant($user, capability: 'marketplace.local.persisted_coverage.read');
        $this->postJson($url, ['actor' => 'other', 'now' => '1999', 'scope_type' => 'platform', 'capability' => '*'])->assertForbidden();
        $this->grant($user, scopeType: 'branch');
        $this->postJson($url, [])->assertForbidden();
        $this->grant($user, scopeId: (string) Str::ulid());
        $this->postJson($url, [])->assertForbidden();
        $this->grant($user, expires: (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:sP'));
        $this->postJson($url, [])->assertForbidden();
        $this->assertSame([0, 0, 0, 0, 0], $this->counts());
        $this->grant($user);
        $created = $this->postJson($url, ['fixture_profile' => FixtureProfile::A->value])->assertCreated();
        $data = $created->json('data');
        $replayed = $this->postJson($url, ['fixture_profile' => FixtureProfile::A->value])->assertCreated()->assertJsonPath('data', $data);
        $this->assertNotSame($created->json('meta.correlation_id'), $replayed->json('meta.correlation_id'));
        $this->assertSame($created->json('meta.correlation_id'), DB::table('marketplace_local_fixture_operations')->value('correlation_id'));
        $this->grant($user, effect: 'deny');
        $this->postJson($url, ['fixture_profile' => FixtureProfile::A->value])->assertForbidden();
        $this->assertSame([1, 1, 3, 1, 1], $this->counts());
    }
}
