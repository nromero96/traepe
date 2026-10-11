<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftVariantChangeAccess;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\ResourceContext;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Domain\Authorization\Scope;

final class LocalDraftVariantChangeResourceResolver implements ResourceContextResolver
{
    public static function scope(): Scope
    {
        return new Scope('platform', LocalDraftVariantChangeAccess::SCOPE_PUBLIC_ID);
    }

    public static function resource(string $capability): ResourceReference
    {
        $id = match ($capability) {
            LocalDraftVariantChangeAccess::UPDATE => LocalDraftVariantChangeAccess::UPDATE_RESOURCE,
            LocalDraftVariantChangeAccess::READ => LocalDraftVariantChangeAccess::READ_RESOURCE,
            default => throw new \InvalidArgumentException('Unknown draft variant capability.'),
        };

        return new ResourceReference($capability, $id);
    }

    public function resolve(ResourceReference $resource): ?ResourceContext
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }
        foreach ([LocalDraftVariantChangeAccess::UPDATE, LocalDraftVariantChangeAccess::READ] as $capability) {
            if ($resource->matches(self::resource($capability))) {
                return new ResourceContext(self::resource($capability), self::scope());
            }
        }

        return null;
    }
}
