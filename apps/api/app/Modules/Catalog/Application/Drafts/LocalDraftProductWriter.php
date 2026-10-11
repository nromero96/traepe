<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductOperation;
use Closure;

interface LocalDraftProductWriter
{
    public function execute(DraftProductInput $input, string $actorPublicId, string $key, string $correlationId, Closure $authorize): DraftProductOperation;
}
