<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ReadLocalDraftVariantChange
{
    public function __construct(private LocalDraftVariantChangeAccess $access, private LocalDraftVariantChangeStore $store) {}

    public function execute(int $authenticatedId, string $operationPublicId): DraftVariantChangeOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftVariantChangeAccess::READ, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }

        return $this->store->find($operationPublicId, $actor);
    }
}
