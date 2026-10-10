<?php

namespace Tests\Integration;

use App\Modules\Identity\Application\Authorization\LocalAuthorizationProbe;
use App\Modules\Identity\Infrastructure\IdentityUser;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageAccess;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LocalPersistedCoverageHttpPostgisTest extends PostgresTestCase
{
    private const URL = '/api/v1/marketplace/local-persisted-coverage-probe';

    private function actor(): IdentityUser
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => '', 'status' => 'active']);

        return IdentityUser::query()->findOrFail($id);
    }

    private function grant(IdentityUser $user, string $effect = 'allow', string $capability = LocalPersistedCoverageAccess::CAPABILITY, string $scopeType = 'platform', ?string $scopeId = null, ?string $expires = null): void
    {
        $permission = DB::table('identity_permissions')->where('code', $capability)->value('id') ?? DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => $capability]);
        DB::table('identity_permission_grants')->insert(['public_id' => (string) Str::ulid(), 'user_id' => $user->getKey(), 'permission_id' => $permission, 'scope_type' => $scopeType, 'scope_public_id' => $scopeId ?? LocalPersistedCoverageAccess::SCOPE_PUBLIC_ID, 'effect' => $effect, 'expires_at' => $expires]);
    }

    private function market(): array
    {
        $country = DB::table('countries')->value('id') ?? DB::table('countries')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ']);
        $publicId = (string) Str::ulid();
        $id = DB::table('markets')->insertGetId(['public_id' => $publicId, 'country_id' => $country, 'name' => 'Synthetic Market', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']);

        return ['id' => $id, 'public_id' => $publicId];
    }

    private function zone(array $market, string $polygon, int $priority): string
    {
        $publicId = (string) Str::ulid();
        DB::insert('INSERT INTO service_zones (public_id, market_id, name, zone_type, polygon, priority) VALUES (?, ?, ?, ?, ST_GeogFromText(?), ?)', [$publicId, $market['id'], 'Synthetic Zone', 'fixture', $polygon, $priority]);

        return $publicId;
    }

    private function read(string $market, float $longitude, float $latitude, string $status, ?string $zone): void
    {
        $this->getJson(self::URL.'?'.http_build_query(['market_public_id' => $market, 'longitude' => $longitude, 'latitude' => $latitude]))->assertOk()->assertJsonPath('data.attributes', ['status' => $status, 'zone_id' => $zone]);
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['countries', 'markets', 'service_zones', 'users', 'identity_permissions', 'identity_permission_grants', 'identity_role_assignments', 'identity_audit'] as $table) {
            $result[$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->toJson());
        }

        return $result;
    }

    public function test_native_coverage_is_scoped_and_read_only_for_all_four_results(): void
    {
        $user = $this->actor();
        $this->grant($user);
        $this->actingAs($user, 'web');
        $market = $this->market();
        $other = $this->market();
        $this->zone($other, 'SRID=4326;MULTIPOLYGON(((0 0,4 0,4 4,0 4,0 0)))', 999);
        $this->read((string) Str::ulid(), 0.5, 0.5, 'market_not_found', null);
        $this->read($market['public_id'], 0.5, 0.5, 'outside', null);
        $a = $this->zone($market, 'SRID=4326;MULTIPOLYGON(((0 0,4 0,4 4,0 4,0 0),(1 1,1 2,2 2,2 1,1 1)))', 10);
        $before = $this->snapshot();
        foreach ([[0.5, 0.5, 'selected', $a], [1, 1.5, 'selected', $a], [1.5, 1.5, 'outside', null], [1.000001, 1.5, 'outside', null], [0.999999, 1.5, 'selected', $a], [1, 6, 'outside', null]] as [$longitude, $latitude, $status, $zone]) {
            $this->read($market['public_id'], $longitude, $latitude, $status, $zone);
        }
        $this->assertSame($before, $this->snapshot());
        $this->zone($market, 'SRID=4326;MULTIPOLYGON(((0 0,4 0,4 4,0 4,0 0)))', 10);
        $before = $this->snapshot();
        $this->read($market['public_id'], 0.5, 0.5, 'ambiguous', null);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_identity_denies_wrong_capability_scope_expiry_deny_and_blocking_before_geographic_queries(): void
    {
        $user = $this->actor();
        $this->actingAs($user, 'web');
        $queries = [];
        DB::listen(function (QueryExecuted $event) use (&$queries): void {
            if (preg_match('/\b(markets|service_zones)\b/i', $event->sql)) {
                $queries[] = $event->sql;
            }
        });
        $query = '?market_public_id=invalid&longitude=invalid&actor_id=other&scope_type=platform&capability=*&resource_id=other&now=1999-01-01';
        $this->getJson(self::URL.$query)->assertForbidden();
        $this->grant($user, capability: LocalAuthorizationProbe::CAPABILITY);
        $this->getJson(self::URL.$query)->assertForbidden();
        $this->grant($user, scopeType: 'branch');
        $this->getJson(self::URL.$query)->assertForbidden();
        $this->grant($user, scopeId: (string) Str::ulid());
        $this->getJson(self::URL.$query)->assertForbidden();
        $this->grant($user, expires: '2000-01-01T00:00:00Z');
        $this->getJson(self::URL.$query)->assertForbidden();
        $this->grant($user);
        $this->getJson(self::URL.$query)->assertUnprocessable();
        $this->grant($user, effect: 'deny', expires: '2999-01-01T00:00:00Z');
        $this->getJson(self::URL.$query)->assertForbidden();
        DB::table('identity_permission_grants')->where('effect', 'deny')->update(['expires_at' => '2000-01-01T00:00:00Z']);
        DB::table('users')->where('id', $user->getKey())->update(['status' => 'blocked']);
        $this->getJson(self::URL.$query)->assertForbidden();
        $this->assertSame([], $queries);
    }
}
