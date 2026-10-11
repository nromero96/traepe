<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductCursor;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductOperation;
use App\Modules\Catalog\Domain\Drafts\DraftProductPage;

interface LocalDraftProductStore
{
    public function profile(string $catalogPublicId, string $actorPublicId): DraftCatalogProfile;

    public function page(string $catalogPublicId, string $actorPublicId, int $limit, ?DraftProductCursor $cursor): DraftProductPage;

    public function append(DraftProductInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftProductOperation;

    public function find(string $operationPublicId, string $actorPublicId): DraftProductOperation;
}
