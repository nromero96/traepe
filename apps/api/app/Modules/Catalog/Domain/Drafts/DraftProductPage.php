<?php

namespace App\Modules\Catalog\Domain\Drafts;

final readonly class DraftProductPage
{
    /** @param list<DraftProductProfile> $products */
    public function __construct(public int $catalogVersion, public array $products, public bool $hasMore, public int $lastId, public ?string $nextCursor = null) {}
}
