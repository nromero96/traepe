<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogOperation;

interface LocalDraftCatalogStore
{
    public function create(DraftCatalogInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftCatalogOperation;

    public function find(string $operationPublicId, string $actorPublicId): DraftCatalogOperation;
}
