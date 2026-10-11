<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftProductChangeInput
{
    public function __construct(public string $catalogPublicId, public string $productPublicId, public int $expectedVersion, public DraftProductData $product)
    {
        if (! DraftCatalogInput::publicId($catalogPublicId) || ! DraftCatalogInput::publicId($productPublicId) || $expectedVersion < 1 || $expectedVersion > 2147483647) {
            throw new InvalidArgumentException('Invalid draft product change input.');
        }
    }

    /** @param array<array-key,mixed> $body */
    public static function fromArray(string $catalogPublicId, string $productPublicId, string $etag, array $body): self
    {
        $input = DraftProductInput::fromArray($catalogPublicId, $etag, $body);

        return new self($catalogPublicId, $productPublicId, $input->expectedVersion, $input->product);
    }

    /** @return array<string,mixed> */
    public function fingerprintPayload(): array
    {
        return ['schema_version' => 1, 'catalog_public_id' => $this->catalogPublicId, 'product_public_id' => $this->productPublicId,
            'expected_catalog_version' => $this->expectedVersion, 'product' => $this->product->snapshot()];
    }
}
