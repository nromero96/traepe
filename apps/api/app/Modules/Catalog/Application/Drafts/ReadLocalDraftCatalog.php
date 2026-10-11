<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ReadLocalDraftCatalog
{
    public function __construct(private LocalDraftCatalogAccess $access, private LocalDraftCatalogStore $store) {}

    public function execute(int $authenticatedId, string $operationPublicId): DraftCatalogOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftCatalogAccess::READ, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }

        return $this->store->find($operationPublicId, $actor);
    }
}
