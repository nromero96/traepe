<?php

namespace Tests\Unit;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductProfile;
use App\Modules\Catalog\Domain\Drafts\DraftVariantCursor;
use App\Modules\Catalog\Domain\Drafts\DraftVariantData;
use App\Modules\Catalog\Domain\Drafts\DraftVariantInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantOperation;
use App\Modules\Catalog\Domain\Drafts\DraftVariantProfile;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DraftVariantTest extends TestCase
{
    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private const PRODUCT = '01ARZ3NDEKTSV4RRFFQ69G5FB1';

    private function data(array $overrides = []): DraftVariantData
    {
        return DraftVariantData::fromArray(array_replace(['name' => ' Presentation ', 'sku' => 'DEMO-001', 'size' => null, 'unit' => ' unidad ', 'barcode' => null, 'attributes' => (object) []], $overrides));
    }

    private function invalid(callable $call): void
    {
        try {
            $call();
            $this->fail('Expected invalid variant data.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    public function test_text_boundaries_preserve_unicode_null_and_no_normalization(): void
    {
        $data = $this->data(['name' => str_repeat('á', 255), 'size' => ' 250 ', 'unit' => str_repeat('ñ', 64), 'barcode' => 'OPAQUE-CODE']);
        $this->assertSame(str_repeat('á', 255), $data->name);
        $this->assertSame(' 250 ', $data->size);
        $this->assertSame('OPAQUE-CODE', $data->barcode);
        $this->assertSame('{}', json_encode($data->snapshot()['attributes']));
        foreach (['name' => 255, 'size' => 255, 'unit' => 64] as $field => $max) {
            foreach (['', ' ', str_repeat('é', $max + 1), "\xff"] as $value) {
                $this->invalid(fn () => $this->data([$field => $value]));
            }
            foreach (array_merge(range(0, 31), [127]) as $control) {
                $this->invalid(fn () => $this->data([$field => 'a'.chr($control)]));
            }
        }
        foreach (['', ' lower', 'lower', '-DEMO', 'A B', str_repeat('A', 65), "A\n", 'Á'] as $sku) {
            $this->invalid(fn () => $this->data(['sku' => $sku]));
        }
        foreach (['', ' ', "code\n", 'á', str_repeat('X', 65)] as $code) {
            $this->invalid(fn () => $this->data(['barcode' => $code]));
        }
        $this->assertSame(str_repeat('A', 64), $this->data(['sku' => str_repeat('A', 64), 'barcode' => str_repeat('X', 64)])->sku);
    }

    public function test_bounded_object_map_is_distinct_from_lists_and_preserves_values(): void
    {
        $map = [];
        for ($i = 0; $i < 20; $i++) {
            $map['key_'.$i] = str_repeat('á', 255);
        }
        $this->assertCount(20, $this->data(['attributes' => (object) $map])->attributes);
        $this->invalid(fn () => $this->data(['attributes' => (object) ($map + ['extra' => 'x'])]));
        foreach ([null, [], ['x'], (object) ['bad-key' => 'x'], (object) ['Upper' => 'x'], (object) ['a' => true], (object) ['a' => 1], (object) ['a' => null], (object) ['a' => []], (object) ['a' => (object) []], (object) ['a' => ''], (object) ['a' => ' '], (object) ['a' => "a\n"], (object) ['a' => str_repeat('x', 256)]] as $value) {
            $this->invalid(fn () => $this->data(['attributes' => $value]));
        }
        foreach (array_merge(range(0, 31), [127]) as $control) {
            $this->invalid(fn () => $this->data(['attributes' => (object) ['color' => 'a'.chr($control)]]));
        }
        $this->assertSame(' azul ', $this->data(['attributes' => (object) ['color' => ' azul ']])->attributes['color']);
        $this->invalid(fn () => DraftVariantData::fromArray(['name' => 'x']));
        $this->invalid(fn () => DraftVariantData::fromArray($this->data()->snapshot() + ['price' => 1]));
    }

    public function test_input_etag_and_fingerprint_include_exact_references_version_and_map(): void
    {
        $a = new DraftVariantInput(self::ID, self::PRODUCT, 1, $this->data(['attributes' => (object) ['z' => 'last', 'a' => 'first']]));
        $b = new DraftVariantInput(self::ID, self::PRODUCT, 1, $this->data(['attributes' => (object) ['a' => 'first', 'z' => 'last']]));
        $this->assertSame(RequestFingerprint::hash($a->fingerprintPayload()), RequestFingerprint::hash($b->fingerprintPayload()));
        $this->assertSame([['a', 'first'], ['z', 'last']], $a->fingerprintPayload()['variant']['attributes']);
        $this->assertSame([], (new DraftVariantInput(self::ID, self::PRODUCT, 1, $this->data()))->fingerprintPayload()['variant']['attributes']);
        foreach ([
            new DraftVariantInput(self::PRODUCT, self::PRODUCT, 1, $a->variant),
            new DraftVariantInput(self::ID, self::ID, 1, $a->variant),
            new DraftVariantInput(self::ID, self::PRODUCT, 2, $a->variant),
            new DraftVariantInput(self::ID, self::PRODUCT, 1, $this->data(['size' => '250'])),
            new DraftVariantInput(self::ID, self::PRODUCT, 1, $this->data(['attributes' => (object) ['z' => 'Last', 'a' => 'first']])),
        ] as $different) {
            $this->assertNotSame(RequestFingerprint::hash($a->fingerprintPayload()), RequestFingerprint::hash($different->fingerprintPayload()));
        }
        $body = ['variant' => (object) $a->variant->snapshot()];
        $this->assertSame($a->fingerprintPayload(), DraftVariantInput::fromArray(self::ID, self::PRODUCT, '"catalog:'.self::ID.':v1"', $body)->fingerprintPayload());
        foreach (['*', 'W/"catalog:'.self::ID.':v1"', '"catalog:'.self::ID.':v01"', '"catalog:'.self::PRODUCT.':v1"', '"catalog:'.self::ID.':v2147483648"'] as $etag) {
            $this->invalid(fn () => DraftVariantInput::fromArray(self::ID, self::PRODUCT, $etag, $body));
        }
        foreach ([[], ['variant' => []], $body + ['actor' => self::ID]] as $input) {
            $this->invalid(fn () => DraftVariantInput::fromArray(self::ID, self::PRODUCT, '"catalog:'.self::ID.':v1"', $input));
        }
        foreach ([0, 2147483648] as $version) {
            $this->invalid(fn () => new DraftVariantInput(self::ID, self::PRODUCT, $version, $a->variant));
        }
        $this->invalid(fn () => new DraftVariantInput(strtolower(self::ID), self::PRODUCT, 1, $a->variant));
    }

    public function test_snapshot_restoration_enforces_closed_shape_references_and_consecutive_versions(): void
    {
        $op = new DraftVariantOperation(self::ID, new DraftCatalogProfile(self::ID, self::ID, 'Catalog', 3),
            new DraftProductProfile(self::PRODUCT, self::ID, new DraftProductData('Product', null, null), 2), new DraftVariantProfile(self::ID, self::ID, self::PRODUCT, $this->data()));
        $json = json_encode($op->snapshot(), JSON_THROW_ON_ERROR);
        $value = json_decode($json, false, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($json, json_encode(DraftVariantOperation::restore(self::ID, (array) $value)->snapshot(), JSON_THROW_ON_ERROR));
        $mutations = [
            fn ($v) => $v->variant->attributes = [],
            fn ($v) => $v->variant->extra = 'x',
            fn ($v) => $v->variant->version = 2,
            fn ($v) => $v->variant->product_public_id = self::ID,
            fn ($v) => $v->product->catalog_public_id = self::PRODUCT,
            fn ($v) => $v->catalog->version_after = 9,
            fn ($v) => $v->product->version_before = 0,
            fn ($v) => $v->schema_version = 2,
            fn ($v) => $v->extra = 'x',
        ];
        foreach ($mutations as $mutate) {
            $v = json_decode($json);
            $mutate($v);
            $this->invalid(fn () => DraftVariantOperation::restore(self::ID, (array) $v));
        }
        $this->invalid(fn () => new DraftVariantOperation(self::ID, new DraftCatalogProfile(self::ID, self::ID, 'Catalog', 2147483647), $op->productBefore, $op->variant));
        $this->invalid(fn () => new DraftVariantOperation(self::ID, $op->catalogBefore, new DraftProductProfile(self::PRODUCT, self::ID, $op->productBefore->product, 2147483647), $op->variant));
    }

    public function test_cursor_shape_includes_both_parents_and_revisions_and_positive_integer_bounds(): void
    {
        $cursor = new DraftVariantCursor(self::ID, self::ID, self::PRODUCT, 5, 3, 20, 9, 2000000000);
        $this->assertSame($cursor->snapshot(), DraftVariantCursor::restore($cursor->snapshot())->snapshot());
        foreach (['actor_public_id', 'catalog_public_id', 'product_public_id'] as $field) {
            $value = $cursor->snapshot();
            $value[$field] = 'invalid';
            $this->invalid(fn () => DraftVariantCursor::restore($value));
        }
        foreach (['catalog_version', 'product_version', 'limit', 'last_id', 'expires_at'] as $field) {
            foreach ([0, '1'] as $bad) {
                $value = $cursor->snapshot();
                $value[$field] = $bad;
                $this->invalid(fn () => DraftVariantCursor::restore($value));
            }
        }
        foreach (['catalog_version' => 2147483648, 'product_version' => 2147483648, 'limit' => 51, 'schema_version' => 3, 'purpose' => 'other', 'extra' => true] as $field => $bad) {
            $value = $cursor->snapshot();
            $value[$field] = $bad;
            $this->invalid(fn () => DraftVariantCursor::restore($value));
        }
    }
}
