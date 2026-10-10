<?php

namespace App\Modules\Identity\Application\Authorization;

use App\Modules\Identity\Domain\Authorization\ResourceContext;
use App\Modules\Identity\Domain\Authorization\ResourceReference;

/** Implemented by the resource owner through its public contract. */
interface ResourceContextResolver
{
    public function resolve(ResourceReference $resource): ?ResourceContext;
}
