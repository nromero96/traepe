<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;
use stdClass;

final readonly class DraftVariantInput
{
    public function __construct(public string $catalogPublicId, public string $productPublicId, public int $expectedVersion, public DraftVariantData $variant)
    {
        if (! DraftCatalogInput::publicId($catalogPublicId) || ! DraftCatalogInput::publicId($productPublicId) || $expectedVersion < 1 || $expectedVersion > 2147483647) {
            throw new InvalidArgumentException('Invalid draft variant input.');
        }
    }

    /** @param array<array-key,mixed> $body */
    public static function fromArray(string $catalog, string $product, string $etag, array $body): self
    {
        if (! DraftCatalogInput::keys($body, ['variant']) || ! $body['variant'] instanceof stdClass
            || ! DraftCatalogInput::publicId($catalog) || ! preg_match('/^"catalog:'.preg_quote($catalog, '/').':v([1-9][0-9]{0,9})"$/D', $etag, $match) || (int) $match[1] > 2147483647) {
            throw new InvalidArgumentException('Invalid draft variant input.');
        }

        return new self($catalog, $product, (int) $match[1], DraftVariantData::fromArray((array) $body['variant']));
    }

    /** @return array<string,mixed> */
    public function fingerprintPayload(): array
    {
        return ['schema_version' => 1, 'catalog_public_id' => $this->catalogPublicId, 'product_public_id' => $this->productPublicId,
            'expected_catalog_version' => $this->expectedVersion, 'variant' => $this->variant->fingerprint()];
    }
}
