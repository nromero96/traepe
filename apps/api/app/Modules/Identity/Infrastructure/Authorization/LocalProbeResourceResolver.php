<?php

namespace App\Modules\Identity\Infrastructure\Authorization;

use App\Modules\Identity\Application\Authorization\LocalAuthorizationProbe;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\ResourceContext;
use App\Modules\Identity\Domain\Authorization\ResourceReference;

final class LocalProbeResourceResolver implements ResourceContextResolver
{
    public function resolve(ResourceReference $resource): ?ResourceContext
    {
        if (! app()->environment(['local', 'testing']) || ! $resource->matches(LocalAuthorizationProbe::resource())) {
            return null;
        }

        return new ResourceContext(LocalAuthorizationProbe::resource(), LocalAuthorizationProbe::scope());
    }
}
