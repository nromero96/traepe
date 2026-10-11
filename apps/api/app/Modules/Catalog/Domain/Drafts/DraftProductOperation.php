<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftProductOperation
{
    public function __construct(public string $publicId, public DraftCatalogProfile $catalogBefore, public DraftProductProfile $product)
    {
        if (! DraftCatalogInput::publicId($publicId) || $catalogBefore->version >= 2147483647 || $product->catalogPublicId !== $catalogBefore->publicId || $product->version !== 1) {
            throw new InvalidArgumentException('Invalid draft product operation.');
        }
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return ['schema_version' => 1, 'merchant_public_id' => $this->catalogBefore->merchantPublicId,
            'catalog' => ['public_id' => $this->catalogBefore->publicId, 'name' => $this->catalogBefore->name, 'status' => 'draft',
                'version_before' => $this->catalogBefore->version, 'version_after' => $this->catalogBefore->version + 1],
            'product' => ['public_id' => $this->product->publicId, 'catalog_public_id' => $this->product->catalogPublicId, ...$this->product->product->snapshot(), 'status' => 'draft', 'version' => 1]];
    }

    /** @param array<array-key,mixed> $value */
    public static function restore(string $publicId, array $value): self
    {
        if (! DraftCatalogInput::keys($value, ['catalog', 'merchant_public_id', 'product', 'schema_version']) || $value['schema_version'] !== 1
            || ! is_string($value['merchant_public_id']) || ! is_array($value['catalog']) || ! is_array($value['product'])) {
            throw new InvalidArgumentException('Invalid draft product snapshot.');
        }
        $c = $value['catalog'];
        $p = $value['product'];
        if (! DraftCatalogInput::keys($c, ['name', 'public_id', 'status', 'version_after', 'version_before'])
            || ! DraftCatalogInput::keys($p, ['brand', 'catalog_public_id', 'description', 'name', 'public_id', 'status', 'version'])
            || ! is_string($c['public_id']) || ! is_string($c['name']) || ! is_int($c['version_before']) || ! is_int($c['version_after'])
            || $c['version_after'] !== $c['version_before'] + 1 || $c['status'] !== 'draft'
            || ! is_string($p['public_id']) || ! is_string($p['catalog_public_id']) || $p['status'] !== 'draft' || $p['version'] !== 1) {
            throw new InvalidArgumentException('Invalid draft product snapshot.');
        }

        return new self($publicId, new DraftCatalogProfile($c['public_id'], $value['merchant_public_id'], $c['name'], $c['version_before']),
            new DraftProductProfile($p['public_id'], $p['catalog_public_id'], DraftProductData::fromArray(['name' => $p['name'], 'description' => $p['description'], 'brand' => $p['brand']])));
    }
}
