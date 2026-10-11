<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeAccess;
use App\Modules\Identity\Application\Authorization\AuthenticatedActorDirectory;
use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\PermissionService;
use DateTimeImmutable;

final readonly class IdentityLocalDraftProductChangeAccess implements LocalDraftProductChangeAccess
{
    public function __construct(private AuthenticatedActorDirectory $actors, private AuthorizationDirectory $directory) {}

    public function actor(int $authenticatedId, string $capability, DateTimeImmutable $serverNow): ?string
    {
        if (! app()->environment(['local', 'testing']) || ! in_array($capability, [self::UPDATE, self::READ], true)) {
            return null;
        }
        $actor = $this->actors->publicId($authenticatedId);
        if ($actor === null) {
            return null;
        }
        $allowed = (new PermissionService($this->directory, new LocalDraftProductChangeResourceResolver))->decide($actor, $capability, LocalDraftProductChangeResourceResolver::scope(), LocalDraftProductChangeResourceResolver::resource($capability), $serverNow)->allowed();

        return $allowed ? $actor : null;
    }
}
