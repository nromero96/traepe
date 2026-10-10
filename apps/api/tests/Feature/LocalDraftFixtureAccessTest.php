<?php

namespace Tests\Feature;

use App\Modules\Identity\Application\Authorization\AuthenticatedActorDirectory;
use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\LocalAuthorizationProbe;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\PermissionEffect;
use App\Modules\Identity\Domain\Authorization\PermissionRule;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Domain\Authorization\Scope;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureAccess;
use App\Modules\Marketplace\Infrastructure\Fixtures\IdentityLocalDraftFixtureAccess;
use App\Modules\Marketplace\Infrastructure\Fixtures\LocalDraftFixtureResourceResolver;
use DateTimeImmutable;
use Tests\TestCase;

final class LocalDraftFixtureAccessTest extends TestCase
{
    public function test_adapter_reuses_identity_policy_with_fixed_server_context(): void
    {
        $actor = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';
        $now = new DateTimeImmutable('2026-10-10T12:00:00Z');
        $scope = LocalDraftFixtureResourceResolver::scope();
        $allow = new PermissionRule($actor, LocalDraftFixtureAccess::CAPABILITY, $scope, PermissionEffect::Allow);
        $deny = new PermissionRule($actor, LocalDraftFixtureAccess::CAPABILITY, $scope, PermissionEffect::Deny);
        $expired = new PermissionRule($actor, LocalDraftFixtureAccess::CAPABILITY, $scope, PermissionEffect::Allow, $now);
        $wrongScope = new PermissionRule($actor, LocalDraftFixtureAccess::CAPABILITY, new Scope('branch', $scope->publicId), PermissionEffect::Allow);
        $wrongRead = new PermissionRule($actor, 'marketplace.local.persisted_coverage.read', $scope, PermissionEffect::Allow);
        $wrongCapability = new PermissionRule($actor, LocalAuthorizationProbe::CAPABILITY, $scope, PermissionEffect::Allow);
        $actors = $this->mock(AuthenticatedActorDirectory::class);
        $directory = $this->mock(AuthorizationDirectory::class);
        $actors->shouldReceive('publicId')->with(11)->andReturn($actor);
        $directory->shouldReceive('isActive')->with($actor)->andReturn(true);
        foreach ([[[], false], [[$allow], true], [[$allow, $deny], false], [[$deny, $allow], false], [[$expired], false], [[$wrongScope], false], [[$wrongCapability], false], [[$wrongRead], false]] as [$rules, $expected]) {
            $directory->shouldReceive('rules')->with($actor)->once()->andReturn($rules);
            $this->assertSame($expected ? $actor : null, app(LocalDraftFixtureAccess::class)->actor(11, $now));
        }
        $resolver = app(ResourceContextResolver::class);
        $this->assertNotNull($resolver->resolve(LocalAuthorizationProbe::resource()));
        $this->assertNull($resolver->resolve(LocalDraftFixtureResourceResolver::resource()));
        $own = new LocalDraftFixtureResourceResolver;
        $this->assertNotNull($own->resolve(LocalDraftFixtureResourceResolver::resource()));
        $this->assertNull($own->resolve(new ResourceReference('other.fixture', LocalDraftFixtureAccess::RESOURCE_PUBLIC_ID)));
        $this->assertNull($own->resolve(new ResourceReference('marketplace.local.draft_fixture.create', $actor)));
    }

    public function test_missing_or_inactive_actor_is_denied_before_rules(): void
    {
        $actors = $this->mock(AuthenticatedActorDirectory::class);
        $directory = $this->mock(AuthorizationDirectory::class);
        $actors->shouldReceive('publicId')->with(11)->once()->andReturn(null);
        $actors->shouldReceive('publicId')->with(12)->once()->andReturn('01ARZ3NDEKTSV4RRFFQ69G5FAZ');
        $directory->shouldReceive('isActive')->once()->andReturn(false);
        $directory->shouldNotReceive('rules');
        $access = app(LocalDraftFixtureAccess::class);
        $this->assertNull($access->actor(11, new DateTimeImmutable));
        $this->assertNull($access->actor(12, new DateTimeImmutable));
    }

    public function test_adapter_and_resolver_deny_other_environments_before_directory_access(): void
    {
        $actors = $this->mock(AuthenticatedActorDirectory::class);
        $directory = $this->mock(AuthorizationDirectory::class);
        $actors->shouldNotReceive('publicId');
        $directory->shouldNotReceive('isActive', 'rules');
        $access = new IdentityLocalDraftFixtureAccess($actors, $directory);
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            $this->assertNull($access->actor(11, new DateTimeImmutable));
            $this->assertNull((new LocalDraftFixtureResourceResolver)->resolve(LocalDraftFixtureResourceResolver::resource()));
        }
    }
}
