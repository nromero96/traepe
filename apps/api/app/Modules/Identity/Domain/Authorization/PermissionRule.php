<?php

namespace App\Modules\Identity\Domain\Authorization;

use DateTimeImmutable;
use InvalidArgumentException;

/** Role permissions are supplied as Allow rules with their assignment's exact scope. */
final readonly class PermissionRule
{
    public function __construct(
        public string $actorPublicId,
        public string $capability,
        public Scope $scope,
        public PermissionEffect $effect,
        public ?DateTimeImmutable $expiresAt = null,
    ) {
        if (! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $actorPublicId)
            || ! preg_match('/^[a-z][a-z0-9_.-]{0,127}$/D', $capability)) {
            throw new InvalidArgumentException('Invalid permission rule.');
        }
    }

    public function applies(string $actor, string $capability, Scope $scope, DateTimeImmutable $now): bool
    {
        return $this->actorPublicId === $actor && $this->capability === $capability
            && $this->scope->matches($scope) && ($this->expiresAt === null || $now < $this->expiresAt);
    }
}
