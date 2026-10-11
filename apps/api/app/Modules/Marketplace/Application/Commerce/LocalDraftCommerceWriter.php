<?php

namespace App\Modules\Marketplace\Application\Commerce;

use App\Modules\Marketplace\Domain\Commerce\DraftCommerceInput;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceOperation;
use Closure;

interface LocalDraftCommerceWriter
{
    /** @param Closure(): void $authorize */
    public function execute(DraftCommerceInput $input, string $actorPublicId, string $key, string $correlationId, Closure $authorize): DraftCommerceOperation;
}
