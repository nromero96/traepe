<?php

namespace App\Modules\Marketplace\Infrastructure\Fixtures;

use App\Modules\Identity\Application\Authorization\AuthenticatedActorDirectory;
use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\PermissionService;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureAccess;
use DateTimeImmutable;

final readonly class IdentityLocalDraftFixtureAccess implements LocalDraftFixtureAccess
{
    public function __construct(private AuthenticatedActorDirectory $actors, private AuthorizationDirectory $directory) {}

    public function actor(int $authenticatedId, DateTimeImmutable $serverNow): ?string
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }
        $actor = $this->actors->publicId($authenticatedId);
        if ($actor === null) {
            return null;
        }
        $allowed = (new PermissionService($this->directory, new LocalDraftFixtureResourceResolver))->decide(
            $actor, self::CAPABILITY, LocalDraftFixtureResourceResolver::scope(),
            LocalDraftFixtureResourceResolver::resource(), $serverNow,
        )->allowed();

        return $allowed ? $actor : null;
    }
}
