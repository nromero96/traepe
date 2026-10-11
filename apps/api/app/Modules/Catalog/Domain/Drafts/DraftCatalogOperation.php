<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftCatalogOperation
{
    public function __construct(public string $publicId, public string $catalogPublicId, public string $productPublicId, public DraftCatalogInput $input)
    {
        foreach ([$publicId, $catalogPublicId, $productPublicId] as $id) {
            if (! DraftCatalogInput::publicId($id)) {
                throw new InvalidArgumentException('Invalid draft catalog operation.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return ['schema_version' => 1, 'merchant_public_id' => $this->input->merchantPublicId,
            'catalog' => ['public_id' => $this->catalogPublicId, 'name' => $this->input->catalogName, 'status' => 'draft', 'version' => 1],
            'product' => ['public_id' => $this->productPublicId, 'catalog_public_id' => $this->catalogPublicId,
                'name' => $this->input->productName, 'description' => $this->input->description, 'brand' => $this->input->brand,
                'status' => 'draft', 'version' => 1]];
    }

    /** @param array<array-key, mixed> $snapshot */
    public static function restore(string $publicId, array $snapshot): self
    {
        if (! DraftCatalogInput::keys($snapshot, ['catalog', 'merchant_public_id', 'product', 'schema_version'])
            || $snapshot['schema_version'] !== 1 || ! is_string($snapshot['merchant_public_id'])
            || ! is_array($snapshot['catalog']) || ! is_array($snapshot['product'])) {
            throw new InvalidArgumentException('Invalid draft catalog snapshot.');
        }
        $catalog = $snapshot['catalog'];
        $product = $snapshot['product'];
        if (! DraftCatalogInput::keys($catalog, ['name', 'public_id', 'status', 'version'])
            || ! DraftCatalogInput::keys($product, ['brand', 'catalog_public_id', 'description', 'name', 'public_id', 'status', 'version'])
            || ! is_string($catalog['public_id']) || ! is_string($product['public_id'])
            || $product['catalog_public_id'] !== $catalog['public_id'] || $catalog['status'] !== 'draft' || $product['status'] !== 'draft'
            || $catalog['version'] !== 1 || $product['version'] !== 1) {
            throw new InvalidArgumentException('Invalid draft catalog snapshot.');
        }
        $catalogId = $catalog['public_id'];
        $productId = $product['public_id'];
        unset($catalog['public_id'], $catalog['status'], $catalog['version'], $product['public_id'], $product['catalog_public_id'], $product['status'], $product['version']);
        $input = DraftCatalogInput::fromArray(['merchant_public_id' => $snapshot['merchant_public_id'], 'catalog' => $catalog, 'product' => $product]);

        return new self($publicId, $catalogId, $productId, $input);
    }
}
