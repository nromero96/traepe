<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ReadLocalDraftProductChange
{
    public function __construct(private LocalDraftProductChangeAccess $access, private LocalDraftProductChangeStore $store) {}

    public function execute(int $authenticatedId, string $operationPublicId): DraftProductChangeOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftProductChangeAccess::READ, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }

        return $this->store->find($operationPublicId, $actor);
    }
}
