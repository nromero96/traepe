<?php

namespace App\Modules\Marketplace\Infrastructure\Commerce;

use App\Modules\Identity\Application\Authorization\AuthenticatedActorDirectory;
use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\PermissionService;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceAccess;
use DateTimeImmutable;

final readonly class IdentityLocalDraftCommerceAccess implements LocalDraftCommerceAccess
{
    public function __construct(private AuthenticatedActorDirectory $actors, private AuthorizationDirectory $directory) {}

    public function actor(int $authenticatedId, string $capability, DateTimeImmutable $serverNow): ?string
    {
        if (! app()->environment(['local', 'testing']) || ! in_array($capability, [self::CREATE, self::READ], true)) {
            return null;
        }
        $actor = $this->actors->publicId($authenticatedId);
        if ($actor === null) {
            return null;
        }
        $allowed = (new PermissionService($this->directory, new LocalDraftCommerceResourceResolver))->decide(
            $actor, $capability, LocalDraftCommerceResourceResolver::scope(), LocalDraftCommerceResourceResolver::resource($capability), $serverNow,
        )->allowed();

        return $allowed ? $actor : null;
    }
}
