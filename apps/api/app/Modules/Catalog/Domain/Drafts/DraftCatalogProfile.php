<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftCatalogProfile
{
    public function __construct(public string $publicId, public string $merchantPublicId, public string $name, public int $version)
    {
        if (! DraftCatalogInput::publicId($publicId) || ! DraftCatalogInput::publicId($merchantPublicId) || $version < 1 || $version > 2147483647) {
            throw new InvalidArgumentException('Invalid draft catalog profile.');
        }
        DraftProductData::name($name);
    }

    public function etag(): string
    {
        return '"catalog:'.$this->publicId.':v'.$this->version.'"';
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return ['schema_version' => 1, 'merchant_public_id' => $this->merchantPublicId, 'name' => $this->name, 'status' => 'draft', 'version' => $this->version];
    }
}
