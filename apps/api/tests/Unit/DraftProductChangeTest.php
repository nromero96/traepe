<?php

namespace Tests\Unit;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeOperation;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductProfile;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DraftProductChangeTest extends TestCase
{
    private const CATALOG = '01ARZ3NDEKTSV4RRFFQ69G5FB0';

    private const PRODUCT = '01ARZ3NDEKTSV4RRFFQ69G5FB1';

    private function input(): DraftProductChangeInput
    {
        return DraftProductChangeInput::fromArray(self::CATALOG, self::PRODUCT, '"catalog:'.self::CATALOG.':v2"',
            ['product' => ['name' => ' Changed ', 'description' => '', 'brand' => null]]);
    }

    private function operation(): DraftProductChangeOperation
    {
        return new DraftProductChangeOperation(self::PRODUCT, new DraftCatalogProfile(self::CATALOG, self::PRODUCT, 'Catalog', 2),
            new DraftProductProfile(self::PRODUCT, self::CATALOG, new DraftProductData('Original', null, null)),
            new DraftProductProfile(self::PRODUCT, self::CATALOG, $this->input()->product, 2));
    }

    private function invalid(callable $call): void
    {
        try {
            $call();
            $this->fail('Invalid change accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_input_reuses_closed_text_and_etag_contract_without_normalizing(): void
    {
        $this->assertSame(' Changed ', $this->input()->product->name);
        $this->assertSame('', $this->input()->product->description);
        foreach (['invalid', strtolower(self::PRODUCT), '8'.substr(self::PRODUCT, 1)] as $id) {
            $this->invalid(fn () => DraftProductChangeInput::fromArray(self::CATALOG, $id, '"catalog:'.self::CATALOG.':v2"', ['product' => $this->input()->product->snapshot()]));
        }
        foreach (['*', 'W/"catalog:'.self::CATALOG.':v2"', '"catalog:'.self::CATALOG.':v02"', '"catalog:'.self::PRODUCT.':v2"', '"catalog:'.self::CATALOG.':v2147483648"', '"catalog:'.self::CATALOG.':v2","other"'] as $etag) {
            $this->invalid(fn () => DraftProductChangeInput::fromArray(self::CATALOG, self::PRODUCT, $etag, ['product' => $this->input()->product->snapshot()]));
        }
        foreach ([[], ['product' => ['name' => 'x']], ['product' => $this->input()->product->snapshot(), 'actor' => self::PRODUCT],
            ['product' => ['name' => 'x', 'description' => null, 'brand' => null, 'version' => 1]],
            ['product' => ['name' => ' ', 'description' => null, 'brand' => null]],
            ['product' => ['name' => 'x', 'description' => "\tbad", 'brand' => null]]] as $body) {
            $this->invalid(fn () => DraftProductChangeInput::fromArray(self::CATALOG, self::PRODUCT, '"catalog:'.self::CATALOG.':v2"', $body));
        }
        $this->assertSame(2147483647, (new DraftProductChangeInput(self::CATALOG, self::PRODUCT, 2147483647, $this->input()->product))->expectedVersion);
        foreach ([0, -1, 2147483648] as $version) {
            $this->invalid(fn () => new DraftProductChangeInput(self::CATALOG, self::PRODUCT, $version, $this->input()->product));
        }
    }

    public function test_fingerprint_includes_both_references_version_and_exact_null_empty_values(): void
    {
        $original = $this->input()->fingerprintPayload();
        foreach (['catalog_public_id' => self::PRODUCT, 'product_public_id' => self::CATALOG, 'expected_catalog_version' => 3,
            'product' => ['name' => ' Changed ', 'description' => null, 'brand' => null]] as $key => $value) {
            $this->assertNotSame(RequestFingerprint::hash($original), RequestFingerprint::hash(array_replace($original, [$key => $value])));
        }
        $this->assertSame(RequestFingerprint::hash($original), RequestFingerprint::hash(array_reverse($original, true)));
    }

    public function test_closed_history_requires_same_identity_consecutive_revisions_and_a_real_change(): void
    {
        $op = $this->operation();
        $this->assertSame($op->snapshot(), DraftProductChangeOperation::restore($op->publicId, $op->snapshot())->snapshot());
        $this->assertSame(3, $op->snapshot()['catalog']['version_after']);
        $this->assertSame(2, $op->snapshot()['product']['after']['version']);
        $invalid = [];
        $value = $op->snapshot();
        $value['extra'] = true;
        $invalid[] = $value;
        $value = $op->snapshot();
        $value['catalog']['version_after'] = 4;
        $invalid[] = $value;
        $value = $op->snapshot();
        $value['product']['catalog_public_id'] = self::PRODUCT;
        $invalid[] = $value;
        $value = $op->snapshot();
        $value['product']['before']['version'] = '1';
        $invalid[] = $value;
        $value = $op->snapshot();
        $value['product']['after']['version'] = 1;
        $invalid[] = $value;
        $value = $op->snapshot();
        $value['product']['after']['name'] = "\ninvalid";
        $invalid[] = $value;
        $value = $op->snapshot();
        $value['product']['after'] = $value['product']['before'];
        $value['product']['after']['version'] = 2;
        $invalid[] = $value;
        $value = $op->snapshot();
        $value['product']['before']['extra'] = null;
        $invalid[] = $value;
        $value = $op->snapshot();
        $value['product']['after']['status'] = 'active';
        $invalid[] = $value;
        foreach ($invalid as $snapshot) {
            $this->invalid(fn () => DraftProductChangeOperation::restore($op->publicId, $snapshot));
        }
        $this->invalid(fn () => new DraftProductChangeOperation(self::PRODUCT, new DraftCatalogProfile(self::CATALOG, self::PRODUCT, 'Catalog', 2147483647), $op->productBefore, $op->productAfter));
    }
}
