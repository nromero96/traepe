<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;
use stdClass;

final readonly class DraftVariantOperation
{
    public function __construct(public string $publicId, public DraftCatalogProfile $catalogBefore, public DraftProductProfile $productBefore, public DraftVariantProfile $variant)
    {
        if (! DraftCatalogInput::publicId($publicId) || $catalogBefore->version >= 2147483647 || $productBefore->version >= 2147483647
            || $productBefore->catalogPublicId !== $catalogBefore->publicId || $variant->catalogPublicId !== $catalogBefore->publicId
            || $variant->productPublicId !== $productBefore->publicId) {
            throw new InvalidArgumentException('Invalid draft variant operation.');
        }
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return ['schema_version' => 1, 'merchant_public_id' => $this->catalogBefore->merchantPublicId,
            'catalog' => ['public_id' => $this->catalogBefore->publicId, 'name' => $this->catalogBefore->name, 'status' => 'draft',
                'version_before' => $this->catalogBefore->version, 'version_after' => $this->catalogBefore->version + 1],
            'product' => ['public_id' => $this->productBefore->publicId, 'catalog_public_id' => $this->catalogBefore->publicId,
                ...$this->productBefore->product->snapshot(), 'status' => 'draft', 'version_before' => $this->productBefore->version, 'version_after' => $this->productBefore->version + 1],
            'variant' => ['public_id' => $this->variant->publicId, 'product_public_id' => $this->productBefore->publicId, ...$this->variant->variant->snapshot(), 'status' => 'draft', 'version' => 1]];
    }

    /** @param array<array-key,mixed> $value */
    public static function restore(string $publicId, array $value): self
    {
        if (! DraftCatalogInput::keys($value, ['catalog', 'merchant_public_id', 'product', 'schema_version', 'variant']) || $value['schema_version'] !== 1
            || ! is_string($value['merchant_public_id']) || ! $value['catalog'] instanceof stdClass || ! $value['product'] instanceof stdClass || ! $value['variant'] instanceof stdClass) {
            throw new InvalidArgumentException('Invalid draft variant snapshot.');
        }
        $c = (array) $value['catalog'];
        $p = (array) $value['product'];
        $v = (array) $value['variant'];
        if (! DraftCatalogInput::keys($c, ['name', 'public_id', 'status', 'version_after', 'version_before'])
            || ! DraftCatalogInput::keys($p, ['brand', 'catalog_public_id', 'description', 'name', 'public_id', 'status', 'version_after', 'version_before'])
            || ! DraftCatalogInput::keys($v, ['attributes', 'barcode', 'name', 'product_public_id', 'public_id', 'size', 'sku', 'status', 'unit', 'version'])
            || ! is_string($c['name']) || ! is_string($c['public_id']) || ! is_string($p['public_id']) || ! is_string($p['catalog_public_id'])
            || ! is_string($v['public_id']) || ! is_string($v['product_public_id']) || $c['status'] !== 'draft' || $p['status'] !== 'draft' || $v['status'] !== 'draft' || $v['version'] !== 1) {
            throw new InvalidArgumentException('Invalid draft variant snapshot.');
        }
        foreach ([$c, $p] as $versions) {
            if (! is_int($versions['version_before']) || ! is_int($versions['version_after']) || $versions['version_after'] !== $versions['version_before'] + 1) {
                throw new InvalidArgumentException('Invalid draft variant revisions.');
            }
        }

        return new self($publicId, new DraftCatalogProfile($c['public_id'], $value['merchant_public_id'], $c['name'], $c['version_before']),
            new DraftProductProfile($p['public_id'], $p['catalog_public_id'], DraftProductData::fromArray(['name' => $p['name'], 'description' => $p['description'], 'brand' => $p['brand']]), $p['version_before']),
            new DraftVariantProfile($v['public_id'], $p['catalog_public_id'], $v['product_public_id'], DraftVariantData::fromArray(array_intersect_key($v, array_flip(['name', 'sku', 'size', 'unit', 'barcode', 'attributes'])))));
    }
}
