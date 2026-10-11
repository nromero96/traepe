<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeOperation;
use Closure;

interface LocalDraftVariantChangeWriter
{
    public function execute(DraftVariantChangeInput $input, string $actorPublicId, string $key, string $correlationId, Closure $authorize): DraftVariantChangeOperation;
}
