<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftCatalogInput
{
    public function __construct(
        public string $merchantPublicId,
        public string $catalogName,
        public string $productName,
        public ?string $description,
        public ?string $brand,
    ) {
        if (! self::publicId($merchantPublicId)) {
            throw new InvalidArgumentException('Invalid draft catalog input.');
        }
        foreach ([$catalogName, $productName, $brand] as $name) {
            if ($name !== null && (! mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 255
                || trim($name, ' ') === '' || preg_match('/[\x00-\x1F\x7F]/u', $name))) {
                throw new InvalidArgumentException('Invalid draft catalog input.');
            }
        }
        if ($description !== null && (! mb_check_encoding($description, 'UTF-8') || mb_strlen($description, 'UTF-8') > 4000
            || preg_match('/[\x00-\x09\x0B-\x1F\x7F]/u', $description))) {
            throw new InvalidArgumentException('Invalid draft catalog input.');
        }
    }

    public static function publicId(string $value): bool
    {
        return preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $value) === 1;
    }

    /** @param array<array-key, mixed> $value
     * @param  list<string>  $expected
     */
    public static function keys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys);

        return $keys === $expected;
    }

    /** @param array<array-key, mixed> $body */
    public static function fromArray(array $body): self
    {
        if (! self::keys($body, ['catalog', 'merchant_public_id', 'product']) || ! is_string($body['merchant_public_id'])
            || ! is_array($body['catalog']) || ! is_array($body['product'])) {
            throw new InvalidArgumentException('Invalid draft catalog input.');
        }
        $catalog = $body['catalog'];
        $product = $body['product'];
        if (! self::keys($catalog, ['name']) || ! self::keys($product, ['brand', 'description', 'name'])
            || ! is_string($catalog['name']) || ! is_string($product['name'])
            || ($product['description'] !== null && ! is_string($product['description']))
            || ($product['brand'] !== null && ! is_string($product['brand']))) {
            throw new InvalidArgumentException('Invalid draft catalog input.');
        }

        return new self($body['merchant_public_id'], $catalog['name'], $product['name'], $product['description'], $product['brand']);
    }

    /** @return array<string, mixed> */
    public function fingerprintPayload(): array
    {
        return ['schema_version' => 1, 'merchant_public_id' => $this->merchantPublicId, 'catalog' => ['name' => $this->catalogName],
            'product' => ['name' => $this->productName, 'description' => $this->description, 'brand' => $this->brand]];
    }
}
