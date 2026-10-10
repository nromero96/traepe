<?php

namespace Tests\Integration;

use App\Modules\Marketplace\Application\Coverage\LocalCoverageProbe;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

final class GeographicFoundationTest extends PostgresTestCase
{
    private const POLYGON = 'SRID=4326;MULTIPOLYGON(((9 9,11 9,11 11,9 11,9 9)))';

    private function country(array $changes = []): int
    {
        return DB::table('countries')->insertGetId(array_replace([
            'public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ',
        ], $changes));
    }

    private function market(int $country, array $changes = []): int
    {
        return DB::table('markets')->insertGetId(array_replace([
            'public_id' => (string) Str::ulid(), 'country_id' => $country, 'name' => 'Synthetic Market', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX',
        ], $changes));
    }

    private function zone(int $market, array $changes = []): int
    {
        return DB::table('service_zones')->insertGetId(array_replace([
            'public_id' => (string) Str::ulid(), 'market_id' => $market, 'name' => 'Synthetic Zone', 'priority' => -10, 'polygon' => self::POLYGON,
        ], $changes));
    }

    private function rejected(callable $action, array $states): void
    {
        try {
            $action();
            $this->fail('Invalid geographic fixture accepted.');
        } catch (QueryException $error) {
            // No SQL/bindings or spatial error messages are printed as evidence.
            $this->assertContains($error->errorInfo[0], $states);
        }
    }

