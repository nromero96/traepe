<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogOperation;
use Closure;

interface LocalDraftCatalogWriter
{
    /** @param Closure(): void $authorize */
    public function execute(DraftCatalogInput $input, string $actorPublicId, string $key, string $correlationId, Closure $authorize): DraftCatalogOperation;
}
