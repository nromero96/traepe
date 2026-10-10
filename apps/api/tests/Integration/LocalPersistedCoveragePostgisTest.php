<?php

namespace Tests\Integration;

use App\Modules\Marketplace\Application\Coverage\LocalCoverageProbe;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageProbe;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LocalPersistedCoveragePostgisTest extends PostgresTestCase
{
    private function market(): array
    {
        $country = DB::table('countries')->value('id');
        if ($country === null) {
            $country = DB::table('countries')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ']);
        }
        $publicId = (string) Str::ulid();
        $id = DB::table('markets')->insertGetId(['public_id' => $publicId, 'country_id' => $country, 'name' => 'Synthetic Market', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']);

        return ['id' => $id, 'public_id' => $publicId];
    }

    private function zone(array $market, string $polygon, int $priority): string
    {
        $publicId = (string) Str::ulid();
        DB::table('service_zones')->insert(['public_id' => $publicId, 'market_id' => $market['id'], 'name' => 'Synthetic Zone', 'polygon' => $polygon, 'priority' => $priority]);

        return $publicId;
    }

    private function snapshot(): array
    {
        $hashes = [];
        foreach (['countries', 'markets', 'service_zones'] as $table) {
            $hashes[$table] = hash('sha256', json_encode(DB::table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR));
        }

        return $hashes;
    }

    private function assertProbe(string $market, float $longitude, float $latitude, string $status, ?string $zone): void
    {
        $result = app(LocalPersistedCoverageProbe::class)->evaluate(new MarketPublicId($market), new GeographicPoint($longitude, $latitude));
        $this->assertSame($status, $result->status);
        $this->assertSame($zone, $result->zonePublicId);
        $this->assertSame(0, Artisan::call('marketplace:local-persisted-coverage', ['market_public_id' => $market, 'longitude' => (string) $longitude, 'latitude' => (string) $latitude]));
        $this->assertSame(['fixture_version' => 'local-persisted-coverage-v1', 'status' => $status, 'zone_id' => $zone], json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_missing_market_and_known_market_without_zones_are_distinct_without_writes(): void
    {
        $before = $this->snapshot();
        $this->assertProbe((string) Str::ulid(), 0, 0, 'market_not_found', null);
        $this->assertSame($before, $this->snapshot());
        $market = $this->market();
        $before = $this->snapshot();
        $this->assertProbe($market['public_id'], 0, 0, 'outside', null);
        $this->assertSame($before, $this->snapshot());
        foreach (['users', 'identity_audit', 'identity_permission_grants'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }

    public function test_native_geography_covers_boundaries_holes_components_and_explicit_axes(): void
    {
        $market = $this->market();
        $a = $this->zone($market, 'SRID=4326;MULTIPOLYGON(((0 0,4 0,4 4,0 4,0 0),(1 1,1 2,2 2,2 1,1 1)),((20 0,24 0,24 4,20 4,20 0)))', -10);
        $b = $this->zone($market, 'SRID=4326;MULTIPOLYGON(((3 0,6 0,6 2,3 2,3 0)))', 20);
        $before = $this->snapshot();
        foreach ([[0.5, 0.5, 'selected', $a], [20.5, 0.5, 'selected', $a], [10, 10, 'outside', null], [0, 1.5, 'selected', $a], [0, 0, 'selected', $a], [1.5, 1.5, 'outside', null], [1, 1.5, 'selected', $a], [0.999999, 1.5, 'selected', $a], [1.000001, 1.5, 'outside', null], [6, 1, 'selected', $b], [1, 6, 'outside', null], [3.5, 0.5, 'selected', $b]] as [$longitude, $latitude, $status, $zone]) {
            $this->assertProbe($market['public_id'], $longitude, $latitude, $status, $zone);
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_priority_and_ties_never_include_another_markets_zones(): void
    {
        $market = $this->market();
        $other = $this->market();
        $polygon = 'SRID=4326;MULTIPOLYGON(((9 9,11 9,11 11,9 11,9 9)))';
        $a = $this->zone($market, $polygon, 10);
        $this->zone($other, $polygon, 999);
        $this->zone($other, $polygon, 999);
        $this->assertProbe($market['public_id'], 10, 10, 'selected', $a);
        $b = $this->zone($market, $polygon, 20);
        $this->assertProbe($market['public_id'], 10, 10, 'selected', $b);
        $this->zone($market, $polygon, 20);
        $this->assertProbe($market['public_id'], 10, 10, 'ambiguous', null);
        $c = $this->zone($market, $polygon, 30);
        $before = $this->snapshot();
        $this->assertProbe($market['public_id'], 10, 10, 'selected', $c);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_geodetic_edges_are_evaluated_natively_instead_of_casting_to_planar_geometry(): void
    {
        $market = $this->market();
        $zone = $this->zone($market, 'SRID=4326;MULTIPOLYGON(((0 10,10 10,10 20,0 20,0 10)))', 10);
        $comparison = DB::selectOne('SELECT ST_Covers(polygon::geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326)) AS planar FROM service_zones WHERE public_id = ?', [5, 10.02, $zone]);
        $this->assertTrue($comparison->planar);
        $this->assertProbe($market['public_id'], 5, 10.02, 'outside', null);
        $this->assertProbe($market['public_id'], 5, 15, 'selected', $zone);
        $this->assertProbe($market['public_id'], 0, 15, 'selected', $zone);
    }

    public function test_single_bound_read_is_scoped_and_existing_probes_still_ignore_persisted_drafts(): void
    {
        $market = $this->market();
        $zone = $this->zone($market, 'SRID=4326;MULTIPOLYGON(((9 9,11 9,11 11,9 11,9 9)))', 10);
        $before = $this->snapshot();
        $queries = [];
        $record = true;
        DB::listen(function (QueryExecuted $event) use (&$record, &$queries): void {
            if ($record) {
                $queries[] = $event;
            }
        });
        $result = app(LocalPersistedCoverageProbe::class)->evaluate(new MarketPublicId($market['public_id']), new GeographicPoint(10, 10));
        $record = false;
        $this->assertSame($zone, $result->zonePublicId);
        $this->assertCount(1, $queries);
        $this->assertSame([10.0, 10.0, $market['public_id']], $queries[0]->bindings);
        $this->assertDoesNotMatchRegularExpression('/\b(INSERT|UPDATE|DELETE|ALTER|CREATE|DROP)\b/i', $queries[0]->sql);
        $this->assertDoesNotMatchRegularExpression('/\b(users|identity_\w+|technical_\w+|countries)\b/i', $queries[0]->sql);
        $this->assertSame('outside', app(LocalCoverageProbe::class)->evaluate(new GeographicPoint(10, 10))->status);
        $this->getJson('/api/v1/marketplace/local-coverage-probe?longitude=10&latitude=10')
            ->assertOk()->assertJsonPath('data.attributes', ['status' => 'outside', 'zone_id' => null])->assertJsonPath('data.id', 'local-coverage-v1');
        $this->assertSame(0, Artisan::call('marketplace:local-coverage', ['longitude' => '10', 'latitude' => '10']));
        $this->assertSame(['fixture_version' => 'local-coverage-v1', 'status' => 'outside', 'zone_id' => null], json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame($before, $this->snapshot());
    }
}
