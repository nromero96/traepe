<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftProductChangeOperation
{
    public function __construct(public string $publicId, public DraftCatalogProfile $catalogBefore, public DraftProductProfile $productBefore, public DraftProductProfile $productAfter)
    {
        if (! DraftCatalogInput::publicId($publicId) || $catalogBefore->version >= 2147483647 || $productBefore->version >= 2147483647
            || $productBefore->catalogPublicId !== $catalogBefore->publicId || $productAfter->catalogPublicId !== $catalogBefore->publicId
            || $productBefore->publicId !== $productAfter->publicId || $productAfter->version !== $productBefore->version + 1
            || $productBefore->product->snapshot() === $productAfter->product->snapshot()) {
            throw new InvalidArgumentException('Invalid draft product change operation.');
        }
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return ['schema_version' => 1, 'merchant_public_id' => $this->catalogBefore->merchantPublicId,
            'catalog' => ['public_id' => $this->catalogBefore->publicId, 'name' => $this->catalogBefore->name, 'status' => 'draft',
                'version_before' => $this->catalogBefore->version, 'version_after' => $this->catalogBefore->version + 1],
            'product' => ['public_id' => $this->productBefore->publicId, 'catalog_public_id' => $this->catalogBefore->publicId,
                'before' => [...$this->productBefore->product->snapshot(), 'status' => 'draft', 'version' => $this->productBefore->version],
                'after' => [...$this->productAfter->product->snapshot(), 'status' => 'draft', 'version' => $this->productAfter->version]]];
    }

    /** @param array<array-key,mixed> $value */
    public static function restore(string $publicId, array $value): self
    {
        if (! DraftCatalogInput::keys($value, ['catalog', 'merchant_public_id', 'product', 'schema_version']) || $value['schema_version'] !== 1
            || ! is_string($value['merchant_public_id']) || ! is_array($value['catalog']) || ! is_array($value['product'])) {
            throw new InvalidArgumentException('Invalid draft product change snapshot.');
        }
        $c = $value['catalog'];
        $p = $value['product'];
        if (! DraftCatalogInput::keys($c, ['name', 'public_id', 'status', 'version_after', 'version_before'])
            || ! DraftCatalogInput::keys($p, ['after', 'before', 'catalog_public_id', 'public_id'])
            || ! is_string($c['public_id']) || ! is_string($c['name']) || $c['status'] !== 'draft'
            || ! is_int($c['version_before']) || ! is_int($c['version_after']) || $c['version_after'] !== $c['version_before'] + 1
            || ! is_string($p['public_id']) || ! is_string($p['catalog_public_id']) || ! is_array($p['before']) || ! is_array($p['after'])) {
            throw new InvalidArgumentException('Invalid draft product change snapshot.');
        }
        $profiles = [];
        foreach (['before', 'after'] as $side) {
            $s = $p[$side];
            if (! DraftCatalogInput::keys($s, ['brand', 'description', 'name', 'status', 'version']) || $s['status'] !== 'draft' || ! is_int($s['version'])) {
                throw new InvalidArgumentException('Invalid draft product change snapshot.');
            }
            $profiles[$side] = new DraftProductProfile($p['public_id'], $p['catalog_public_id'],
                DraftProductData::fromArray(['name' => $s['name'], 'description' => $s['description'], 'brand' => $s['brand']]), $s['version']);
        }

        return new self($publicId, new DraftCatalogProfile($c['public_id'], $value['merchant_public_id'], $c['name'], $c['version_before']), $profiles['before'], $profiles['after']);
    }
}
