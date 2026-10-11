<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftVariantInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantOperation;
use Closure;

interface LocalDraftVariantWriter
{
    public function execute(DraftVariantInput $input, string $actorPublicId, string $key, string $correlationId, Closure $authorize): DraftVariantOperation;
}
