<?php

namespace Tests\Integration;

use App\Modules\Identity\Infrastructure\IdentityUser;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageAccess;
use App\Modules\Marketplace\Application\Fixtures\CreateLocalDraftFixture;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureAccess;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

final class CommercialFoundationTest extends PostgresTestCase
{
    private const MIGRATION = '2026_10_10_000005_create_marketplace_commercial_foundation.php';

    private const TABLES = ['countries', 'markets', 'service_zones', 'marketplace_local_fixture_operations', 'merchants', 'branches'];

    private function merchant(array $changes = []): int
    {
        return DB::table('merchants')->insertGetId(array_replace(['public_id' => (string) Str::ulid(), 'legal_name' => 'Synthetic Merchant', 'trade_name' => 'Synthetic Merchant'], $changes));
    }

    private function market(): int
    {
        $country = DB::table('countries')->value('id') ?? DB::table('countries')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ']);

        return DB::table('markets')->insertGetId(['public_id' => (string) Str::ulid(), 'country_id' => $country, 'name' => 'Synthetic Market', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']);
    }

    private function branch(int $merchant, int $market, array $changes = []): int
    {
        return DB::table('branches')->insertGetId(array_replace(['public_id' => (string) Str::ulid(), 'merchant_id' => $merchant, 'market_id' => $market, 'name' => 'Synthetic Branch', 'timezone' => 'Etc/UTC', 'point' => 'SRID=4326;POINT(0.5 1.5)'], $changes));
    }

    private function rejected(callable $operation, string|array $state): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Invalid commercial fixture accepted.');
        } catch (QueryException $failure) {
            // Report only SQLSTATE, never SQL, bindings, identifiers or coordinates.
            $this->assertContains($failure->errorInfo[0], is_array($state) ? $state : [$state]);
        }
    }

