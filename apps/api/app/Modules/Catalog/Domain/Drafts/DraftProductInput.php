<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftProductInput
{
    public function __construct(public string $catalogPublicId, public int $expectedVersion, public DraftProductData $product)
    {
        if (! DraftCatalogInput::publicId($catalogPublicId) || $expectedVersion < 1 || $expectedVersion > 2147483647) {
            throw new InvalidArgumentException('Invalid draft product input.');
        }
    }

    /** @param array<array-key,mixed> $body */
    public static function fromArray(string $catalogPublicId, string $etag, array $body): self
    {
        if (! DraftCatalogInput::publicId($catalogPublicId)
            || ! preg_match('/^"catalog:'.preg_quote($catalogPublicId, '/').':v([1-9][0-9]{0,9})"$/D', $etag, $match)
            || (int) $match[1] > 2147483647 || ! DraftCatalogInput::keys($body, ['product']) || ! is_array($body['product'])) {
            throw new InvalidArgumentException('Invalid draft product input.');
        }

        return new self($catalogPublicId, (int) $match[1], DraftProductData::fromArray($body['product']));
    }

    /** @return array<string,mixed> */
    public function fingerprintPayload(): array
    {
        return ['schema_version' => 1, 'catalog_public_id' => $this->catalogPublicId, 'expected_catalog_version' => $this->expectedVersion, 'product' => $this->product->snapshot()];
    }
}
