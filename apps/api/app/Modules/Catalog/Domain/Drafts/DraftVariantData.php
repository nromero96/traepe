<?php

namespace App\Modules\Catalog\Domain\Drafts;

use InvalidArgumentException;
use stdClass;

final readonly class DraftVariantData
{
    /** @var array<string,string> */
    public array $attributes;

    /** @param array<array-key,mixed> $attributes */
    public function __construct(public string $name, public string $sku, public ?string $size, public string $unit, public ?string $barcode, array $attributes)
    {
        self::text($name, 255);
        self::text($unit, 64);
        if ($size !== null) {
            self::text($size, 255);
        }
        if (! preg_match('/^[A-Z0-9][A-Z0-9._-]{0,63}$/D', $sku)
            || ($barcode !== null && ! preg_match('/^[!-~]{1,64}$/D', $barcode)) || count($attributes) > 20) {
            throw new InvalidArgumentException('Invalid draft variant data.');
        }
        $checked = [];
        foreach ($attributes as $key => $value) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key) || ! is_string($value)) {
                throw new InvalidArgumentException('Invalid draft variant attributes.');
            }
            self::text($value, 255);
            $checked[$key] = $value;
        }
        ksort($checked, SORT_STRING);
        $this->attributes = $checked;
    }

    private static function text(string $value, int $max): void
    {
        if (! mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') < 1 || mb_strlen($value, 'UTF-8') > $max
            || trim($value, ' ') === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('Invalid draft variant text.');
        }
    }

    /** @param array<array-key,mixed> $value */
    public static function fromArray(array $value): self
    {
        if (! DraftCatalogInput::keys($value, ['attributes', 'barcode', 'name', 'size', 'sku', 'unit'])
            || ! is_string($value['name']) || ! is_string($value['sku']) || ! is_string($value['unit'])
            || ($value['size'] !== null && ! is_string($value['size']))
            || ($value['barcode'] !== null && ! is_string($value['barcode'])) || ! $value['attributes'] instanceof stdClass) {
            throw new InvalidArgumentException('Invalid draft variant input.');
        }
        $attributes = (array) $value['attributes'];
        foreach ($attributes as $key => $item) {
            if (! is_string($key) || ! is_string($item)) {
                throw new InvalidArgumentException('Invalid draft variant attributes.');
            }
        }

        return new self($value['name'], $value['sku'], $value['size'], $value['unit'], $value['barcode'], $attributes);
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return ['name' => $this->name, 'sku' => $this->sku, 'size' => $this->size, 'unit' => $this->unit,
            'barcode' => $this->barcode, 'attributes' => (object) $this->attributes];
    }

    /** @return array<string,mixed> */
    public function fingerprint(): array
    {
        $pairs = [];
        foreach ($this->attributes as $key => $value) {
            $pairs[] = [$key, $value];
        }

        return [...$this->snapshot(), 'attributes' => $pairs];
    }
}