    private function actor(array $capabilities): IdentityUser
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => '', 'status' => 'active']);
        foreach ($capabilities as $capability) {
            $permission = DB::table('identity_permissions')->where('code', $capability)->value('id') ?? DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => $capability]);
            DB::table('identity_permission_grants')->insert(['public_id' => (string) Str::ulid(), 'user_id' => $id, 'permission_id' => $permission, 'scope_type' => 'platform', 'scope_public_id' => LocalDraftFixtureAccess::SCOPE_PUBLIC_ID, 'effect' => 'allow']);
        }

        return IdentityUser::query()->findOrFail($id);
    }

    private function snapshot(array $tables): array
    {
        $snapshot = [];
        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->sort()->values()->toJson();
            $snapshot[$table] = hash('sha256', $rows);
        }

        return $snapshot;
    }

    public function test_empty_schema_types_nullability_defaults_indexes_and_own_foreign_keys(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame(0, DB::table($table)->count());
        }
        foreach (['merchants', 'branches'] as $table) {
            $columns = DB::select('SELECT column_name, data_type, udt_name, character_maximum_length, is_nullable, column_default FROM information_schema.columns WHERE table_schema = ? AND table_name = ?', ['public', $table]);
            $byName = array_column($columns, null, 'column_name');
            foreach (['id' => 'bigint', 'public_id' => 'character', 'status' => 'character varying', 'version' => 'integer', 'created_at' => 'timestamp with time zone', 'updated_at' => 'timestamp with time zone'] as $column => $type) {
                $this->assertSame($type, $byName[$column]->data_type);
            }
            $this->assertSame(26, $byName['public_id']->character_maximum_length);
            $this->assertSame(40, $byName['status']->character_maximum_length);
            $this->assertStringContainsString('draft', $byName['status']->column_default);
            $this->assertSame('1', $byName['version']->column_default);
            foreach (['created_at', 'updated_at'] as $column) {
                $this->assertSame('CURRENT_TIMESTAMP', $byName[$column]->column_default);
            }
            foreach ($columns as $column) {
                $this->assertSame(in_array($column->column_name, ['tax_id', 'risk_level'], true) ? 'YES' : 'NO', $column->is_nullable);
            }
            $this->assertNotContains('deleted_at', array_keys($byName));
            $indexes = DB::select('SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = ? AND tablename = ?', ['public', $table]);
            $definitions = array_column($indexes, 'indexdef', 'indexname');
            $this->assertArrayHasKey($table.'_pkey', $definitions);
            $this->assertStringContainsString('UNIQUE INDEX', $definitions[$table.'_public_id_unique']);
            $unique = DB::select('SELECT indexrelid::regclass::text AS name FROM pg_index WHERE indrelid = ?::regclass AND indisunique', [$table]);
            $names = array_column($unique, 'name');
            sort($names);
            $this->assertSame([$table.'_pkey', $table.'_public_id_unique'], $names);
            if ($table === 'merchants') {
                foreach (['legal_name' => 255, 'trade_name' => 255, 'tax_id' => 64, 'risk_level' => 40] as $column => $length) {
                    $this->assertSame('character varying', $byName[$column]->data_type);
                    $this->assertSame($length, $byName[$column]->character_maximum_length);
                    $this->assertNull($byName[$column]->column_default);
                }
                $this->assertNotContains('market_id', array_keys($byName));
                $this->assertSame([], DB::select("SELECT confrelid FROM pg_constraint WHERE conrelid = 'merchants'::regclass AND contype = 'f'"));
            } else {
                $this->assertSame('geography', $byName['point']->udt_name);
                foreach (['name' => 255, 'timezone' => 64] as $column => $length) {
                    $this->assertSame('character varying', $byName[$column]->data_type);
                    $this->assertSame($length, $byName[$column]->character_maximum_length);
                    $this->assertNull($byName[$column]->column_default);
                }
                foreach (['merchant_id', 'market_id'] as $column) {
                    $this->assertSame('bigint', $byName[$column]->data_type);
                    $this->assertNull($byName[$column]->column_default);
                }
                $this->assertStringContainsString('(merchant_id, market_id)', $definitions['branches_merchant_id_market_id_index']);
                $this->assertStringContainsString('(market_id)', $definitions['branches_market_id_index']);
                $this->assertMatchesRegularExpression('/USING gist \\(point\\)/i', $definitions['branches_point_gist']);
                $foreignKeys = DB::select("SELECT confrelid::regclass::text AS target, confdeltype AS deletion FROM pg_constraint WHERE conrelid = 'branches'::regclass AND contype = 'f' ORDER BY target");
                $this->assertSame(['markets', 'merchants'], array_column($foreignKeys, 'target'));
                $this->assertSame(['r', 'r'], array_column($foreignKeys, 'deletion'));
            }
        }
        $migrations = DB::table('migrations')->count();
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertStringContainsString('Nothing to migrate', Artisan::output());
        $this->assertSame($migrations, DB::table('migrations')->count());
    }

    public function test_multiple_merchants_branches_and_markets_preserve_explicit_context_defaults_and_utc(): void
    {
        $a = $this->merchant(['created_at' => '2026-10-10T03:00:00-05:00']);
        $b = $this->merchant();
        $marketA = $this->market();
        $marketB = $this->market();
        $branch = $this->branch($a, $marketA, ['created_at' => '2026-10-10T03:00:00-05:00']);
        $this->branch($a, $marketA);
        $this->branch($a, $marketB);
        $this->branch($b, $marketA);
        $this->assertSame(2, DB::table('merchants')->count());
        $this->assertSame(4, DB::table('branches')->count());
        $this->assertSame(2, DB::table('branches')->where('merchant_id', $a)->where('market_id', $marketA)->count());
        $this->assertSame(1, DB::table('branches')->where('merchant_id', $a)->where('market_id', $marketB)->count());
        $this->assertSame(1, DB::table('branches')->where('merchant_id', $b)->where('market_id', $marketA)->count());
        foreach (['merchants' => $a, 'branches' => $branch] as $table => $id) {
            $row = DB::table($table)->find($id);
            $this->assertTrue(Str::isUlid($row->public_id));
            $this->assertSame('draft', $row->status);
            $this->assertSame(1, $row->version);
            $this->assertSame('2026-10-10 08:00:00', DB::selectOne("SELECT to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') AS utc FROM {$table} WHERE id = ?", [$id])->utc);
        }
        $this->assertNull(DB::table('merchants')->find($a)->tax_id);
        $this->assertNull(DB::table('merchants')->find($a)->risk_level);
        $this->assertSame('Etc/UTC', DB::table('branches')->find($branch)->timezone);
        $point = DB::selectOne('SELECT ST_SRID(point::geometry) AS srid, ST_NDims(point::geometry) AS dimensions, GeometryType(point::geometry) AS type, ST_IsValid(point::geometry, 0) AS valid, ST_IsEmpty(point::geometry) AS empty, ST_X(point::geometry) AS longitude, ST_Y(point::geometry) AS latitude FROM branches WHERE id = ?', [$branch]);
        $this->assertSame(4326, $point->srid);
        $this->assertSame(2, $point->dimensions);
        $this->assertSame('POINT', $point->type);
        $this->assertTrue($point->valid);
        $this->assertFalse($point->empty);
        $this->assertSame(0.5, $point->longitude);
        $this->assertSame(1.5, $point->latitude);
    }

    public function test_invalid_identifiers_names_fiscal_risk_states_versions_and_required_fields_are_rejected(): void
    {
        foreach (['public_id' => 'invalid', 'legal_name' => '', 'trade_name' => ' ', 'tax_id' => 'synthetic-tax-id', 'risk_level' => 'low', 'status' => 'approved', 'version' => 0] as $column => $value) {
            $this->rejected(fn () => $this->merchant([$column => $value]), '23514');
        }
        foreach (['public_id', 'legal_name', 'trade_name', 'status', 'version', 'created_at', 'updated_at'] as $column) {
            $this->rejected(fn () => $this->merchant([$column => null]), '23502');
        }
        foreach (['legal_name', 'trade_name'] as $column) {
            $this->rejected(fn () => $this->merchant([$column => str_repeat('x', 256)]), '22001');
        }
        $merchant = $this->merchant();
        $market = $this->market();
        foreach (['public_id' => 'invalid', 'name' => '', 'timezone' => ' ', 'status' => 'open', 'version' => -1] as $column => $value) {
            $this->rejected(fn () => $this->branch($merchant, $market, [$column => $value]), '23514');
        }
        foreach (['public_id', 'merchant_id', 'market_id', 'name', 'timezone', 'point', 'status', 'version', 'created_at', 'updated_at'] as $column) {
            $this->rejected(fn () => $this->branch($merchant, $market, [$column => null]), '23502');
        }
        $this->rejected(fn () => $this->branch($merchant, $market, ['timezone' => str_repeat('x', 65)]), '22001');
        $branch = $this->branch($merchant, $market);
        foreach (['tax_id' => 'synthetic-tax-id', 'risk_level' => 'low', 'status' => 'active', 'version' => 0] as $column => $value) {
            $this->rejected(fn () => DB::table('merchants')->where('id', $merchant)->update([$column => $value]), '23514');
        }
        foreach (['status' => 'paused', 'version' => 0, 'name' => ' ', 'timezone' => ''] as $column => $value) {
            $this->rejected(fn () => DB::table('branches')->where('id', $branch)->update([$column => $value]), '23514');
        }
        $this->assertSame(1, DB::table('merchants')->count());
        $this->assertSame(1, DB::table('branches')->count());
    }

    public function test_public_id_uniqueness_strict_ulids_and_foreign_key_restrict_preserve_rows(): void
    {
        $merchant = $this->merchant();
        $market = $this->market();
        $branch = $this->branch($merchant, $market);
        $merchantId = DB::table('merchants')->find($merchant)->public_id;
        $branchId = DB::table('branches')->find($branch)->public_id;
        $this->rejected(fn () => $this->merchant(['public_id' => $merchantId]), '23505');
        $this->rejected(fn () => $this->branch($merchant, $market, ['public_id' => $branchId]), '23505');
        foreach (['01ARZ3NDEKTSV4RRFFQ69G5FAZ', '7ZZZZZZZZZZZZZZZZZZZZZZZZZ'] as $id) {
            $this->merchant(['public_id' => $id]);
        }
        foreach (['8ZZZZZZZZZZZZZZZZZZZZZZZZZ', strtolower($merchantId), '01ARZ3NDEKTSV4RRFFQ69G5FAI'] as $id) {
            $this->rejected(fn () => $this->merchant(['public_id' => $id]), '23514');
        }
        $before = $this->snapshot(['merchants', 'branches', 'markets']);
        $this->rejected(fn () => $this->branch(9223372036854775807, $market), '23503');
        $this->rejected(fn () => $this->branch($merchant, 9223372036854775807), '23503');
        $this->rejected(fn () => DB::table('merchants')->where('id', $merchant)->delete(), '23503');
        $this->rejected(fn () => DB::table('markets')->where('id', $market)->delete(), '23503');
        $this->assertSame($before, $this->snapshot(['merchants', 'branches', 'markets']));
    }

    public function test_spatial_type_srid_dimensions_empty_and_non_finite_points_are_rejected(): void
    {
        $merchant = $this->merchant();
        $market = $this->market();
        $this->rejected(fn () => $this->branch($merchant, $market, ['point' => 'SRID=4326;POINT EMPTY']), '23514');
        foreach (['SRID=4326;POINT(NaN 0)', 'SRID=4326;POINT(0 NaN)', 'SRID=4326;POINT(Infinity 0)', 'SRID=4326;POINT(0 Infinity)'] as $point) {
            // PostGIS may reject a non-finite literal during parsing before CHECK.
            $this->rejected(fn () => $this->branch($merchant, $market, ['point' => $point]), ['23514', 'XX000']);
        }
        foreach (['SRID=4326;LINESTRING(0 0,1 1)', 'SRID=4326;MULTIPOINT((0 0),(1 1))', 'SRID=4269;POINT(0 0)', 'SRID=3857;POINT(0 0)', 'SRID=4326;POINT Z (0 0 1)', 'SRID=4326;POINT M (0 0 1)'] as $point) {
            $this->rejected(fn () => $this->branch($merchant, $market, ['point' => $point]), '22023');
        }
        $this->assertSame(0, DB::table('branches')->count());
    }

    public function test_geographic_diagnostics_and_fixture_creation_do_not_consult_or_change_commercial_rows(): void
    {
        $this->app->detectEnvironment(fn () => 'testing');
        $merchant = $this->merchant();
        $market = $this->market();
        $this->branch($merchant, $market);
        $user = $this->actor([LocalDraftFixtureAccess::CAPABILITY, LocalPersistedCoverageAccess::CAPABILITY]);
        $this->actingAs($user, 'web');
        $before = $this->snapshot(['merchants', 'branches']);
        $touched = 0;
        $record = false;
        DB::listen(function (QueryExecuted $event) use (&$touched, &$record): void {
            if ($record && preg_match('/\\b(merchants|branches)\\b/i', $event->sql)) {
                $touched++;
            }
        });
        $record = true;
        $this->getJson('/api/v1/marketplace/local-coverage-probe?longitude=0.5&latitude=0.5')->assertOk()->assertJsonPath('data.attributes.status', 'selected');
        $this->assertSame(0, Artisan::call('marketplace:local-coverage', ['longitude' => '0.5', 'latitude' => '0.5']));
        $publicMarket = DB::table('markets')->find($market)->public_id;
        $this->getJson('/api/v1/marketplace/local-persisted-coverage-probe?market_public_id='.$publicMarket.'&longitude=0.5&latitude=1.5')->assertOk()->assertJsonPath('data.attributes.status', 'outside');
        $this->assertSame(0, Artisan::call('marketplace:local-persisted-coverage', ['market_public_id' => $publicMarket, 'longitude' => '0.5', 'latitude' => '1.5']));
        $created = $this->withHeader('Idempotency-Key', 'synthetic-commercial-regression')->postJson('/api/v1/marketplace/local-draft-fixtures', ['fixture_profile' => FixtureProfile::A->value])->assertCreated();
        $this->postJson('/api/v1/marketplace/local-draft-fixtures', ['fixture_profile' => FixtureProfile::A->value])->assertCreated()->assertJsonPath('data', $created->json('data'));
        $this->getJson('/api/v1/marketplace/local-persisted-coverage-probe?market_public_id='.$created->json('data.attributes.market_public_id').'&longitude=0.5&latitude=0.5')->assertOk()->assertJsonPath('data.attributes.status', 'selected');
        $record = false;
        $this->assertSame(0, $touched);
        $this->assertSame($before, $this->snapshot(['merchants', 'branches']));
        $this->assertSame(1, DB::table('marketplace_local_fixture_operations')->count());
        $this->assertSame(1, DB::table('platform_idempotency_keys')->count());
    }

    public function test_forward_only_down_preserves_synthetic_commercial_records(): void
    {
        $merchant = $this->merchant();
        $this->branch($merchant, $this->market());
        $before = $this->snapshot(self::TABLES);
        $migration = require database_path('migrations/'.self::MIGRATION);
        try {
            $migration->down();
            $this->fail('Unreviewed commercial rollback accepted.');
        } catch (RuntimeException $failure) {
            $this->assertStringContainsString('forward-only', $failure->getMessage());
        }
        $this->assertSame($before, $this->snapshot(self::TABLES));
    }

    public function test_additive_installation_preserves_all_legacy_data_and_idempotent_reapplication(): void
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
            $this->assertFalse(Schema::hasTable('merchants'));
            $this->assertFalse(Schema::hasTable('branches'));
            $user = $this->actor([LocalDraftFixtureAccess::CAPABILITY]);
            app(CreateLocalDraftFixture::class)->execute((int) $user->getKey(), FixtureProfile::A, 'synthetic-legacy-key', (string) Str::ulid());
            $tables = array_column(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'migrations'"), 'tablename');
            $before = $this->snapshot($tables);
            $migrations = DB::table('migrations')->count();
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
            $this->assertSame($before, $this->snapshot($tables));
            $this->assertSame($migrations + 1, DB::table('migrations')->count());
            $this->assertSame(0, DB::table('merchants')->count());
            $this->assertSame(0, DB::table('branches')->count());
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
            $this->assertSame($migrations + 1, DB::table('migrations')->count());
            $this->assertSame($before, $this->snapshot($tables));
        } finally {
            config(['database.connections.pgsql.database' => 'postgres']);
            DB::purge('pgsql');
            // Only the random database created above is removed.
            DB::statement('DROP DATABASE "'.$legacy.'" WITH (FORCE)');
            config(['database.connections.pgsql.database' => $this->probeDatabase]);
            DB::purge('pgsql');
        }
    }
}
