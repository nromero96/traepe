<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftVariantCurrentProfile
{
    public function __construct(public string $publicId, public string $catalogPublicId, public string $productPublicId, public DraftVariantData $variant, public int $version)
    {
        foreach ([$publicId, $catalogPublicId, $productPublicId] as $id) {
            if (! DraftCatalogInput::publicId($id)) {
                throw new InvalidArgumentException('Invalid current draft variant profile.');
            }
        }
        if ($version < 1 || $version > 2147483647) {
            throw new InvalidArgumentException('Invalid current draft variant version.');
        }
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return ['schema_version' => 2, 'catalog_public_id' => $this->catalogPublicId, 'product_public_id' => $this->productPublicId,
            ...$this->variant->snapshot(), 'status' => 'draft', 'version' => $this->version];
    }
}
