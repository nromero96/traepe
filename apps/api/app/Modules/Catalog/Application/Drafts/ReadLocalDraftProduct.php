<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftProductOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ReadLocalDraftProduct
{
    public function __construct(private LocalDraftProductAccess $access, private LocalDraftProductStore $store) {}

    public function execute(int $authenticatedId, string $operationPublicId): DraftProductOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftProductAccess::READ, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }

        return $this->store->find($operationPublicId, $actor);
    }
}
