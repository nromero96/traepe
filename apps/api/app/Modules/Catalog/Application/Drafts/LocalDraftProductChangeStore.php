<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftProductChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeOperation;

interface LocalDraftProductChangeStore
{
    public function change(DraftProductChangeInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftProductChangeOperation;

    public function find(string $operationPublicId, string $actorPublicId): DraftProductChangeOperation;
}
