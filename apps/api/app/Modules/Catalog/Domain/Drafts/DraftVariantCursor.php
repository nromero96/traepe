<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftVariantCursor
{
    public function __construct(public string $actorPublicId, public string $catalogPublicId, public string $productPublicId, public int $catalogVersion, public int $productVersion, public int $limit, public int $lastId, public int $expiresAt)
    {
        if (! DraftCatalogInput::publicId($actorPublicId) || ! DraftCatalogInput::publicId($catalogPublicId)
            || ! DraftCatalogInput::publicId($productPublicId) || $productVersion < 1 || $productVersion > 2147483647 || $catalogVersion < 1 || $catalogVersion > 2147483647 || $limit < 1 || $limit > 50 || $lastId < 1 || $expiresAt < 1) {
            throw new InvalidArgumentException('Invalid draft variant cursor.');
        }
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return ['schema_version' => 1, 'purpose' => 'catalog.local.draft_variants.page.v1', 'actor_public_id' => $this->actorPublicId, 'catalog_public_id' => $this->catalogPublicId, 'product_public_id' => $this->productPublicId, 'product_version' => $this->productVersion, 'catalog_version' => $this->catalogVersion, 'limit' => $this->limit, 'last_id' => $this->lastId, 'expires_at' => $this->expiresAt];
    }

    /** @param array<array-key,mixed> $value */
    public static function restore(array $value): self
    {
        if (! DraftCatalogInput::keys($value, ['actor_public_id', 'catalog_public_id', 'catalog_version', 'expires_at', 'last_id', 'limit', 'product_public_id', 'product_version', 'purpose', 'schema_version'])
            || $value['schema_version'] !== 1 || $value['purpose'] !== 'catalog.local.draft_variants.page.v1' || ! is_string($value['actor_public_id']) || ! is_string($value['catalog_public_id']) || ! is_string($value['product_public_id'])) {
            throw new InvalidArgumentException('Invalid draft variant cursor.');
        }
        foreach (['catalog_version', 'product_version', 'limit', 'last_id', 'expires_at'] as $field) {
            if (! is_int($value[$field])) {
                throw new InvalidArgumentException('Invalid draft variant cursor.');
            }
        }

        return new self($value['actor_public_id'], $value['catalog_public_id'], $value['product_public_id'], $value['catalog_version'], $value['product_version'], $value['limit'], $value['last_id'], $value['expires_at']);
    }
}
