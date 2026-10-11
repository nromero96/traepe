<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftProductChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeOperation;
use Closure;

interface LocalDraftProductChangeWriter
{
    public function execute(DraftProductChangeInput $input, string $actorPublicId, string $key, string $correlationId, Closure $authorize): DraftProductChangeOperation;
}
