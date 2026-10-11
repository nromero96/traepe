<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftVariantAccess;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\ResourceContext;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Domain\Authorization\Scope;

final class LocalDraftVariantResourceResolver implements ResourceContextResolver
{
    public static function scope(): Scope
    {
        return new Scope('platform', LocalDraftVariantAccess::SCOPE_PUBLIC_ID);
    }

    public static function resource(string $capability): ResourceReference
    {
        $id = match ($capability) {
            LocalDraftVariantAccess::CREATE => LocalDraftVariantAccess::CREATE_RESOURCE,
            LocalDraftVariantAccess::CONTENT => LocalDraftVariantAccess::CONTENT_RESOURCE,
            LocalDraftVariantAccess::READ => LocalDraftVariantAccess::READ_RESOURCE,
            default => throw new \InvalidArgumentException('Unknown draft variant capability.'),
        };

        return new ResourceReference($capability, $id);
    }

    public function resolve(ResourceReference $resource): ?ResourceContext
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }
        foreach ([LocalDraftVariantAccess::CREATE, LocalDraftVariantAccess::CONTENT, LocalDraftVariantAccess::READ] as $capability) {
            if ($resource->matches(self::resource($capability))) {
                return new ResourceContext(self::resource($capability), self::scope());
            }
        }

        return null;
    }
}
