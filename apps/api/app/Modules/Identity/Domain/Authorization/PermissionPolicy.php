<?php

namespace App\Modules\Identity\Domain\Authorization;

use DateTimeImmutable;

final class PermissionPolicy
{
    /** @param iterable<PermissionRule> $rules */
    public function evaluate(
        string $actorPublicId,
        bool $active,
        string $capability,
        Scope $scope,
        ResourceReference $resource,
        ?ResourceContext $context,
        iterable $rules,
        DateTimeImmutable $now,
    ): Decision {
        if (! $active) {
            return Decision::InactiveActor;
        }
        if ($context === null || ! $context->resource->matches($resource)) {
            return Decision::UnknownResource;
        }
        if (! $context->scope->matches($scope)) {
            return Decision::ScopeMismatch;
        }
        $granted = false;
        foreach ($rules as $rule) {
            if (! $rule->applies($actorPublicId, $capability, $scope, $now)) {
                continue;
            }
            if ($rule->effect === PermissionEffect::Deny) {
                return Decision::ExplicitDeny;
            }
            $granted = true;
        }

        return $granted ? Decision::Allowed : Decision::NoGrant;
    }
}
