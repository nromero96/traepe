<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftVariantOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ReadLocalDraftVariant
{
    public function __construct(private LocalDraftVariantAccess $access, private LocalDraftVariantStore $store) {}

    public function execute(int $authenticatedId, string $operationPublicId): DraftVariantOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftVariantAccess::READ, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }

        return $this->store->find($operationPublicId, $actor);
    }
}
