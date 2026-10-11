<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\ResourceContext;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Domain\Authorization\Scope;

final class LocalDraftCatalogResourceResolver implements ResourceContextResolver
{
    public static function scope(): Scope
    {
        return new Scope('platform', LocalDraftCatalogAccess::SCOPE_PUBLIC_ID);
    }

    public static function resource(string $capability): ResourceReference
    {
        return new ResourceReference($capability, $capability === LocalDraftCatalogAccess::CREATE
            ? LocalDraftCatalogAccess::CREATE_RESOURCE : LocalDraftCatalogAccess::READ_RESOURCE);
    }

    public function resolve(ResourceReference $resource): ?ResourceContext
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }
        foreach ([LocalDraftCatalogAccess::CREATE, LocalDraftCatalogAccess::READ] as $capability) {
            if ($resource->matches(self::resource($capability))) {
                return new ResourceContext(self::resource($capability), self::scope());
            }
        }

        return null;
    }
}
