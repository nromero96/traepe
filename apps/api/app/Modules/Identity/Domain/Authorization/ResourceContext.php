<?php

namespace App\Modules\Identity\Domain\Authorization;

/** Resolved by the resource owner on the server, never hydrated from a client claim. */
final readonly class ResourceContext
{
    public function __construct(public ResourceReference $resource, public Scope $scope) {}
}