    public function test_clean_migration_has_empty_tables_expected_types_and_indexes_and_is_idempotent(): void
    {
        foreach (['countries', 'markets', 'service_zones'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame(0, DB::table($table)->count());
            $columns = DB::select('SELECT column_name, data_type, udt_name, character_maximum_length, is_nullable FROM information_schema.columns WHERE table_schema = ? AND table_name = ?', ['public', $table]);
            $byName = array_column($columns, null, 'column_name');
            $this->assertSame('bigint', $byName['id']->data_type);
            $this->assertSame('character', $byName['public_id']->data_type);
            $this->assertSame(26, $byName['public_id']->character_maximum_length);
            foreach ($columns as $column) {
                $this->assertSame('NO', $column->is_nullable);
            }
            foreach (['created_at', 'updated_at'] as $name) {
                $this->assertSame('timestamp with time zone', $byName[$name]->data_type);
            }
            $indexes = DB::select('SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = ? AND tablename = ?', ['public', $table]);
            $names = array_column($indexes, 'indexname');
            $this->assertContains($table.'_pkey', $names);
            $this->assertContains($table.'_public_id_unique', $names);
            if ($table === 'countries') {
                $this->assertContains('countries_code_unique', $names);
            } elseif ($table === 'markets') {
                $this->assertSame('bigint', $byName['country_id']->data_type);
                $this->assertContains('markets_country_id_index', $names);
            } else {
                $this->assertSame('bigint', $byName['market_id']->data_type);
                $this->assertSame('geography', $byName['polygon']->udt_name);
                $this->assertContains('service_zones_market_id_index', $names);
                $this->assertMatchesRegularExpression('/USING gist \(polygon\)/i', array_column($indexes, 'indexdef', 'indexname')['service_zones_polygon_gist']);
            }
        }
        foreach (['merchants', 'branches'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertSame([], DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename IN ('zone_rules', 'addresses', 'geocoding_results')"));
        $migrations = DB::table('migrations')->count();
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertSame($migrations, DB::table('migrations')->count());
    }

    public function test_draft_defaults_explicit_settings_signed_priority_and_utc_are_preserved(): void
    {
        $country = $this->country();
        $market = $this->market($country, ['created_at' => '2026-10-10T03:00:00-05:00']);
        $zone = $this->zone($market);
        // Names and polygons may repeat; no new business uniqueness/overlap rule.
        $this->zone($market, ['priority' => 10]);
        $this->assertSame(2, DB::table('service_zones')->count());
        $storedMarket = DB::table('markets')->find($market);
        $storedZone = DB::table('service_zones')->find($zone);
        $this->assertTrue(Str::isUlid($storedMarket->public_id));
        $this->assertTrue(Str::isUlid($storedZone->public_id));
        $this->assertSame('draft', $storedMarket->status);
        $this->assertSame('draft', $storedZone->status);
        $this->assertSame('fixture', $storedZone->zone_type);
        $this->assertSame(1, $storedMarket->version);
        $this->assertSame(1, $storedZone->version);
        $this->assertSame(-10, $storedZone->priority);
        $this->assertSame('Etc/UTC', $storedMarket->timezone);
        $this->assertSame('XXX', $storedMarket->currency_code);
        $this->assertSame('ZZZ', DB::table('countries')->find($country)->currency_code);
        $this->assertSame('2026-10-10 08:00:00', DB::selectOne("SELECT to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') AS utc FROM markets WHERE id = ?", [$market])->utc);
        $geometry = DB::selectOne('SELECT ST_SRID(polygon::geometry) AS srid, ST_NDims(polygon::geometry) AS dimensions, GeometryType(polygon::geometry) AS type, ST_IsValid(polygon::geometry, 0) AS valid FROM service_zones WHERE id = ?', [$zone]);
        $this->assertSame(4326, $geometry->srid);
        $this->assertSame(2, $geometry->dimensions);
        $this->assertSame('MULTIPOLYGON', $geometry->type);
        $this->assertTrue($geometry->valid);
    }

    public function test_required_fields_codes_references_versions_and_activation_are_enforced(): void
    {
        foreach ([['public_id' => '123'], ['code' => 'zZ'], ['currency_code' => 'zzz'], ['name' => '   ']] as $changes) {
            $this->rejected(fn () => $this->country($changes), ['23514']);
        }
        foreach ([['public_id' => null], ['currency_code' => null], ['created_at' => null]] as $changes) {
            $this->rejected(fn () => $this->country($changes), ['23502']);
        }
        $country = $this->country();
        foreach ([['public_id' => '123'], ['name' => ' '], ['currency_code' => 'xxx'], ['timezone' => ' '], ['status' => 'active'], ['version' => 0], ['version' => -1]] as $changes) {
            $this->rejected(fn () => $this->market($country, $changes), ['23514']);
        }
        foreach ([['country_id' => null], ['currency_code' => null], ['timezone' => null]] as $changes) {
            $this->rejected(fn () => $this->market($country, $changes), ['23502']);
        }
        $market = $this->market($country);
        foreach ([['public_id' => '123'], ['name' => ' '], ['status' => 'active'], ['zone_type' => 'delivery'], ['version' => 0]] as $changes) {
            $this->rejected(fn () => $this->zone($market, $changes), ['23514']);
        }
        foreach ([['market_id' => null], ['priority' => null], ['polygon' => null]] as $changes) {
            $this->rejected(fn () => $this->zone($market, $changes), ['23502']);
        }
        $this->assertSame(1, DB::table('countries')->count());
        $this->assertSame(1, DB::table('markets')->count());
        $this->assertSame(0, DB::table('service_zones')->count());
    }

    public function test_unique_ids_country_codes_foreign_keys_and_restricted_deletes(): void
    {
        $country = $this->country();
        $market = $this->market($country);
        $zone = $this->zone($market);
        $this->rejected(fn () => $this->country(), ['23505']);
        $this->rejected(fn () => $this->country(['code' => 'YY', 'public_id' => DB::table('countries')->find($country)->public_id]), ['23505']);
        $this->rejected(fn () => $this->market($country, ['public_id' => DB::table('markets')->find($market)->public_id]), ['23505']);
        $this->rejected(fn () => $this->zone($market, ['public_id' => DB::table('service_zones')->find($zone)->public_id]), ['23505']);
        $this->rejected(fn () => $this->market(9223372036854775807), ['23503']);
        $this->rejected(fn () => $this->zone(9223372036854775807), ['23503']);
        $this->rejected(fn () => DB::table('countries')->where('id', $country)->delete(), ['23503']);
        $this->rejected(fn () => DB::table('markets')->where('id', $market)->delete(), ['23503']);
        foreach (['countries', 'markets', 'service_zones'] as $table) {
            $this->assertSame(1, DB::table($table)->count());
        }
    }

    public function test_spatial_constraints_reject_invalid_empty_wrong_type_srid_and_dimensions(): void
    {
        $market = $this->market($this->country());
        foreach (['SRID=4326;MULTIPOLYGON(((0 0,2 2,0 2,2 0,0 0)))', 'SRID=4326;MULTIPOLYGON EMPTY'] as $polygon) {
            $this->rejected(fn () => $this->zone($market, ['polygon' => $polygon]), ['23514']);
        }
        foreach (['SRID=4326;POINT(0 0)', 'SRID=4269;MULTIPOLYGON(((9 9,11 9,11 11,9 11,9 9)))', 'SRID=4326;MULTIPOLYGON Z (((9 9 1,11 9 1,11 11 1,9 11 1,9 9 1)))', 'SRID=4326;MULTIPOLYGON M (((9 9 1,11 9 1,11 11 1,9 11 1,9 9 1)))'] as $polygon) {
            $this->rejected(fn () => $this->zone($market, ['polygon' => $polygon]), ['22023']);
        }
        $this->assertSame(0, DB::table('service_zones')->count());
    }

    public function test_existing_probes_ignore_persisted_drafts_and_never_query_or_write_their_tables(): void
    {
        $this->zone($this->market($this->country()));
        $queries = [];
        $record = false;
        DB::listen(function (QueryExecuted $event) use (&$record, &$queries): void {
            if ($record) {
                $queries[] = $event->sql;
            }
        });
        $record = true;
        foreach ([[10, 10, 'outside', null], [3.5, 0.5, 'selected', '01ARZ3NDEKTSV4RRFFQ69G5FB0'], [5.5, 1.5, 'ambiguous', null]] as [$longitude, $latitude, $status, $zone]) {
            $this->getJson('/api/v1/marketplace/local-coverage-probe?'.http_build_query(compact('longitude', 'latitude')))->assertOk()->assertJsonPath('data.attributes', ['status' => $status, 'zone_id' => $zone]);
            $result = app(LocalCoverageProbe::class)->evaluate(new GeographicPoint($longitude, $latitude));
            $this->assertSame($status, $result->status);
            $this->assertSame($zone, $result->zonePublicId);
            $this->assertSame(0, Artisan::call('marketplace:local-coverage', ['longitude' => (string) $longitude, 'latitude' => (string) $latitude]));
            $body = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('local-coverage-v1', $body['fixture_version']);
            $this->assertSame($status, $body['status']);
            $this->assertSame($zone, $body['zone_id']);
        }
        $record = false;
        $this->assertCount(9, $queries);
        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/\b(countries|markets|service_zones|merchants|branches)\b/i', $sql);
        }
        foreach (['countries', 'markets', 'service_zones'] as $table) {
            $this->assertSame(1, DB::table($table)->count());
        }
    }

    public function test_forward_only_rollback_preserves_existing_rows(): void
    {
        $this->zone($this->market($this->country()));
        $migration = require database_path('migrations/2026_10_10_000003_create_marketplace_geographic_foundation.php');
        try {
            $migration->down();
            $this->fail('Unreviewed geographic rollback accepted.');
        } catch (RuntimeException $error) {
            $this->assertSame('Geographic foundation is forward-only; rollback requires a reviewed data plan.', $error->getMessage());
        }
        foreach (['countries', 'markets', 'service_zones'] as $table) {
            $this->assertSame(1, DB::table($table)->count());
        }
    }
}
