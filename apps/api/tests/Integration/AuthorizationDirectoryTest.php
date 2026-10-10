<?php

namespace Tests\Integration;

use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\PermissionService;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\Decision;
use App\Modules\Identity\Domain\Authorization\PermissionEffect;
use App\Modules\Identity\Domain\Authorization\ResourceContext;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Domain\Authorization\Scope;
use App\Modules\Identity\Infrastructure\Authorization\PostgresAuthorizationDirectory;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AuthorizationDirectoryTest extends PostgresTestCase
{
    private function actor(): array
    {
        $publicId = (string) Str::ulid();
        $id = DB::table('users')->insertGetId(['public_id' => $publicId, 'name' => '', 'status' => 'active']);

        return [$id, $publicId];
    }

    private function fixture(int $userId, string $scope): int
    {
        $role = DB::table('identity_roles')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'local.fixture.role']);
        $permission = DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'identity.local.probe']);
        DB::table('identity_role_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        DB::table('identity_role_assignments')->insert([
            'public_id' => (string) Str::ulid(), 'user_id' => $userId, 'role_id' => $role, 'scope_type' => 'branch', 'scope_public_id' => $scope,
        ]);

        return $permission;
    }

    private function grant(int $userId, int $permission, string $scope, string $effect, ?string $expires = null): void
    {
        DB::table('identity_permission_grants')->insert([
            'public_id' => (string) Str::ulid(), 'user_id' => $userId, 'permission_id' => $permission,
            'scope_type' => 'branch', 'scope_public_id' => $scope, 'effect' => $effect, 'expires_at' => $expires,
        ]);
    }

    public function test_container_binding_empty_directory_and_actor_state(): void
    {
        $directory = app(AuthorizationDirectory::class);
        $this->assertInstanceOf(PostgresAuthorizationDirectory::class, $directory);
        [$id, $publicId] = $this->actor();
        $this->assertTrue($directory->isActive($publicId));
        $this->assertSame([], $directory->rules($publicId));
        $this->assertFalse($directory->isActive((string) Str::ulid()));
        DB::table('users')->where('id', $id)->update(['status' => 'blocked']);
        $this->assertFalse($directory->isActive($publicId));
        $this->assertSame([], $directory->rules($publicId));
    }

    public function test_role_and_explicit_deny_are_evaluated_by_the_real_service(): void
    {
        [$id, $publicId] = $this->actor();
        $scopeId = (string) Str::ulid();
        $permission = $this->fixture($id, $scopeId);
        $scope = new Scope('branch', $scopeId);
        $resource = new ResourceReference('local.fixture', (string) Str::ulid());
        $resolver = $this->createMock(ResourceContextResolver::class);
        $resolver->method('resolve')->willReturn(new ResourceContext($resource, $scope));
        $service = new PermissionService(app(AuthorizationDirectory::class), $resolver);
        $now = new DateTimeImmutable('2030-01-01T00:00:00Z');
        $this->assertSame(Decision::Allowed, $service->decide($publicId, 'identity.local.probe', $scope, $resource, $now));
        $this->grant($id, $permission, $scopeId, 'deny', '2030-01-01T00:00:01Z');
        $this->assertSame(Decision::ExplicitDeny, $service->decide($publicId, 'identity.local.probe', $scope, $resource, $now));
        $this->assertSame(Decision::Allowed, $service->decide($publicId, 'identity.local.probe', $scope, $resource, new DateTimeImmutable('2030-01-01T00:00:01Z')));
        DB::table('users')->where('id', $id)->update(['status' => 'blocked']);
        $this->assertSame(Decision::InactiveActor, $service->decide($publicId, 'identity.local.probe', $scope, $resource, $now));
        $this->assertSame([], app(AuthorizationDirectory::class)->rules($publicId));
    }

    public function test_directory_never_reads_another_users_roles_or_grants(): void
    {
        [$owner, $ownerPublic] = $this->actor();
        [$other, $otherPublic] = $this->actor();
        $scope = (string) Str::ulid();
        $permission = $this->fixture($owner, $scope);
        $this->grant($other, $permission, $scope, 'deny');
        $directory = app(AuthorizationDirectory::class);
        $ownerRules = $directory->rules($ownerPublic);
        $otherRules = $directory->rules($otherPublic);
        $this->assertCount(1, $ownerRules);
        $this->assertCount(1, $otherRules);
        $this->assertSame(PermissionEffect::Allow, $ownerRules[0]->effect);
        $this->assertSame(PermissionEffect::Deny, $otherRules[0]->effect);
        $this->assertSame($ownerPublic, $ownerRules[0]->actorPublicId);
        $this->assertSame($otherPublic, $otherRules[0]->actorPublicId);
        $this->assertSame([], $directory->rules((string) Str::ulid()));
    }

    public function test_database_rejects_wildcard_and_invalid_grant_effect(): void
    {
        try {
            DB::table('identity_permissions')->insert(['public_id' => (string) Str::ulid(), 'code' => '*']);
            $this->fail('Wildcard accepted.');
        } catch (QueryException $error) {
            $this->assertSame('23514', $error->errorInfo[0]);
        }
        [$id] = $this->actor();
        $scope = (string) Str::ulid();
        $permission = $this->fixture($id, $scope);
        try {
            $this->grant($id, $permission, $scope, 'invalid');
            $this->fail('Invalid effect accepted.');
        } catch (QueryException $error) {
            $this->assertSame('23514', $error->errorInfo[0]);
        }
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
    }

    public function test_assignment_integrity_rejects_unknown_actor_scope_and_duplicate(): void
    {
        [$id] = $this->actor();
        $scope = (string) Str::ulid();
        $this->fixture($id, $scope);
        $existing = (array) DB::table('identity_role_assignments')->first();
        unset($existing['id']);
        foreach ([
            [['user_id' => 9223372036854775807], '23503'],
            [['scope_type' => 'wildcard'], '23514'],
            [['scope_public_id' => '123'], '23514'],
            [[], '23505'],
        ] as [$changes, $state]) {
            try {
                DB::table('identity_role_assignments')->insert(array_replace($existing, ['public_id' => (string) Str::ulid()], $changes));
                $this->fail('Invalid assignment accepted.');
            } catch (QueryException $error) {
                $this->assertSame($state, $error->errorInfo[0]);
            }
        }
    }
}
