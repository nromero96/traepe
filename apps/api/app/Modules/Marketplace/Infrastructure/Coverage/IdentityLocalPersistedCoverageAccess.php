<?php

namespace App\Modules\Marketplace\Infrastructure\Coverage;

use App\Modules\Identity\Application\Authorization\AuthenticatedActorDirectory;
use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\PermissionService;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageAccess;
use DateTimeImmutable;

final readonly class IdentityLocalPersistedCoverageAccess implements LocalPersistedCoverageAccess
{
    public function __construct(private AuthenticatedActorDirectory $actors, private AuthorizationDirectory $directory) {}

    public function allows(int $authenticatedId, DateTimeImmutable $serverNow): bool
    {
        if (! app()->environment(['local', 'testing'])) {
            return false;
        }
        $actor = $this->actors->publicId($authenticatedId);
        if ($actor === null) {
            return false;
        }
        // Dedicated composition keeps Identity's existing resolver binding unchanged.
        $permissions = new PermissionService($this->directory, new LocalPersistedCoverageResourceResolver);

        return $permissions->decide(
            $actor, self::CAPABILITY, LocalPersistedCoverageResourceResolver::scope(),
            LocalPersistedCoverageResourceResolver::resource(), $serverNow,
        )->allowed();
    }
}
