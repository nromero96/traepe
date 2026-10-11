<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftVariantCursor;
use App\Modules\Catalog\Domain\Drafts\DraftVariantInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantOperation;
use App\Modules\Catalog\Domain\Drafts\DraftVariantPage;

interface LocalDraftVariantStore
{
    public function append(DraftVariantInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftVariantOperation;

    public function find(string $operationPublicId, string $actorPublicId): DraftVariantOperation;

    public function page(string $catalogPublicId, string $productPublicId, string $actorPublicId, int $limit, ?DraftVariantCursor $cursor): DraftVariantPage;
}
