<?php

namespace App\Modules\Marketplace\Application\Commerce;

use App\Modules\Marketplace\Domain\Commerce\DraftCommerceInput;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceOperation;

interface LocalDraftCommerceStore
{
    public function create(DraftCommerceInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftCommerceOperation;

    public function find(string $operationPublicId, string $actorPublicId): DraftCommerceOperation;
}
