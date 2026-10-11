<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftProductProfile
{
    public function __construct(public string $publicId, public string $catalogPublicId, public DraftProductData $product, public int $version = 1)
    {
        if (! DraftCatalogInput::publicId($publicId) || ! DraftCatalogInput::publicId($catalogPublicId) || $version < 1 || $version > 2147483647) {
            throw new InvalidArgumentException('Invalid draft product profile.');
        }
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return ['schema_version' => 1, 'catalog_public_id' => $this->catalogPublicId, ...$this->product->snapshot(), 'status' => 'draft', 'version' => $this->version];
    }
}
