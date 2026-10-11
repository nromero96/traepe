<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftProductCursor
{
    public function __construct(public string $actorPublicId, public string $catalogPublicId, public int $catalogVersion, public int $limit, public int $lastId, public int $expiresAt)
    {
        if (! DraftCatalogInput::publicId($actorPublicId) || ! DraftCatalogInput::publicId($catalogPublicId)
            || $catalogVersion < 1 || $catalogVersion > 2147483647 || $limit < 1 || $limit > 50 || $lastId < 1 || $expiresAt < 1) {
            throw new InvalidArgumentException('Invalid draft product cursor.');
        }
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return ['schema_version' => 1, 'purpose' => 'catalog.local.draft_products.page.v1', 'actor_public_id' => $this->actorPublicId, 'catalog_public_id' => $this->catalogPublicId, 'catalog_version' => $this->catalogVersion, 'limit' => $this->limit, 'last_id' => $this->lastId, 'expires_at' => $this->expiresAt];
    }

    /** @param array<array-key,mixed> $value */
    public static function restore(array $value): self
    {
        if (! DraftCatalogInput::keys($value, ['actor_public_id', 'catalog_public_id', 'catalog_version', 'expires_at', 'last_id', 'limit', 'purpose', 'schema_version'])
            || $value['schema_version'] !== 1 || $value['purpose'] !== 'catalog.local.draft_products.page.v1' || ! is_string($value['actor_public_id']) || ! is_string($value['catalog_public_id'])) {
            throw new InvalidArgumentException('Invalid draft product cursor.');
        }
        foreach (['catalog_version', 'limit', 'last_id', 'expires_at'] as $field) {
            if (! is_int($value[$field])) {
                throw new InvalidArgumentException('Invalid draft product cursor.');
            }
        }

        return new self($value['actor_public_id'], $value['catalog_public_id'], $value['catalog_version'], $value['limit'], $value['last_id'], $value['expires_at']);
    }
}
