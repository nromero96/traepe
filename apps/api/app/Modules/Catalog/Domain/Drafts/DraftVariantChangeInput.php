<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;
use stdClass;

final readonly class DraftVariantChangeInput
{
    public function __construct(public string $catalogPublicId, public string $productPublicId, public string $variantPublicId, public int $expectedVersion, public DraftVariantEditableData $variant)
    {
        foreach ([$catalogPublicId, $productPublicId, $variantPublicId] as $id) {
            if (! DraftCatalogInput::publicId($id)) {
                throw new InvalidArgumentException('Invalid draft variant change input.');
            }
        }
        if ($expectedVersion < 1 || $expectedVersion > 2147483647) {
            throw new InvalidArgumentException('Invalid draft variant change version.');
        }
    }

    /** @param array<array-key,mixed> $body */
    public static function fromArray(string $catalog, string $product, string $variant, string $etag, array $body): self
    {
        if (! DraftCatalogInput::keys($body, ['variant']) || ! $body['variant'] instanceof stdClass
            || ! DraftCatalogInput::publicId($catalog) || ! preg_match('/^"catalog:'.preg_quote($catalog, '/').':v([1-9][0-9]{0,9})"$/D', $etag, $match) || (int) $match[1] > 2147483647) {
            throw new InvalidArgumentException('Invalid draft variant change input.');
        }

        return new self($catalog, $product, $variant, (int) $match[1], DraftVariantEditableData::fromArray((array) $body['variant']));
    }

    /** @return array<string,mixed> */
    public function fingerprintPayload(): array
    {
        return ['schema_version' => 1, 'catalog_public_id' => $this->catalogPublicId, 'product_public_id' => $this->productPublicId,
            'variant_public_id' => $this->variantPublicId, 'expected_catalog_version' => $this->expectedVersion, 'variant' => $this->variant->fingerprint()];
    }
}
