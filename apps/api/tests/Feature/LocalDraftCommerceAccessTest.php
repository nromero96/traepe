<?php

namespace Tests\Feature;

use App\Modules\Identity\Application\Authorization\AuthenticatedActorDirectory;
use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\LocalAuthorizationProbe;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\PermissionEffect;
use App\Modules\Identity\Domain\Authorization\PermissionRule;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceAccess;
use App\Modules\Marketplace\Infrastructure\Commerce\IdentityLocalDraftCommerceAccess;
use App\Modules\Marketplace\Infrastructure\Commerce\LocalDraftCommerceResourceResolver;
use DateTimeImmutable;
use Tests\TestCase;

final class LocalDraftCommerceAccessTest extends TestCase
{
    public function test_capabilities_resources_and_existing_global_resolver_remain_independent(): void
    {
        $actor = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';
        $now = new DateTimeImmutable('2026-10-10T12:00:00Z');
        $scope = LocalDraftCommerceResourceResolver::scope();
        $actors = $this->mock(AuthenticatedActorDirectory::class);
        $directory = $this->mock(AuthorizationDirectory::class);
        $actors->shouldReceive('publicId')->with(11)->andReturn($actor);
        $directory->shouldReceive('isActive')->with($actor)->andReturn(true);
        foreach ([LocalDraftCommerceAccess::CREATE, LocalDraftCommerceAccess::READ] as $capability) {
            $allow = new PermissionRule($actor, $capability, $scope, PermissionEffect::Allow);
            $deny = new PermissionRule($actor, $capability, $scope, PermissionEffect::Deny);
            $expired = new PermissionRule($actor, $capability, $scope, PermissionEffect::Allow, $now);
            $other = new PermissionRule($actor, $capability === LocalDraftCommerceAccess::CREATE ? LocalDraftCommerceAccess::READ : LocalDraftCommerceAccess::CREATE, $scope, PermissionEffect::Allow);
            foreach ([[[], null], [[$allow], $actor], [[$allow, $deny], null], [[$expired], null], [[$other], null]] as [$rules, $expected]) {
                $directory->shouldReceive('rules')->with($actor)->once()->andReturn($rules);
                $this->assertSame($expected, app(LocalDraftCommerceAccess::class)->actor(11, $capability, $now));
            }
            $reference = LocalDraftCommerceResourceResolver::resource($capability);
            $this->assertNotNull((new LocalDraftCommerceResourceResolver)->resolve($reference));
            $this->assertNull((new LocalDraftCommerceResourceResolver)->resolve(new ResourceReference($capability, $actor)));
            $this->assertNull(app(ResourceContextResolver::class)->resolve($reference));
        }
        $this->assertNotNull(app(ResourceContextResolver::class)->resolve(LocalAuthorizationProbe::resource()));
    }

    public function test_unknown_capability_missing_or_inactive_actor_never_loads_rules(): void
    {
        $actors = $this->mock(AuthenticatedActorDirectory::class);
        $directory = $this->mock(AuthorizationDirectory::class);
        $actors->shouldReceive('publicId')->with(11)->once()->andReturn(null);
        $actors->shouldReceive('publicId')->with(12)->once()->andReturn('01ARZ3NDEKTSV4RRFFQ69G5FAZ');
        $directory->shouldReceive('isActive')->once()->andReturn(false);
        $directory->shouldNotReceive('rules');
        $access = app(LocalDraftCommerceAccess::class);
        $this->assertNull($access->actor(11, 'unsupported', new DateTimeImmutable));
        $this->assertNull($access->actor(11, LocalDraftCommerceAccess::CREATE, new DateTimeImmutable));
        $this->assertNull($access->actor(12, LocalDraftCommerceAccess::READ, new DateTimeImmutable));
    }

    public function test_adapter_and_own_resolver_deny_outside_local_before_directories(): void
    {
        $actors = $this->mock(AuthenticatedActorDirectory::class);
        $directory = $this->mock(AuthorizationDirectory::class);
        $actors->shouldNotReceive('publicId');
        $directory->shouldNotReceive('isActive', 'rules');
        $access = new IdentityLocalDraftCommerceAccess($actors, $directory);
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            foreach ([LocalDraftCommerceAccess::CREATE, LocalDraftCommerceAccess::READ] as $capability) {
                $this->assertNull($access->actor(11, $capability, new DateTimeImmutable));
                $this->assertNull((new LocalDraftCommerceResourceResolver)->resolve(LocalDraftCommerceResourceResolver::resource($capability)));
            }
        }
    }
}
