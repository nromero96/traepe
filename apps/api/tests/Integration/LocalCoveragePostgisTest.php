<?php

namespace Tests\Integration;

use App\Modules\Marketplace\Application\Coverage\LocalCoverageProbe;
use App\Modules\Marketplace\Application\Coverage\LocalZoneSource;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Infrastructure\Coverage\PostgisLocalZoneSource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

final class LocalCoveragePostgisTest extends PostgresTestCase
{
    public function test_fixture_geometries_are_valid_nonempty_2d_wgs84_polygons(): void
    {
        $this->assertInstanceOf(PostgisLocalZoneSource::class, app(LocalZoneSource::class));
        $this->assertCount(3, array_unique(array_column(PostgisLocalZoneSource::FIXTURES, 0)));
        foreach (PostgisLocalZoneSource::FIXTURES as $fixture) {
            $geometry = DB::selectOne('SELECT ST_IsValid(geom) AS valid, ST_IsEmpty(geom) AS empty, ST_SRID(geom) AS srid, ST_NDims(geom) AS dimensions, GeometryType(geom) AS type FROM (SELECT ST_GeomFromText(?, 4326) AS geom) AS fixture', [$fixture[2]]);
            $this->assertTrue($geometry->valid);
            $this->assertFalse($geometry->empty);
            $this->assertSame(4326, $geometry->srid);
            $this->assertSame(2, $geometry->dimensions);
            $this->assertSame('POLYGON', $geometry->type);
        }
    }

    public function test_real_postgis_covers_interiors_boundaries_and_holes_with_explicit_axes(): void
    {
        $probe = app(LocalCoverageProbe::class);
        $a = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';
        foreach ([[0.5, 0.5, $a], [0, 0, $a], [4, 3, $a], [1, 1.5, $a], [2.00001, 1.5, $a], [1.5, 1.5, null], [-1, -1, null], [1.5, 5.5, null]] as [$longitude, $latitude, $zone]) {
            $result = $probe->evaluate(new GeographicPoint($longitude, $latitude));
            $this->assertSame($zone, $result->zonePublicId);
            $this->assertSame($zone === null ? 'outside' : 'selected', $result->status);
        }
    }

    public function test_overlap_selects_maximum_and_equal_maxima_return_no_zone(): void
    {
        $probe = app(LocalCoverageProbe::class);
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FB0', $probe->evaluate(new GeographicPoint(3.5, 0.5))->zonePublicId);
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FB1', $probe->evaluate(new GeographicPoint(6.5, 2.5))->zonePublicId);
        foreach ([[5.5, 1.5], [6, 1.5]] as [$longitude, $latitude]) {
            $result = $probe->evaluate(new GeographicPoint($longitude, $latitude));
            $this->assertSame('ambiguous', $result->status);
            $this->assertNull($result->zonePublicId);
        }
    }

    public function test_console_emits_only_version_status_and_fixture_zone_without_writes(): void
    {
        $before = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
        foreach ([['0.5', '0.5', 'selected'], ['10', '10', 'outside'], ['5.5', '1.5', 'ambiguous']] as [$longitude, $latitude, $status]) {
            $this->assertSame(0, Artisan::call('marketplace:local-coverage', ['longitude' => $longitude, 'latitude' => $latitude, '--no-interaction' => true]));
            $body = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(['fixture_version', 'status', 'zone_id'], array_keys($body));
            $this->assertSame('local-coverage-v1', $body['fixture_version']);
            $this->assertSame($status, $body['status']);
            if ($status !== 'selected') {
                $this->assertNull($body['zone_id']);
            }
        }
        $this->assertEquals($before, DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"));
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('identity_audit')->count());
        foreach (['countries', 'markets', 'service_zones', 'merchants', 'branches'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }
}
