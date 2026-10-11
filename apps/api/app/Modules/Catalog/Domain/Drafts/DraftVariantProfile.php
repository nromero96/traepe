<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftVariantProfile
{
    public function __construct(public string $publicId, public string $catalogPublicId, public string $productPublicId, public DraftVariantData $variant)
    {
        foreach ([$publicId, $catalogPublicId, $productPublicId] as $id) {
            if (! DraftCatalogInput::publicId($id)) {
                throw new InvalidArgumentException('Invalid draft variant profile.');
            }
        }
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return ['schema_version' => 1, 'catalog_public_id' => $this->catalogPublicId, 'product_public_id' => $this->productPublicId,
            ...$this->variant->snapshot(), 'status' => 'draft', 'version' => 1];
    }
}
