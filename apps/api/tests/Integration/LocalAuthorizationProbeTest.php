<?php

namespace Tests\Integration;

use App\Modules\Identity\Application\Authorization\LocalAuthorizationProbe;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Infrastructure\IdentityUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LocalAuthorizationProbeTest extends PostgresTestCase
{
    private const URL = '/api/v1/identity/local-authorization-probe';

    private function actor(): IdentityUser
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => '', 'status' => 'active']);

        return IdentityUser::query()->findOrFail($id);
    }

    private function grant(IdentityUser $user, string $effect = 'allow', ?string $scopeId = null, ?string $expires = null, string $scopeType = 'platform'): void
    {
        $permission = DB::table('identity_permissions')->where('code', LocalAuthorizationProbe::CAPABILITY)->value('id');
        if ($permission === null) {
            $permission = DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => LocalAuthorizationProbe::CAPABILITY]);
        }
        DB::table('identity_permission_grants')->insert([
            'public_id' => (string) Str::ulid(), 'user_id' => $user->getKey(), 'permission_id' => $permission,
            'scope_type' => $scopeType, 'scope_public_id' => $scopeId ?? LocalAuthorizationProbe::SCOPE_PUBLIC_ID,
            'effect' => $effect, 'expires_at' => $expires,
        ]);
    }

    public function test_anonymous_and_authenticated_without_grants_are_denied(): void
    {
        $this->getJson(self::URL)->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
        $this->actingAs($this->actor(), 'web')->getJson(self::URL)->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->assertSame(0, DB::table('identity_permission_grants')->count());
    }

    public function test_exact_server_resolved_permission_allows_only_the_local_fixture(): void
    {
        $user = $this->actor();
        $this->grant($user);
        $this->actingAs($user, 'web')->getJson(self::URL)
            ->assertOk()->assertJsonPath('data.id', LocalAuthorizationProbe::RESOURCE_PUBLIC_ID)
            ->assertJsonPath('data.attributes.allowed', true)
            ->assertJsonMissingPath('data.attributes.user_id')
            ->assertJsonMissingPath('data.attributes.phone');
        $resolver = app(ResourceContextResolver::class);
        $this->assertNull($resolver->resolve(new ResourceReference('other.fixture', LocalAuthorizationProbe::RESOURCE_PUBLIC_ID)));
        $this->assertNull($resolver->resolve(new ResourceReference('identity.local.probe', (string) Str::ulid())));
    }

    public function test_deny_blocking_and_expiry_are_rechecked_on_each_http_request(): void
    {
        $user = $this->actor();
        $this->grant($user);
        $this->actingAs($user, 'web')->getJson(self::URL)->assertOk();
        $this->grant($user, 'deny', expires: '2999-01-01T00:00:00Z');
        $this->getJson(self::URL)->assertForbidden();
        // Test-owned fixture only: production grant mutations have no API in this checkpoint.
        DB::table('identity_permission_grants')->where('effect', 'deny')->update(['expires_at' => '2000-01-01T00:00:00Z']);
        $this->getJson(self::URL)->assertOk();
        DB::table('users')->where('id', $user->getKey())->update(['status' => 'blocked']);
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_client_cannot_choose_another_actor_scope_resource_or_time(): void
    {
        $user = $this->actor();
        $other = $this->actor();
        $otherScope = (string) Str::ulid();
        $this->grant($other);
        $this->grant($user, scopeId: $otherScope);
        $query = http_build_query([
            'actor_id' => $other->public_id, 'scope_id' => $otherScope, 'scope_type' => 'branch',
            'resource_id' => (string) Str::ulid(), 'capability' => '*', 'now' => '1999-01-01T00:00:00Z',
        ]);
        $this->actingAs($user, 'web')->getJson(self::URL.'?'.$query)->assertForbidden();
        $this->grant($user, expires: '2000-01-01T00:00:00Z');
        $this->getJson(self::URL.'?'.$query)->assertForbidden();
        $this->grant($user, scopeType: 'branch');
        $this->getJson(self::URL.'?'.$query)->assertForbidden();
    }

    public function test_runtime_production_guard_denies_even_a_cached_local_route(): void
    {
        $user = $this->actor();
        $this->grant($user);
        $this->actingAs($user, 'web');
        $this->app->detectEnvironment(fn () => 'production');
        $this->getJson(self::URL)->assertNotFound();
        $this->assertNull(app(ResourceContextResolver::class)->resolve(LocalAuthorizationProbe::resource()));
    }

    public function test_production_denies_before_authentication_or_resolving_unbound_services(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        unset($this->app[ResourceContextResolver::class]);
        $this->getJson(self::URL)->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }

    public function test_route_enforces_its_request_limit(): void
    {
        $this->actingAs($this->actor(), 'web');
        for ($i = 0; $i < 30; $i++) {
            $this->getJson(self::URL)->assertForbidden();
        }
        $this->getJson(self::URL)->assertStatus(429)->assertJsonPath('error.code', 'rate_limited')->assertHeader('Retry-After');
    }
}
