<?php

namespace App\Modules\Marketplace\Infrastructure\Commerce;

use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\ResourceContext;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Domain\Authorization\Scope;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceAccess;

final class LocalDraftCommerceResourceResolver implements ResourceContextResolver
{
    public static function scope(): Scope
    {
        return new Scope('platform', LocalDraftCommerceAccess::SCOPE_PUBLIC_ID);
    }

    public static function resource(string $capability): ResourceReference
    {
        return new ResourceReference($capability, $capability === LocalDraftCommerceAccess::CREATE
            ? LocalDraftCommerceAccess::CREATE_RESOURCE : LocalDraftCommerceAccess::READ_RESOURCE);
    }

    public function resolve(ResourceReference $resource): ?ResourceContext
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }
        foreach ([LocalDraftCommerceAccess::CREATE, LocalDraftCommerceAccess::READ] as $capability) {
            if ($resource->matches(self::resource($capability))) {
                return new ResourceContext(self::resource($capability), self::scope());
            }
        }

        return null;
    }
}
