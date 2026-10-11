<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ReadLocalDraftCatalogProfile
{
    public function __construct(private LocalDraftProductAccess $access, private LocalDraftProductStore $store) {}

    public function execute(int $authenticatedId, string $catalogPublicId): DraftCatalogProfile
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftProductAccess::CONTENT, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }

        return $this->store->profile($catalogPublicId, $actor);
    }
}
