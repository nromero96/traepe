<?php

namespace Tests\Feature;

use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Infrastructure\Drafts\IdentityLocalDraftCatalogAccess;
use App\Modules\Catalog\Infrastructure\Drafts\LocalDraftCatalogResourceResolver;
use App\Modules\Catalog\Infrastructure\Drafts\MarketplaceLocalDraftMerchantSource;
use App\Modules\Identity\Application\Authorization\AuthenticatedActorDirectory;
use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\LocalAuthorizationProbe;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\PermissionEffect;
use App\Modules\Identity\Domain\Authorization\PermissionRule;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Marketplace\Application\Commerce\OwnedLocalDraftMerchantV1;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class LocalDraftCatalogAccessTest extends TestCase
{
    public function test_marketplace_bridge_uses_only_versioned_boolean_contract_and_guards_environment(): void
    {
        $merchant = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';
        $actor = '01ARZ3NDEKTSV4RRFFQ69G5FB0';
        $contract = $this->mock(OwnedLocalDraftMerchantV1::class);
        $contract->shouldReceive('lockOwnedDraft')->with($merchant, $actor)->once()->andReturn(true);
        $source = new MarketplaceLocalDraftMerchantSource($contract);
        $this->assertTrue($source->lockOwnedDraft($merchant, $actor));
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            try {
                $source->lockOwnedDraft($merchant, $actor);
                $this->fail('Non-local bridge access accepted.');
            } catch (HttpException $failure) {
                $this->assertSame(404, $failure->getStatusCode());
            }
        }
    }

    public function test_capabilities_resources_and_existing_global_resolver_remain_independent(): void
    {
        $actor = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';
        $now = new DateTimeImmutable('2026-10-10T12:00:00Z');
        $scope = LocalDraftCatalogResourceResolver::scope();
        $actors = $this->mock(AuthenticatedActorDirectory::class);
        $directory = $this->mock(AuthorizationDirectory::class);
        $actors->shouldReceive('publicId')->with(11)->andReturn($actor);
        $directory->shouldReceive('isActive')->with($actor)->andReturn(true);
        foreach ([LocalDraftCatalogAccess::CREATE, LocalDraftCatalogAccess::READ] as $capability) {
            $allow = new PermissionRule($actor, $capability, $scope, PermissionEffect::Allow);
            $deny = new PermissionRule($actor, $capability, $scope, PermissionEffect::Deny);
            $expired = new PermissionRule($actor, $capability, $scope, PermissionEffect::Allow, $now);
            $other = new PermissionRule($actor, $capability === LocalDraftCatalogAccess::CREATE ? LocalDraftCatalogAccess::READ : LocalDraftCatalogAccess::CREATE, $scope, PermissionEffect::Allow);
            foreach ([[[], null], [[$allow], $actor], [[$allow, $deny], null], [[$expired], null], [[$other], null]] as [$rules, $expected]) {
                $directory->shouldReceive('rules')->with($actor)->once()->andReturn($rules);
                $this->assertSame($expected, app(LocalDraftCatalogAccess::class)->actor(11, $capability, $now));
            }
            $reference = LocalDraftCatalogResourceResolver::resource($capability);
            $this->assertNotNull((new LocalDraftCatalogResourceResolver)->resolve($reference));
            $this->assertNull((new LocalDraftCatalogResourceResolver)->resolve(new ResourceReference($capability, $actor)));
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
        $access = app(LocalDraftCatalogAccess::class);
        $this->assertNull($access->actor(11, 'unsupported', new DateTimeImmutable));
        $this->assertNull($access->actor(11, LocalDraftCatalogAccess::CREATE, new DateTimeImmutable));
        $this->assertNull($access->actor(12, LocalDraftCatalogAccess::READ, new DateTimeImmutable));
    }

    public function test_adapter_and_own_resolver_deny_outside_local_before_directories(): void
    {
        $actors = $this->mock(AuthenticatedActorDirectory::class);
        $directory = $this->mock(AuthorizationDirectory::class);
        $actors->shouldNotReceive('publicId');
        $directory->shouldNotReceive('isActive', 'rules');
        $access = new IdentityLocalDraftCatalogAccess($actors, $directory);
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            foreach ([LocalDraftCatalogAccess::CREATE, LocalDraftCatalogAccess::READ] as $capability) {
                $this->assertNull($access->actor(11, $capability, new DateTimeImmutable));
                $this->assertNull((new LocalDraftCatalogResourceResolver)->resolve(LocalDraftCatalogResourceResolver::resource($capability)));
            }
        }
    }
}
