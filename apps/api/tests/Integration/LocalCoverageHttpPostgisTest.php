<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;

final class LocalCoverageHttpPostgisTest extends PostgresTestCase
{
    public function test_public_http_uses_real_postgis_without_creating_identity_or_business_data(): void
    {
        $before = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
        $a = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';
        $b = '01ARZ3NDEKTSV4RRFFQ69G5FB0';
        $c = '01ARZ3NDEKTSV4RRFFQ69G5FB1';
        foreach ([[0.5, 0.5, 'selected', $a], [0, 0, 'selected', $a], [1, 1.5, 'selected', $a], [1.5, 1.5, 'outside', null], [3.5, 0.5, 'selected', $b], [6.5, 2.5, 'selected', $c], [5.5, 1.5, 'ambiguous', null], [6, 1.5, 'ambiguous', null], [-1, -1, 'outside', null], [1.5, 5.5, 'outside', null]] as [$longitude, $latitude, $status, $zone]) {
            $response = $this->getJson('/api/v1/marketplace/local-coverage-probe?'.http_build_query(compact('longitude', 'latitude')));
            $response->assertOk()->assertJsonPath('data.attributes', ['status' => $status, 'zone_id' => $zone]);
            $this->assertSame(['status', 'zone_id'], array_keys($response->json('data.attributes')));
            $this->assertFalse($response->headers->has('Set-Cookie'));
        }
        $this->assertEquals($before, DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"));
        foreach (['users', 'identity_audit', 'identity_permission_grants'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        foreach (['countries', 'markets', 'service_zones'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertSame([], DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename IN ('merchants', 'branches')"));
    }
}
