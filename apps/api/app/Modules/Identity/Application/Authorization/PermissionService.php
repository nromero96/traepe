<?php

namespace App\Modules\Identity\Application\Authorization;

use App\Modules\Identity\Domain\Authorization\Decision;
use App\Modules\Identity\Domain\Authorization\PermissionPolicy;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Domain\Authorization\Scope;
use DateTimeImmutable;

final readonly class PermissionService
{
    public function __construct(private AuthorizationDirectory $directory, private ResourceContextResolver $resources) {}

    public function decide(string $actorPublicId, string $capability, Scope $scope, ResourceReference $resource, DateTimeImmutable $now): Decision
    {
        if (! $this->directory->isActive($actorPublicId)) {
            return Decision::InactiveActor;
        }

        return (new PermissionPolicy)->evaluate(
            $actorPublicId, true, $capability, $scope, $resource,
            $this->resources->resolve($resource), $this->directory->rules($actorPublicId), $now,
        );
    }
}
