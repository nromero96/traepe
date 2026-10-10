<?php

namespace App\Modules\Marketplace\Infrastructure\Fixtures;

use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\ResourceContext;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Domain\Authorization\Scope;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureAccess;

final class LocalDraftFixtureResourceResolver implements ResourceContextResolver
{
    public static function scope(): Scope
    {
        return new Scope('platform', LocalDraftFixtureAccess::SCOPE_PUBLIC_ID);
    }

    public static function resource(): ResourceReference
    {
        return new ResourceReference(LocalDraftFixtureAccess::CAPABILITY, LocalDraftFixtureAccess::RESOURCE_PUBLIC_ID);
    }

    public function resolve(ResourceReference $resource): ?ResourceContext
    {
        if (! app()->environment(['local', 'testing']) || ! $resource->matches(self::resource())) {
            return null;
        }

        return new ResourceContext(self::resource(), self::scope());
    }
}
