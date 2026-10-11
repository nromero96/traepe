<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftVariantAccess;
use App\Modules\Identity\Application\Authorization\AuthenticatedActorDirectory;
use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\PermissionService;
use DateTimeImmutable;

final readonly class IdentityLocalDraftVariantAccess implements LocalDraftVariantAccess
{
    public function __construct(private AuthenticatedActorDirectory $actors, private AuthorizationDirectory $directory) {}

    public function actor(int $authenticatedId, string $capability, DateTimeImmutable $serverNow): ?string
    {
        if (! app()->environment(['local', 'testing']) || ! in_array($capability, [self::CREATE, self::CONTENT, self::READ], true)) {
            return null;
        }
        $actor = $this->actors->publicId($authenticatedId);
        if ($actor === null) {
            return null;
        }
        $allowed = (new PermissionService($this->directory, new LocalDraftVariantResourceResolver))->decide($actor, $capability, LocalDraftVariantResourceResolver::scope(), LocalDraftVariantResourceResolver::resource($capability), $serverNow)->allowed();

        return $allowed ? $actor : null;
    }
}
