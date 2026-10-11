<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeOperation;

interface LocalDraftVariantChangeStore
{
    public function change(DraftVariantChangeInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftVariantChangeOperation;

    public function find(string $operationPublicId, string $actorPublicId): DraftVariantChangeOperation;
}
