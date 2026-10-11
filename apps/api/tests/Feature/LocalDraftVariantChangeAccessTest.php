<?php

namespace Tests\Feature;

use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantChangeAccess;
use App\Modules\Catalog\Infrastructure\Drafts\IdentityLocalDraftVariantChangeAccess;
use App\Modules\Catalog\Infrastructure\Drafts\LocalDraftVariantChangeResourceResolver;
use App\Modules\Identity\Application\Authorization\AuthenticatedActorDirectory;
use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\PermissionEffect;
use App\Modules\Identity\Domain\Authorization\PermissionRule;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Domain\Authorization\Scope;
use DateTimeImmutable;
use Tests\TestCase;

final class LocalDraftVariantChangeAccessTest extends TestCase
{
    public function test_two_capabilities_resources_scope_and_old_permissions_are_independent(): void
    {
        $actor = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';
        $now = new DateTimeImmutable('2026-10-10T12:00:00Z');
        $scope = LocalDraftVariantChangeResourceResolver::scope();
        $actors = $this->mock(AuthenticatedActorDirectory::class);
        $directory = $this->mock(AuthorizationDirectory::class);
        $actors->shouldReceive('publicId')->with(11)->andReturn($actor);
        $directory->shouldReceive('isActive')->with($actor)->andReturn(true);
        $capabilities = [LocalDraftVariantChangeAccess::UPDATE, LocalDraftVariantChangeAccess::READ];
        foreach ($capabilities as $capability) {
            $allow = new PermissionRule($actor, $capability, $scope, PermissionEffect::Allow);
            $deny = new PermissionRule($actor, $capability, $scope, PermissionEffect::Deny);
            $expired = new PermissionRule($actor, $capability, $scope, PermissionEffect::Allow, $now);
            $otherScope = new PermissionRule($actor, $capability, new Scope('platform', $actor), PermissionEffect::Allow);
            foreach ([[[], null], [[$allow], $actor], [[$deny, $allow], null], [[$expired], null], [[$otherScope], null], [[new PermissionRule($actor, LocalDraftCatalogAccess::CREATE, $scope, PermissionEffect::Allow)], null]] as [$rules,$expected]) {
                $directory->shouldReceive('rules')->with($actor)->once()->andReturn($rules);
                $this->assertSame($expected, app(LocalDraftVariantChangeAccess::class)->actor(11, $capability, $now));
            }
            foreach (array_diff($capabilities, [$capability]) as $other) {
                $directory->shouldReceive('rules')->once()->andReturn([new PermissionRule($actor, $other, $scope, PermissionEffect::Allow)]);
                $this->assertNull(app(LocalDraftVariantChangeAccess::class)->actor(11, $capability, $now));
            }
            $resource = LocalDraftVariantChangeResourceResolver::resource($capability);
            $this->assertNotNull((new LocalDraftVariantChangeResourceResolver)->resolve($resource));
            $this->assertNull((new LocalDraftVariantChangeResourceResolver)->resolve(new ResourceReference($capability, $actor)));
            $this->assertNull(app(ResourceContextResolver::class)->resolve($resource));
        }
    }

    public function test_unknown_missing_blocked_and_nonlocal_actors_never_load_rules(): void
    {
        $actors = $this->mock(AuthenticatedActorDirectory::class);
        $directory = $this->mock(AuthorizationDirectory::class);
        $actors->shouldReceive('publicId')->with(11)->once()->andReturn(null);
        $actors->shouldReceive('publicId')->with(12)->once()->andReturn('01ARZ3NDEKTSV4RRFFQ69G5FAZ');
        $directory->shouldReceive('isActive')->once()->andReturn(false);
        $directory->shouldNotReceive('rules');
        $access = new IdentityLocalDraftVariantChangeAccess($actors, $directory);
        $this->assertNull($access->actor(11, 'unsupported', new DateTimeImmutable));
        $this->assertNull($access->actor(11, LocalDraftVariantChangeAccess::UPDATE, new DateTimeImmutable));
        $this->assertNull($access->actor(12, LocalDraftVariantChangeAccess::READ, new DateTimeImmutable));
        foreach (['production', 'staging'] as $env) {
            $this->app->detectEnvironment(fn () => $env);
            foreach ([LocalDraftVariantChangeAccess::UPDATE, LocalDraftVariantChangeAccess::READ] as $cap) {
                $this->assertNull($access->actor(11, $cap, new DateTimeImmutable));
                $this->assertNull((new LocalDraftVariantChangeResourceResolver)->resolve(LocalDraftVariantChangeResourceResolver::resource($cap)));
            }
        }
    }
}
