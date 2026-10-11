<?php

namespace Tests\Unit;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductProfile;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeOperation;
use App\Modules\Catalog\Domain\Drafts\DraftVariantCurrentProfile;
use App\Modules\Catalog\Domain\Drafts\DraftVariantCursor;
use App\Modules\Catalog\Domain\Drafts\DraftVariantData;
use App\Modules\Catalog\Domain\Drafts\DraftVariantEditableData;
use App\Modules\Catalog\Domain\Drafts\DraftVariantOperation;
use App\Modules\Catalog\Domain\Drafts\DraftVariantProfile;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DraftVariantChangeTest extends TestCase
{
    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private const PRODUCT = '01ARZ3NDEKTSV4RRFFQ69G5FB1';

    private const VARIANT = '01ARZ3NDEKTSV4RRFFQ69G5FB0';

    private function data(array $replace = []): DraftVariantEditableData
    {
        return DraftVariantEditableData::fromArray(array_replace(['name' => 'Edited', 'size' => null, 'unit' => 'unit', 'barcode' => null, 'attributes' => (object) []], $replace));
    }

    private function reject(callable $call): void
    {
        try {
            $call();
            $this->fail('Expected rejected change');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    private function op(): DraftVariantChangeOperation
    {
        return new DraftVariantChangeOperation(self::ID, new DraftCatalogProfile(self::ID, self::ID, 'Catalog', 3),
            new DraftProductProfile(self::PRODUCT, self::ID, new DraftProductData('Product', null, null), 2),
            new DraftVariantCurrentProfile(self::VARIANT, self::ID, self::PRODUCT, new DraftVariantData('Original', 'SKU', null, 'unit', null, []), 1),
            new DraftVariantCurrentProfile(self::VARIANT, self::ID, self::PRODUCT, $this->data()->withSku('SKU'), 2));
    }

    public function test_editable_shape_requires_all_five_fields_and_refuses_even_unchanged_sku(): void
    {
        $data = $this->data(['name' => str_repeat('á', 255), 'unit' => str_repeat('ñ', 64), 'size' => ' 250 ', 'barcode' => str_repeat('X', 64)]);
        $this->assertSame(' 250 ', $data->size);
        $this->assertSame('{}', json_encode($data->snapshot()['attributes']));
        $this->assertArrayNotHasKey('sku', $data->snapshot());
        $this->assertSame('SKU', $data->withSku('SKU')->sku);
        foreach (array_keys($data->snapshot()) as $field) {
            $value = $data->snapshot();
            unset($value[$field]);
            $this->reject(fn () => DraftVariantEditableData::fromArray($value));
        }
        foreach (['sku' => 'SKU', 'version' => 1, 'price' => 100, 'actor' => self::ID] as $key => $value) {
            $this->reject(fn () => DraftVariantEditableData::fromArray($data->snapshot() + [$key => $value]));
        }
        foreach (['name' => 256, 'size' => 256, 'unit' => 65] as $field => $length) {
            foreach (['', ' ', str_repeat('á', $length), "\xff", null, 1, [], true] as $bad) {
                if ($field === 'size' && $bad === null) {
                    continue;
                }
                $this->reject(fn () => $this->data([$field => $bad]));
            }
        }
        foreach ([[], null, (object) ['bad-key' => 'x'], (object) ['a' => null], (object) ['a' => []], (object) ['a' => ' '], (object) ['a' => "x\n"], (object) array_fill_keys(array_map(fn ($i) => 'k'.$i, range(0, 20)), 'x')] as $bad) {
            $this->reject(fn () => $this->data(['attributes' => $bad]));
        }
    }

    public function test_fingerprint_is_canonical_and_binds_all_references_revision_and_exact_values(): void
    {
        $a = new DraftVariantChangeInput(self::ID, self::PRODUCT, self::VARIANT, 3, $this->data(['attributes' => (object) ['z' => 'last', 'a' => 'first']]));
        $b = new DraftVariantChangeInput(self::ID, self::PRODUCT, self::VARIANT, 3, $this->data(['attributes' => (object) ['a' => 'first', 'z' => 'last']]));
        $hash = RequestFingerprint::hash($a->fingerprintPayload());
        $this->assertSame($hash, RequestFingerprint::hash($b->fingerprintPayload()));
        $this->assertSame([['a', 'first'], ['z', 'last']], $a->fingerprintPayload()['variant']['attributes']);
        $this->assertArrayNotHasKey('sku', $a->fingerprintPayload()['variant']);
        foreach ([
            new DraftVariantChangeInput(self::PRODUCT, self::PRODUCT, self::VARIANT, 3, $a->variant),
            new DraftVariantChangeInput(self::ID, self::ID, self::VARIANT, 3, $a->variant),
            new DraftVariantChangeInput(self::ID, self::PRODUCT, self::ID, 3, $a->variant),
            new DraftVariantChangeInput(self::ID, self::PRODUCT, self::VARIANT, 4, $a->variant),
            new DraftVariantChangeInput(self::ID, self::PRODUCT, self::VARIANT, 3, $this->data(['size' => '250'])),
            new DraftVariantChangeInput(self::ID, self::PRODUCT, self::VARIANT, 3, $this->data(['attributes' => (object) ['z' => 'Last', 'a' => 'first']])),
        ] as $different) {
            $this->assertNotSame($hash, RequestFingerprint::hash($different->fingerprintPayload()));
        }
        $body = ['variant' => (object) $a->variant->snapshot()];
        $this->assertSame($a->fingerprintPayload(), DraftVariantChangeInput::fromArray(self::ID, self::PRODUCT, self::VARIANT, '"catalog:'.self::ID.':v3"', $body)->fingerprintPayload());
        foreach (['*', 'W/"catalog:'.self::ID.':v3"', '"catalog:'.self::ID.':v03"', '"catalog:'.self::PRODUCT.':v3"', '"catalog:'.self::ID.':v2147483648"'] as $etag) {
            $this->reject(fn () => DraftVariantChangeInput::fromArray(self::ID, self::PRODUCT, self::VARIANT, $etag, $body));
        }
        foreach ([[], ['variant' => []], $body + ['extra' => 1]] as $value) {
            $this->reject(fn () => DraftVariantChangeInput::fromArray(self::ID, self::PRODUCT, self::VARIANT, '"catalog:'.self::ID.':v3"', $value));
        }
        foreach ([0, 2147483648] as $version) {
            $this->reject(fn () => new DraftVariantChangeInput(self::ID, self::PRODUCT, self::VARIANT, $version, $a->variant));
        }
        $this->reject(fn () => new DraftVariantChangeInput(self::ID, self::PRODUCT, 'invalid', 3, $a->variant));
    }

    public function test_original_creation_v1_is_separate_from_actual_v2_and_change_snapshot_restores_strictly(): void
    {
        $op = $this->op();
        $old = new DraftVariantOperation(self::ID, $op->catalogBefore, $op->productBefore, new DraftVariantProfile(self::VARIANT, self::ID, self::PRODUCT, $op->variantBefore->variant));
        $this->assertSame(1, $old->snapshot()['variant']['version']);
        $this->assertSame(2, $op->variantAfter->snapshot()['schema_version']);
        $this->assertSame(2, $op->variantAfter->snapshot()['version']);
        $json = json_encode($op->snapshot(), JSON_THROW_ON_ERROR);
        $this->assertSame($json, json_encode(DraftVariantChangeOperation::restore(self::ID, (array) json_decode($json))->snapshot(), JSON_THROW_ON_ERROR));
        foreach ([
            fn ($v) => $v->variant->after->sku = 'OTHER', fn ($v) => $v->variant->after->version = 1,
            fn ($v) => $v->variant->before->version = 0, fn ($v) => $v->variant->after->attributes = [],
            fn ($v) => $v->variant->before->extra = true, fn ($v) => $v->variant->product_public_id = self::ID,
            fn ($v) => $v->product->catalog_public_id = self::PRODUCT, fn ($v) => $v->catalog->version_after = 8,
            fn ($v) => $v->product->version_before = 0, fn ($v) => $v->schema_version = 2, fn ($v) => $v->extra = true,
            fn ($v) => $v->variant->after->name = 'Original',
        ] as $mutate) {
            $value = json_decode($json);
            $mutate($value);
            $this->reject(fn () => DraftVariantChangeOperation::restore(self::ID, (array) $value));
        }
        $this->assertSame(1, $old->snapshot()['variant']['version']);
    }

    public function test_maximum_revisions_no_changes_and_reference_or_sku_changes_reject(): void
    {
        $op = $this->op();
        foreach ([
            fn () => new DraftVariantChangeOperation(self::ID, new DraftCatalogProfile(self::ID, self::ID, 'Catalog', 2147483647), $op->productBefore, $op->variantBefore, $op->variantAfter),
            fn () => new DraftVariantChangeOperation(self::ID, $op->catalogBefore, new DraftProductProfile(self::PRODUCT, self::ID, $op->productBefore->product, 2147483647), $op->variantBefore, $op->variantAfter),
            fn () => new DraftVariantChangeOperation(self::ID, $op->catalogBefore, $op->productBefore, new DraftVariantCurrentProfile(self::VARIANT, self::ID, self::PRODUCT, $op->variantBefore->variant, 2147483647), $op->variantAfter),
            fn () => new DraftVariantChangeOperation(self::ID, $op->catalogBefore, $op->productBefore, $op->variantBefore, new DraftVariantCurrentProfile(self::VARIANT, self::ID, self::PRODUCT, $op->variantBefore->variant, 2)),
            fn () => new DraftVariantCurrentProfile(self::VARIANT, self::ID, self::PRODUCT, $op->variantAfter->variant, 0),
            fn () => new DraftVariantCurrentProfile(self::VARIANT, self::ID, self::PRODUCT, $op->variantAfter->variant, 2147483648),
        ] as $call) {
            $this->reject($call);
        }
        $this->assertSame(2147483647, (new DraftVariantCurrentProfile(self::VARIANT, self::ID, self::PRODUCT, $op->variantAfter->variant, 2147483647))->version);
    }

    public function test_cursor_v2_rejects_all_legacy_v1_tokens_before_context_authority(): void
    {
        $cursor = new DraftVariantCursor(self::ID, self::ID, self::PRODUCT, 3, 2, 20, 4, 2000000000);
        $this->assertSame(2, $cursor->snapshot()['schema_version']);
        $this->assertSame('catalog.local.draft_variants.page.v2', $cursor->snapshot()['purpose']);
        $value = $cursor->snapshot();
        $value['schema_version'] = 1;
        $value['purpose'] = 'catalog.local.draft_variants.page.v1';
        $this->reject(fn () => DraftVariantCursor::restore($value));
    }
}
