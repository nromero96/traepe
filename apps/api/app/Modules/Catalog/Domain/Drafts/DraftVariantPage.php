<?php

namespace App\Modules\Catalog\Domain\Drafts;

final readonly class DraftVariantPage
{
    /** @param list<DraftVariantCurrentProfile> $variants */
    public function __construct(public int $catalogVersion, public int $productVersion, public array $variants, public bool $hasMore, public int $lastId, public ?string $nextCursor = null) {}
}
