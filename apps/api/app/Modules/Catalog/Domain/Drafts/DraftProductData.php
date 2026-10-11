<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;

final readonly class DraftProductData
{
    public function __construct(public string $name, public ?string $description, public ?string $brand)
    {
        self::name($name);
        if ($brand !== null) {
            self::name($brand);
        }
        if ($description !== null && (! mb_check_encoding($description, 'UTF-8') || mb_strlen($description, 'UTF-8') > 4000
            || preg_match('/[\x00-\x09\x0B-\x1F\x7F]/u', $description))) {
            throw new InvalidArgumentException('Invalid draft product data.');
        }
    }

    public static function name(string $name): void
    {
        if (! mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 255
            || trim($name, ' ') === '' || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            throw new InvalidArgumentException('Invalid draft product data.');
        }
    }

    /** @param array<array-key,mixed> $value */
    public static function fromArray(array $value): self
    {
        if (! DraftCatalogInput::keys($value, ['brand', 'description', 'name']) || ! is_string($value['name'])
            || ($value['description'] !== null && ! is_string($value['description']))
            || ($value['brand'] !== null && ! is_string($value['brand']))) {
            throw new InvalidArgumentException('Invalid draft product data.');
        }

        return new self($value['name'], $value['description'], $value['brand']);
    }

    /** @return array{name:string,description:?string,brand:?string} */
    public function snapshot(): array
    {
        return ['name' => $this->name, 'description' => $this->description, 'brand' => $this->brand];
    }
}
